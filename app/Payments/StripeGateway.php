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

    public static function publishableKey(): string
    {
        return trim((string) Settings::get('stripe_publishable_key'));
    }

    /** Card form on the shop's own payment page (Stripe Elements) instead of Stripe's page. Needs the publishable key. */
    public static function embedded(): bool
    {
        return Settings::bool('stripe_embedded', true) && str_starts_with(self::publishableKey(), 'pk_') && self::secretKey() !== '';
    }

    /** One call to the Stripe API; returns the JSON or throws with Stripe's own message. */
    public static function api(string $method, string $path, array $params = []): array
    {
        $res = SecureHttp::send(fn ($http) => $http->asForm()->withToken(self::secretKey())->{$method}('https://api.stripe.com/v1/'.$path, $params));
        if (! $res->successful()) {
            throw new RuntimeException('Stripe: '.($res->json('error.message') ?? 'HTTP '.$res->status()));
        }

        return $res->json() ?? [];
    }

    /** The customer's record at Stripe (holds their saved cards); created the first time. */
    public static function customerFor(\App\Models\User $user): string
    {
        if (filled($user->stripe_customer_id)) {
            return $user->stripe_customer_id;
        }
        $customer = self::api('post', 'customers', ['email' => $user->email, 'name' => $user->name, 'metadata[user_id]' => $user->id]);
        $user->forceFill(['stripe_customer_id' => $customer['id']])->save();

        return $customer['id'];
    }

    /** Cards saved at Stripe for this customer: [['id','brand','last4','exp']]. */
    public static function savedCards(?\App\Models\User $user): array
    {
        if (! $user || blank($user->stripe_customer_id ?? null) || self::secretKey() === '') {
            return [];
        }
        try {
            $list = self::api('get', 'customers/'.$user->stripe_customer_id.'/payment_methods', ['type' => 'card', 'limit' => 10]);
        } catch (\Throwable) {
            return [];
        }

        return array_map(fn ($pm) => ['id' => $pm['id'], 'brand' => ucfirst((string) ($pm['card']['brand'] ?? 'card')), 'last4' => $pm['card']['last4'] ?? '',
            'exp' => sprintf('%02d/%02d', $pm['card']['exp_month'] ?? 0, ($pm['card']['exp_year'] ?? 0) % 100)], $list['data'] ?? []);
    }

    /** Forgets one saved card (only if it belongs to this customer). */
    public static function removeCard(\App\Models\User $user, string $paymentMethod): bool
    {
        if (! collect(self::savedCards($user))->contains('id', $paymentMethod)) {
            return false;
        }
        self::api('post', 'payment_methods/'.$paymentMethod.'/detach');

        return true;
    }

    /** The PaymentIntent for this order: the open one is reused, otherwise a new one is made. */
    public static function intentFor(Order $order, ?\App\Models\User $user): array
    {
        $amount = (int) round((float) $order->amount_due * 100);
        if (str_starts_with((string) $order->payment_reference, 'pi_')) {
            $pi = self::api('get', 'payment_intents/'.$order->payment_reference);
            if ((int) $pi['amount'] === $amount && in_array($pi['status'], ['requires_payment_method', 'requires_confirmation', 'requires_action'], true)) {
                return $pi;
            }
            if ($pi['status'] === 'succeeded' || $pi['status'] === 'processing') {
                return $pi;
            }
        }
        $pi = self::api('post', 'payment_intents', array_filter([
            'amount' => $amount, 'currency' => 'aed', 'payment_method_types[0]' => 'card',
            'description' => 'Order '.$order->number, 'receipt_email' => $order->email,
            'metadata[order_number]' => $order->number,
            'customer' => $user ? self::customerFor($user) : null,
        ], fn ($v) => $v !== null));
        $order->update(['payment_reference' => $pi['id']]);

        return $pi;
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
        // Card form on our own page (the customer never leaves the shop).
        if (self::embedded()) {
            return route('payments.card', $order->number);
        }
        $res = SecureHttp::send(fn ($http) => $http->asForm()->withToken(self::secretKey())
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'expires_at' => max(now()->addMinutes(31)->timestamp, $order->payment_expires_at?->timestamp ?? 0),
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
        if (($event['type'] ?? null) === 'payment_intent.succeeded') {
            return $this->markPaidFromIntent($event['data']['object'] ?? [], ['event' => $event['id'] ?? null]);
        }
        if (($event['type'] ?? null) !== 'checkout.session.completed' || ($event['data']['object']['payment_status'] ?? null) !== 'paid') {
            return null;
        }

        return $this->markPaidFrom($event['data']['object'], ['event' => $event['id'] ?? null]);
    }

    /** Shopper is back from Stripe: ask Stripe directly whether that session is paid. */
    private function confirmReturn(Request $request): ?Order
    {
        // Back from our own card page: ask Stripe about that PaymentIntent.
        $pi = (string) $request->query('payment_intent');
        if (preg_match('/^pi_[A-Za-z0-9_]+$/', $pi) && self::secretKey() !== '') {
            return $this->markPaidFromIntent(self::api('get', 'payment_intents/'.$pi), ['intent' => $pi]);
        }
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

    public function markPaidFromIntent(array $pi, array $payload): ?Order
    {
        if (($pi['status'] ?? null) !== 'succeeded') {
            return null;
        }
        $order = Order::where('number', $pi['metadata']['order_number'] ?? '')->first();
        if (! $order || (int) ($pi['amount_received'] ?? $pi['amount'] ?? 0) !== (int) round((float) $order->amount_due * 100)) {
            return null;
        }

        return app(OrderService::class)->markPaid($order, 'stripe', $pi['id'], $payload);
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
