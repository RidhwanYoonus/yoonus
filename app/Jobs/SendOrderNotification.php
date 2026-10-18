<?php

namespace App\Jobs;

use App\Mail\ShopMessage;
use App\Messaging\Messenger;
use App\Models\Order;
use App\Pricing\Money;
use App\Shop\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\URL;

/** Order status updates by email + WhatsApp, in the customer's language. */
class SendOrderNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $orderId, public string $event) {}

    public function handle(Messenger $messenger, InvoiceService $invoices): void
    {
        $order = Order::find($this->orderId);
        if (! $order) {
            return;
        }

        Messenger::inLocale($order->locale, function () use ($order, $messenger, $invoices) {
            $vars = [
                'number' => $order->number,
                'name' => $order->customer_name,
                'total' => Money::format(Money::toFils($order->grand_total)),
                'tracking' => $order->tracking_number ?: '—',
                'branch' => $order->pickupBranch?->localized('name') ?? '',
            ];
            $event = $order->isPickup() && $this->event === 'delivered' ? 'collected' : $this->event;
            $subject = __("shop.notify.{$event}.subject", $vars);
            $body = __("shop.notify.{$event}.body", $vars);
            if ($subject === "shop.notify.{$event}.subject") {
                return;   // no template for this status
            }

            $pdf = $this->event === 'paid' && $order->invoice_number ? $invoices->pdf($order)->output() : null;
            $messenger->email($order->email, new ShopMessage(
                $subject, [$body], __('shop.notify.view_order'), URL::signedRoute('order.show', ['order' => $order->number]),
                $order->locale, $pdf, $pdf ? $invoices->filename($order) : null,
            ), 'order_'.$this->event);

            if ($pdf && \App\Support\Settings::bool('whatsapp_send_invoice')) {
                // Tax invoice PDF on WhatsApp too, with a download link as a fallback.
                $link = URL::signedRoute('order.invoice', ['order' => $order->number]);
                $messenger->whatsappDocument($order->phone, $pdf, $invoices->filename($order), $subject."\n".$body."\n".__('shop.notify.invoice_link').' '.$link, 'order_'.$this->event);
            } else {
                $messenger->whatsapp($order->phone, $subject."\n".$body, 'order_'.$this->event);
            }
        });
    }
}
