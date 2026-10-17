<?php

namespace App\Payments;

use App\Models\Order;
use App\Shop\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Tabby "Pay in 4". Status is re-fetched from Tabby's API (never trusted from the
 * webhook body) and the authorised payment is captured. Sandbox-ready.
 */
class TabbyGateway implements PaymentGateway
{
    private const API = 'https://api.tabby.ai/api/v2';

    public function key(): string
    {
        return 'tabby';
    }

    public function start(Order $order): string
    {
        $order->loadMissing('items');
        $res = Http::withToken(config('services.tabby.secret_key'))->post(self::API.'/checkout', [
            'payment' => [
                'amount' => number_format((float) $order->amount_due, 2, '.', ''),
                'currency' => 'AED',
                'description' => 'Order '.$order->number,
                'buyer' => ['phone' => $order->phone, 'email' => $order->email, 'name' => $order->customer_name],
                'shipping_address' => [
                    'city' => $order->shipping_address['city'] ?? 'Dubai',
                    'address' => $order->shipping_address['line1'] ?? 'Store pickup',
                    'zip' => '00000',
                ],
                'order' => [
                    'reference_id' => $order->number,
                    'items' => $order->items->map(fn ($i) => [
                        'title' => $i->product_name, 'quantity' => $i->qty,
                        'unit_price' => number_format((float) $i->line_total / $i->qty, 2, '.', ''),
                        'reference_id' => $i->sku, 'category' => 'Jewellery',
                    ])->all(),
                ],
            ],
            'lang' => $order->locale,
            'merchant_code' => config('services.tabby.merchant_code'),
            'merchant_urls' => [
                'success' => route('payments.return', ['gateway' => 'tabby', 'order' => $order->number]),
                'cancel' => route('checkout.show'),
                'failure' => route('checkout.show', ['payment' => 'declined']),
            ],
        ])->throw()->json();

        $url = $res['configuration']['available_products']['installments'][0]['web_url'] ?? null;
        if (($res['status'] ?? null) !== 'created' || ! $url) {
            throw new RuntimeException(__('shop.pay.bnpl_rejected'));
        }
        $order->update(['payment_reference' => $res['payment']['id']]);

        return $url;
    }

    public function confirm(Request $request): ?Order
    {
        $paymentId = $request->input('payment_id') ?? $request->input('id');
        $order = $paymentId ? Order::where('payment_reference', $paymentId)->first() : null;
        if (! $order) {
            return null;
        }
        $api = Http::withToken(config('services.tabby.secret_key'));
        $payment = $api->get(self::API.'/payments/'.$paymentId)->throw()->json();
        $status = strtoupper($payment['status'] ?? '');
        if (! in_array($status, ['AUTHORIZED', 'CLOSED'], true) || abs((float) $payment['amount'] - (float) $order->amount_due) > 0.001) {
            return null;
        }
        if ($status === 'AUTHORIZED') {
            $api->post(self::API.'/payments/'.$paymentId.'/captures', ['amount' => $payment['amount']])->throw();
        }

        return app(OrderService::class)->markPaid($order, 'tabby', $paymentId);
    }
}
