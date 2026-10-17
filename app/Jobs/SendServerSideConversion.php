<?php

namespace App\Jobs;

use App\Models\Order;
use App\Support\Settings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * Server-side purchase events (GA4 Measurement Protocol + Meta Conversions API),
 * so purchases are counted even when browser pixels are blocked.
 */
class SendServerSideConversion implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        $order = Order::with('items')->find($this->orderId);
        if (! $order || $order->conversion_sent) {
            return;
        }
        $value = (float) $order->grand_total;
        $items = $order->items->map(fn ($i) => ['item_id' => $i->sku, 'item_name' => $i->product_name, 'quantity' => $i->qty, 'price' => round((float) $i->line_total / $i->qty, 2)])->all();

        if (($ga = Settings::get('ga4_id')) && ($secret = Settings::get('ga4_api_secret'))) {
            Http::post("https://www.google-analytics.com/mp/collect?measurement_id={$ga}&api_secret={$secret}", [
                'client_id' => hash('sha256', $order->email),
                'events' => [['name' => 'purchase', 'params' => [
                    'transaction_id' => $order->number, 'currency' => 'AED', 'value' => $value,
                    'tax' => (float) $order->vat_total, 'shipping' => (float) $order->shipping_total, 'items' => $items,
                ]]],
            ]);
        }

        if (($pixel = Settings::get('meta_pixel_id')) && ($token = Settings::get('meta_capi_token'))) {
            Http::post("https://graph.facebook.com/v21.0/{$pixel}/events?access_token={$token}", [
                'data' => [[
                    'event_name' => 'Purchase',
                    'event_time' => $order->paid_at?->timestamp ?? time(),
                    'event_id' => $order->number,        // de-duplicates with the browser pixel
                    'action_source' => 'website',
                    'user_data' => [
                        'em' => [hash('sha256', strtolower(trim($order->email)))],
                        'ph' => [hash('sha256', preg_replace('/\D/', '', $order->phone))],
                    ],
                    'custom_data' => ['currency' => 'AED', 'value' => $value, 'content_ids' => array_column($items, 'item_id'), 'content_type' => 'product'],
                ]],
            ]);
        }

        $order->forceFill(['conversion_sent' => true])->saveQuietly();
    }
}
