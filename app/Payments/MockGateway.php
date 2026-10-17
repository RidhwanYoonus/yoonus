<?php

namespace App\Payments;

use App\Models\Order;
use App\Shop\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/** Simulated hosted payment page for development and demos (disabled in production). */
class MockGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'mock';
    }

    public function start(Order $order): string
    {
        abort_if(app()->isProduction() && ! config('services.mock_payments'), 503, 'Card payments are not configured yet.');

        return URL::temporarySignedRoute('payments.mock', now()->addMinutes(30), ['order' => $order->number]);
    }

    public function confirm(Request $request): ?Order
    {
        if (! $request->hasValidSignature()) {
            return null;
        }
        $order = Order::where('number', $request->route('order'))->firstOrFail();

        return app(OrderService::class)->markPaid($order, 'mock', 'MOCK-'.$order->number);
    }
}
