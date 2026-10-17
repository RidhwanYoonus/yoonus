<?php

namespace App\Payments;

use App\Models\Order;
use App\Shop\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** Stripe Checkout (AED). Order is marked paid only from a signature-verified webhook. */
class StripeGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'stripe';
    }

    public function start(Order $order): string
    {
        $session = Http::asForm()->withToken(config('services.stripe.secret'))
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'client_reference_id' => $order->number,
                'customer_email' => $order->email,
                'locale' => $order->locale === 'ar' ? 'auto' : 'en',
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => 'aed',
                'line_items[0][price_data][unit_amount]' => (int) round((float) $order->amount_due * 100),
                'line_items[0][price_data][product_data][name]' => 'Order '.$order->number,
                'metadata[order_number]' => $order->number,
                'success_url' => route('checkout.complete', ['order' => $order->number]),
                'cancel_url' => route('checkout.show'),
            ])->throw()->json();

        $order->update(['payment_reference' => $session['id']]);

        return $session['url'];
    }

    public function confirm(Request $request): ?Order
    {
        $payload = $request->getContent();
        if (! $this->validSignature($payload, (string) $request->header('Stripe-Signature'), (string) config('services.stripe.webhook_secret'))) {
            abort(400, 'Invalid signature');
        }
        $event = json_decode($payload, true);
        if (($event['type'] ?? null) !== 'checkout.session.completed' || ($event['data']['object']['payment_status'] ?? null) !== 'paid') {
            return null;
        }
        $session = $event['data']['object'];
        $order = Order::where('number', $session['metadata']['order_number'] ?? $session['client_reference_id'] ?? '')->first();
        if (! $order || (int) $session['amount_total'] !== (int) round((float) $order->amount_due * 100)) {
            return null;
        }

        return app(OrderService::class)->markPaid($order, 'stripe', $session['payment_intent'] ?? $session['id'], ['event' => $event['id'] ?? null]);
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
