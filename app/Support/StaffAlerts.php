<?php

namespace App\Support;

use App\Mail\ShopMessage;
use App\Messaging\Messenger;
use App\Models\Order;

/** Messages to the shop's own staff: new orders and video-call requests (numbers and emails in Settings). */
class StaffAlerts
{
    /** WhatsApp numbers (or emails) typed in a Settings box, one per line or separated by commas. */
    public static function numbers(string $key): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) Settings::get($key, '')))));
    }

    /** Paid after the payment time ran out, but a piece had been sold to someone else: staff must refund. */
    public static function refundNeeded(Order $order, array $names): void
    {
        $text = "⚠️ Refund needed: order {$order->number} was paid late (AED ".number_format((float) $order->amount_due, 2).') but '
            .implode(', ', $names)." had already been sold. Nothing was sold twice.\n{$order->customer_name} · {$order->phone}\n".url('/admin/orders/'.$order->id);
        $messenger = app(Messenger::class);
        $numbers = self::numbers('staff_order_whatsapp');
        foreach ($numbers as $number) {
            $messenger->whatsapp($number, $text, 'staff_refund_needed');
        }
        $emails = self::numbers('staff_order_emails') ?: array_filter([Settings::get('store_email')]);
        foreach ($emails as $email) {
            $messenger->email($email, new ShopMessage('Refund needed — order '.$order->number, explode("\n", $text), 'Open the order', url('/admin/orders/'.$order->id)), 'staff_refund_needed');
        }
    }

    /** A new order to look after: COD placed, or an online order paid. */
    public static function newOrder(Order $order, string $why): void
    {
        if (! Settings::bool('staff_order_alerts')) {
            return;
        }
        $order->loadMissing('items');
        $items = $order->items->map(fn ($i) => $i->qty.' × '.$i->product_name.($i->optionsText() ? ' ('.$i->optionsText().')' : ''))->implode(', ');
        $text = "🛍 New order {$order->number} ({$why})\n{$order->customer_name} · {$order->phone}\n{$items}\nTotal AED ".number_format((float) $order->grand_total, 2)
            ."\n".($order->fulfilment === 'pickup' ? 'Store pickup' : 'Delivery'.($order->deliveryMapUrl() ? ' 📍 '.$order->deliveryMapUrl() : ''))."\n".url('/admin/orders/'.$order->id);
        $messenger = app(Messenger::class);
        foreach (self::numbers('staff_order_whatsapp') as $number) {
            $messenger->whatsapp($number, $text, 'staff_new_order');
        }
        foreach (self::numbers('staff_order_emails') as $email) {
            $messenger->email($email, new ShopMessage('New order '.$order->number.' — AED '.number_format((float) $order->grand_total, 2),
                explode("\n", $text), 'Open the order', url('/admin/orders/'.$order->id)), 'staff_new_order');
        }
    }
}
