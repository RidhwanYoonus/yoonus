<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Shop\OrderService;
use App\Support\SecureHttp;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Mettpay (PayBy) hosted card payments — API: POST /api/orders/createorder with merchantKey /
 * merchantSecret headers. Keys are entered in Settings → Payments (never in code).
 *
 * The documented API has no payment-status call, so a return to the success page alone does not
 * mark an order paid (anyone could open that address). The order is flagged "payment being
 * confirmed" for staff to check in the Mettpay dashboard — unless a status URL is set in Settings,
 * in which case the payment is confirmed automatically, server to server.
 */
class MettpayGateway implements PaymentGateway
{
    public const DEFAULT_URL = 'https://application.mettpay.com/api/orders/createorder';

    private const ERRORS = [
        'INVALID_PARAMETER' => 'Mettpay rejected the payment details (invalid parameter).',
        'REQUESTTIME_TOO_EARLY' => 'This computer\'s clock is wrong — Mettpay needs the time to be within 15 minutes.',
        'REQUESTTIME_TOO_LATER' => 'This computer\'s clock is wrong — Mettpay needs the time to be within 15 minutes.',
        'RATE_LIMIT_REJECT' => 'Too many payment attempts at once — please try again in a minute.',
        'UNAUTHORIZED' => 'Mettpay says this merchant account may not use the payment API — please contact Mettpay / PayBy.',
        'SERVICE_NOT_AVAILABLE' => 'Mettpay is busy right now — please try again shortly.',
    ];

    public function key(): string
    {
        return 'mettpay';
    }

    public static function configured(): bool
    {
        return filled(Settings::get('mettpay_merchant_key')) && filled(Settings::get('mettpay_merchant_secret'));
    }

    public function start(Order $order): string
    {
        if (! self::configured()) {
            throw new RuntimeException('Mettpay merchant key and secret are not set (Settings → Payments).');
        }
        [$first, $last] = array_pad(explode(' ', trim($order->customer_name), 2), 2, '');
        $back = fn (string $result) => URL::signedRoute('payments.return', ['gateway' => 'mettpay', 'order' => $order->number, 'result' => $result]);

        $res = SecureHttp::send(fn ($http) => $http->withHeaders(self::headers())->asJson()->post((string) Settings::get('mettpay_api_url', self::DEFAULT_URL), [
            'order' => [
                'id' => $order->number,
                'amount' => round((float) $order->amount_due, 2),
                'remarks' => 'Order '.$order->number,
                'returnUrl' => $back('home'),
                'returnSuccUrl' => $back('success'),
                'returnErrorUrl' => $back('error'),
            ],
            'customer' => [
                'email' => $order->email,
                'firstName' => $first ?: $order->customer_name,
                'lastName' => $last ?: $first,
                'mobilePhone' => $order->phone,
                'phone' => $order->phone,
            ],
        ]));

        $json = $res->json() ?? [];
        if ($res->failed() || ! ($url = self::findUrl($json))) {
            $code = self::findValue($json, ['message', 'msg', 'code', 'error']) ?? 'HTTP '.$res->status();
            throw new RuntimeException((self::ERRORS[$code] ?? 'Mettpay could not start the payment.').' (HTTP '.$res->status().') Mettpay replied: '.mb_substr($res->body() ?: '(empty)', 0, 300));
        }

        $order->update(['payment_reference' => (string) (self::findValue($json, ['orderNo', 'orderId', 'merchantOrderNo', 'reference', 'token', 'id']) ?? $order->number)]);
        Payment::updateOrCreate(['gateway' => 'mettpay', 'reference' => $order->payment_reference], ['order_id' => $order->id, 'status' => 'started', 'amount' => $order->amount_due, 'payload' => $json]);

        return $url;
    }

    /** Settings → "Test Mettpay": AED 1.00 test order; returns the payment page URL or throws Mettpay's reply. */
    public static function testConnection(): array
    {
        if (! self::configured()) {
            throw new RuntimeException('Enter the merchant key and secret and click Save settings first.');
        }
        $id = 'TEST-'.now()->format('YmdHis');
        $res = SecureHttp::send(fn ($http) => $http->withHeaders(self::headers())->asJson()->post((string) Settings::get('mettpay_api_url', self::DEFAULT_URL), [
            'order' => ['id' => $id, 'amount' => 1.00, 'remarks' => 'Connection test', 'returnUrl' => url('/'), 'returnSuccUrl' => url('/'), 'returnErrorUrl' => url('/')],
            'customer' => ['email' => (string) Settings::get('store_email'), 'firstName' => 'Test', 'lastName' => 'Order', 'mobilePhone' => '+971500000000', 'phone' => '+971500000000'],
        ]));
        $json = $res->json() ?? [];
        if ($res->failed() || ! ($url = self::findUrl($json))) {
            throw new RuntimeException('HTTP '.$res->status().' — '.(self::ERRORS[self::findValue($json, ['message', 'msg', 'code', 'error'])] ?? '').' Mettpay replied: '.mb_substr($res->body() ?: '(empty)', 0, 500));
        }

        return ['url' => $url, 'response' => $json];
    }

    private static function headers(): array
    {
        return [
            'merchantKey' => trim((string) Settings::get('mettpay_merchant_key')),
            'merchantSecret' => trim((string) Settings::get('mettpay_merchant_secret')),
            'Accept' => 'application/json',
        ];
    }

    /** Called when the shopper comes back to returnSuccUrl (signed link). */
    public function confirm(Request $request): ?Order
    {
        $order = Order::where('number', $request->route('order'))->first();
        if (! $order || $order->isPaid()) {
            return $order;
        }

        if ($statusUrl = Settings::get('mettpay_status_url')) {
            $res = SecureHttp::send(fn ($http) => $http->withHeaders([
                'merchantKey' => Settings::get('mettpay_merchant_key'),
                'merchantSecret' => Settings::get('mettpay_merchant_secret'),
                'Accept' => 'application/json',
            ])->get(str_replace('{id}', urlencode($order->number), $statusUrl), ['orderId' => $order->number]))->json() ?? [];
            $status = strtoupper((string) self::findValue($res, ['status', 'orderStatus', 'paymentStatus', 'state']));
            $amount = self::findValue($res, ['amount', 'paidAmount', 'orderAmount']);
            if (in_array($status, ['PAID', 'SUCCESS', 'SUCCEEDED', 'SETTLED', 'COMPLETED', 'CAPTURED'], true)
                && ($amount === null || abs((float) $amount - (float) $order->amount_due) < 0.01)) {
                return app(OrderService::class)->markPaid($order, 'mettpay', $order->payment_reference, $res);
            }

            return null;
        }

        // No status API: flag for staff to confirm in the Mettpay dashboard.
        if ($order->payment_status === 'unpaid') {
            $order->update(['payment_status' => 'verifying']);
            app(OrderService::class)->history($order, $order->status, $order->status, null, 'Customer returned from Mettpay success page — confirm the payment in the Mettpay dashboard, then set status to Paid.');
        }

        return null;
    }

    private static function findUrl(array $json): ?string
    {
        foreach (Arr::dot($json) as $key => $value) {
            if (is_string($value) && str_starts_with($value, 'http') && preg_match('/(url|link|redirect|checkout|pay)/i', (string) $key)) {
                return $value;
            }
        }

        return null;
    }

    private static function findValue(array $json, array $names): mixed
    {
        foreach (Arr::dot($json) as $key => $value) {
            if (in_array(last(explode('.', (string) $key)), $names, true) && $value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
