<?php

namespace App\Payments;

use App\Models\Order;
use App\Shop\OrderService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** Tamara instalments. Order status is re-read from Tamara and authorised server-side. Sandbox-ready. */
class TamaraGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'tamara';
    }

    private function api(): PendingRequest
    {
        return Http::withToken(config('services.tamara.token'))->baseUrl(config('services.tamara.base_url'));
    }

    public function start(Order $order): string
    {
        $order->loadMissing('items');
        $money = fn ($v) => ['amount' => round((float) $v, 2), 'currency' => 'AED'];
        [$first, $last] = array_pad(explode(' ', $order->customer_name, 2), 2, '-');

        $res = $this->api()->post('/checkout', [
            'order_reference_id' => $order->number,
            'total_amount' => $money($order->amount_due),
            'description' => 'Order '.$order->number,
            'country_code' => 'AE',
            'payment_type' => 'PAY_BY_INSTALMENTS',
            'locale' => $order->locale === 'ar' ? 'ar_AE' : 'en_US',
            'items' => $order->items->map(fn ($i) => [
                'reference_id' => (string) $i->id, 'type' => 'Physical', 'name' => $i->product_name, 'sku' => $i->sku,
                'quantity' => $i->qty, 'total_amount' => $money($i->line_total),
            ])->all(),
            'consumer' => ['first_name' => $first, 'last_name' => $last, 'phone_number' => $order->phone, 'email' => $order->email],
            'shipping_address' => [
                'first_name' => $first, 'last_name' => $last, 'country_code' => 'AE',
                'line1' => $order->shipping_address['line1'] ?? 'Store pickup',
                'city' => $order->shipping_address['city'] ?? 'Dubai',
            ],
            'tax_amount' => $money($order->vat_total),
            'shipping_amount' => $money($order->shipping_total),
            'merchant_url' => [
                'success' => route('payments.return', ['gateway' => 'tamara', 'order' => $order->number]),
                'failure' => route('checkout.show', ['payment' => 'declined']),
                'cancel' => route('checkout.show'),
                'notification' => route('payments.webhook', ['gateway' => 'tamara']),
            ],
        ])->throw()->json();

        $order->update(['payment_reference' => $res['order_id']]);

        return $res['checkout_url'];
    }

    public function confirm(Request $request): ?Order
    {
        $tamaraId = $request->input('orderId') ?? $request->input('order_id');
        $order = $tamaraId ? Order::where('payment_reference', $tamaraId)->first() : null;
        if (! $order) {
            return null;
        }
        $remote = $this->api()->get('/orders/'.$tamaraId)->throw()->json();
        $status = $remote['status'] ?? '';
        if ($status === 'approved') {
            $remote = $this->api()->post('/orders/'.$tamaraId.'/authorise')->throw()->json();
            $status = $remote['status'] ?? 'authorised';
        }
        if (! in_array($status, ['authorised', 'fully_captured'], true)) {
            return null;
        }

        return app(OrderService::class)->markPaid($order, 'tamara', $tamaraId);
    }
}
