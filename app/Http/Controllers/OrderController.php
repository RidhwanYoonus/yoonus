<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Shop\InvoiceService;
use App\Support\Seo;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public static function canView(Request $request, Order $order): bool
    {
        return ($order->user_id && $request->user()?->id === $order->user_id)
            || in_array($order->number, $request->session()->get('orders', []), true)
            || $request->hasValidSignature()
            || \App\Support\StaffAccess::can($request->user(), 'orders');
    }

    public function track(Request $request)
    {
        if ($request->filled(['number', 'email'])) {
            $order = Order::where('number', strtoupper(trim($request->input('number'))))
                ->where('email', strtolower(trim($request->input('email'))))->first();
            if ($order) {
                $request->session()->push('orders', $order->number);

                return redirect()->route('order.show', $order->number);
            }

            return back()->withInput()->withErrors(['number' => __('shop.track.not_found')]);
        }

        return view('shop.track', ['seo' => Seo::page(__('shop.nav.track'))]);
    }

    public function show(Request $request, string $order)
    {
        $order = Order::with('items', 'statusHistory', 'pickupBranch')->where('number', $order)->firstOrFail();
        abort_unless(self::canView($request, $order), 403);
        if ($request->hasValidSignature()) {
            $request->session()->push('orders', $order->number);
        }
        if (app(\App\Shop\OrderService::class)->expireUnpaid($order)) {
            return self::backToBag($order);
        }

        return view('shop.order', ['seo' => Seo::page(__('shop.order.title', ['number' => $order->number])), 'order' => $order]);
    }

    /** Payment time ran out: put the pieces back in the bag so the customer can check out at today's price. */
    public static function backToBag(Order $order)
    {
        $cart = app(\App\Shop\CartService::class);
        foreach ($order->items()->with('product')->get() as $item) {
            if ($item->product && $item->product->inStock()) {
                try {
                    $cart->add($item->product, $item->qty);
                } catch (\Throwable) {
                    // Sold out or hidden meanwhile: just leave it out.
                }
            }
        }

        return redirect()->route('cart.show')->withErrors(['payment' => __('shop.pay.expired', ['minutes' => \App\Shop\OrderService::paymentWindow()])]);
    }

    public function invoice(Request $request, string $order, InvoiceService $invoices)
    {
        $order = Order::where('number', $order)->firstOrFail();
        abort_unless(self::canView($request, $order) && $order->invoice_number, 404);

        return $invoices->pdf($order)->download($invoices->filename($order));
    }
}
