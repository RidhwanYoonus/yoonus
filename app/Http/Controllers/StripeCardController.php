<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Payments\StripeGateway;
use App\Shop\OrderService;
use App\Support\Seo;
use Illuminate\Http\Request;
use Throwable;

/**
 * Card payment on the shop's own page. The card boxes are Stripe's secure fields (the card number goes straight
 * to Stripe, never to this server); saved cards are kept at Stripe and only listed here.
 */
class StripeCardController extends Controller
{
    private function order(Request $request, string $number): Order
    {
        $order = Order::where('number', $number)->firstOrFail();
        abort_unless(OrderController::canView($request, $order) && $order->payment_method !== 'cod', 403);
        abort_unless(StripeGateway::embedded(), 404);

        return $order;
    }

    public function show(Request $request, string $order)
    {
        $order = $this->order($request, $order);
        if (app(OrderService::class)->expireUnpaid($order)) {
            return OrderController::backToBag($order);
        }
        if (! $order->awaitingPayment()) {
            return redirect()->route('checkout.complete', $order->number);
        }
        try {
            $intent = StripeGateway::intentFor($order, $request->user());
        } catch (Throwable $e) {
            return CheckoutController::paymentStartFailed($request, $order, $e);
        }
        if ($intent['status'] === 'succeeded') {
            return redirect()->route('payments.return', ['gateway' => 'stripe', 'order' => $order->number, 'payment_intent' => $intent['id']]);
        }

        return view('shop.pay-card', [
            'seo' => Seo::page(__('shop.card.title')),
            'order' => $order,
            'clientSecret' => $intent['client_secret'],
            'publishableKey' => StripeGateway::publishableKey(),
            'cards' => StripeGateway::savedCards($request->user()),
            'canSave' => (bool) $request->user(),
            'returnUrl' => route('payments.return', ['gateway' => 'stripe', 'order' => $order->number]),
        ]);
    }

    /** Just before the new card is sent: keep it at Stripe for next time, or not. */
    public function prepare(Request $request, string $order)
    {
        $order = $this->order($request, $order);
        $intent = StripeGateway::intentFor($order, $request->user());
        $save = $request->boolean('save') && $request->user();
        StripeGateway::api('post', 'payment_intents/'.$intent['id'], ['setup_future_usage' => $save ? 'off_session' : '']);

        return response()->json(['ok' => true]);
    }

    /** Pay with a card saved earlier. The bank may still ask for its code (3-D Secure) in the browser. */
    public function saved(Request $request, string $order)
    {
        $order = $this->order($request, $order);
        $pm = (string) $request->input('payment_method');
        abort_unless($request->user() && collect(StripeGateway::savedCards($request->user()))->contains('id', $pm), 403);
        $intent = StripeGateway::intentFor($order, $request->user());
        try {
            $pi = StripeGateway::api('post', 'payment_intents/'.$intent['id'].'/confirm', [
                'payment_method' => $pm, 'return_url' => route('payments.return', ['gateway' => 'stripe', 'order' => $order->number]),
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => __('shop.card.declined')], 422);
        }
        if ($pi['status'] === 'succeeded') {
            app(StripeGateway::class)->markPaidFromIntent($pi, ['intent' => $pi['id'], 'saved_card' => true]);

            return response()->json(['done' => route('checkout.complete', $order->number)]);
        }
        if ($pi['status'] === 'requires_action') {
            return response()->json(['action' => true, 'client_secret' => $pi['client_secret']]);
        }

        return response()->json(['error' => __('shop.card.declined')], 422);
    }
}
