<?php

namespace App\Payments;

use App\Models\Order;
use App\Shop\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Telr hosted payment page (AED). Payment is confirmed with a server-side `check` call.
 * Ready for sandbox testing once the Telr store ID and auth key are set in .env.
 */
class TelrGateway implements PaymentGateway
{
    private const ENDPOINT = 'https://secure.telr.com/gateway/order.json';

    public function key(): string
    {
        return 'telr';
    }

    public function start(Order $order): string
    {
        $res = Http::asForm()->post(self::ENDPOINT, [
            'ivp_method' => 'create',
            'ivp_store' => config('services.telr.store_id'),
            'ivp_authkey' => config('services.telr.auth_key'),
            'ivp_test' => config('services.telr.test') ? 1 : 0,
            'ivp_cart' => $order->number,
            'ivp_amount' => number_format((float) $order->amount_due, 2, '.', ''),
            'ivp_currency' => 'AED',
            'ivp_desc' => 'Order '.$order->number,
            'ivp_lang' => $order->locale,
            'return_auth' => route('payments.return', ['gateway' => 'telr', 'order' => $order->number]),
            'return_decl' => route('checkout.show', ['payment' => 'declined']),
            'return_can' => route('checkout.show'),
            'bill_email' => $order->email,
        ])->throw()->json();

        $order->update(['payment_reference' => $res['order']['ref']]);

        return $res['order']['url'];
    }

    public function confirm(Request $request): ?Order
    {
        $order = Order::where('number', $request->route('order') ?? $request->input('order'))->first();
        if (! $order?->payment_reference) {
            return null;
        }
        $res = Http::asForm()->post(self::ENDPOINT, [
            'ivp_method' => 'check',
            'ivp_store' => config('services.telr.store_id'),
            'ivp_authkey' => config('services.telr.auth_key'),
            'order_ref' => $order->payment_reference,
        ])->throw()->json();

        $status = (int) ($res['order']['status']['code'] ?? 0);   // 3 = paid
        $amount = (float) ($res['order']['amount'] ?? 0);
        if ($status !== 3 || abs($amount - (float) $order->amount_due) > 0.001) {
            return null;
        }

        return app(OrderService::class)->markPaid($order, 'telr', $res['order']['transaction']['ref'] ?? $order->payment_reference);
    }
}
