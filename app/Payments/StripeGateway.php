<?php

namespace App\Payments;

use App\Models\Order;
use App\Shop\OrderService;
use App\Support\SecureHttp;
use App\Support\Settings;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Stripe Checkout (AED). An order is marked paid from a signature-verified webhook, or when the
 * shopper returns and Stripe itself (asked server-to-server with the secret key) says it is paid.
 */
class StripeGateway implements PaymentGateway
{
    /** Keys from Settings → Payments, falling back to STRIPE_SECRET / STRIPE_WEBHOOK_SECRET in .env. */
    public static function secretKey(): string
    {
        return trim((string) (Settings::get('stripe_secret_key') ?: config('services.stripe.secret')));
    }

    public static function webhookSecret(): string
    {
        return trim((string) (Settings::get('stripe_webhook_secret') ?: config('services.stripe.webhook_secret')));
    }

    /** Checks the secret key with Stripe; returns 'test' or 'live'. */
    public static function testConnection(): string
    {
        $key = self::secretKey();
        if ($key === '') {
            throw new RuntimeException('Enter your Stripe secret key (sk_test_… or sk_live_…) and save first.');
        }
        if (str_starts_with($key, 'pk_')) {
            throw new RuntimeException('That is the publishable key (pk_…). Paste the secret key (sk_… or rk_…) instead.');
        }
        $res = SecureHttp::send(fn ($http) => $http->withToken($key)->get('https://api.stripe.com/v1/balance'));
        if (! $res->successful()) {
            throw new RuntimeException('Stripe refused the key: '.($res->json('error.message') ?? 'HTTP '.$res->status()));
        }

        return $res->json('livemode') ? 'live' : 'test';
    }

    public function key(): string
    {
        return 'stripe';
    }

    public function start(Order $order): string
    {
        if (self::secretKey() === '') {
            throw new RuntimeException('Stripe secret key is not set (Settings → Payments).');
        }
        $res = SecureHttp::send(fn ($http) => $http->asForm()->withToken(self::secretKey())
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                // Ask for cards explicitly so it works even when no AED methods are switched on in the dashboard.
                'payment_method_types[0]' => 'card',
                'client_reference_id' => $order->number,
                'customer_email' => $order->email,
                'locale' => $order->locale === 'ar' ? 'auto' : 'en',
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => 'aed',
                'line_items[0][price_data][unit_amount]' => (int) round((float) $order->amount_due * 100),
                'line_items[0][price_data][product_data][name]' => 'Order '.$order->number,
                'metadata[order_number]' => $order->number,
                // Stripe fills in {CHECKOUT_SESSION_ID}; it must stay un-encoded.
                'success_url' => route('payments.return', ['gateway' => 'stripe', 'order' => $order->number]).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('order.show', $order->number),
            ]));
        if (! $res->successful()) {
            throw new RuntimeException('Stripe could not start the payment: '.($res->json('error.message') ?? 'HTTP '.$res->status()));
        }
        $session = $res->json();

        $order->update(['payment_reference' => $session['id']]);

        return $session['url'];
    }

    public function confirm(Request $request): ?Order
    {
        if (! $request->hasHeader('Stripe-Signature')) {
            return $this->confirmReturn($request);
        }
        $payload = $request->getContent();
        if (! $this->validSignature($payload, (string) $request->header('Stripe-Signature'), self::webhookSecret())) {
            abort(400, 'Invalid signature');
        }
        $event = json_decode($payload, true);
        if (($event['type'] ?? null) !== 'checkout.session.completed' || ($event['data']['object']['payment_status'] ?? null) !== 'paid') {
            return null;
        }

        return $this->markPaidFrom($event['data']['object'], ['event' => $event['id'] ?? null]);
    }

    /** Shopper is back from Stripe: ask Stripe directly whether that session is paid. */
    private function confirmReturn(Request $request): ?Order
    {
        $id = (string) $request->query('session_id');
        if (! preg_match('/^cs_[A-Za-z0-9_]+$/', $id) || self::secretKey() === '') {
            return null;
        }
        $res = SecureHttp::send(fn ($http) => $http->withToken(self::secretKey())->get('https://api.stripe.com/v1/checkout/sessions/'.$id));
        $session = $res->successful() ? $res->json() : [];
        if (($session['payment_status'] ?? null) !== 'paid') {
            return null;
        }

        return $this->markPaidFrom($session, ['session' => $id]);
    }

    private function markPaidFrom(array $session, array $payload): ?Order
    {
        $order = Order::where('number', $session['metadata']['order_number'] ?? $session['client_reference_id'] ?? '')->first();
        if (! $order || (int) $session['amount_total'] !== (int) round((float) $order->amount_due * 100)) {
            return null;
        }

        return app(OrderService::class)->markPaid($order, 'stripe', $session['payment_intent'] ?? $session['id'], $payload);
    }

    public function validSignature(string $payload, string $header, string $secret, int $tolerance = 300): bool
    {
        $parts = collect(explode(',', $header))->mapToGroups(function ($p) {
            [$k, $v] = array_pad(explode('=', $p, 2), 2, '');

            return [trim($k) => $v];
        });
        $t = (int) ($parts['t'][0] ?? 0);
        if (! $secret || ! $t || abs(time() - $t) > $tolerance) {
            return false;
        }
        $expected = hash_hmac('sha256', $t.'.'.$payload, $secret);

        return collect($parts['v1'] ?? [])->contains(fn ($sig) => hash_equals($expected, $sig));
    }
}
