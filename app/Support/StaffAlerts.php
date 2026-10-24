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

    /** A new order to look after: COD placed, or an online order paid. */
    public static function newOrder(Order $order, string $why): void
    {
        if (! Settings::bool('staff_order_alerts')) {
            return;
        }
        $order->loadMissing('items');
        $items = $order->items->map(fn ($i) => $i->qty.' × '.$i->product_name.($i->optionsText() ? ' ('.$i->optionsText().')' : ''))->implode(', ');
        $text = "🛍 New order {$order->number} ({$why})\n{$order->customer_name} · {$order->phone}\n{$items}\nTotal AED ".number_format((float) $order->grand_total, 2)
            ."\n".($order->fulfilment === 'pickup' ? 'Store pickup' : 'Delivery')."\n".url('/admin/orders/'.$order->id);
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
