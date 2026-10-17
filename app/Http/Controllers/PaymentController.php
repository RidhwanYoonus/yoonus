<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Payments\GatewayManager;
use App\Payments\MockGateway;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(private readonly GatewayManager $gateways) {}

    public function mock(Request $request, string $order)
    {
        $order = Order::where('number', $order)->firstOrFail();

        return view('shop.mock-payment', ['seo' => Seo::page('Test payment'), 'order' => $order]);
    }

    public function mockApprove(Request $request, string $order)
    {
        $paid = app(MockGateway::class)->confirm($request);

        return redirect()->route('checkout.complete', $paid?->number ?? $order);
    }

    /** Shopper returns from the hosted page; we still verify server-to-server. */
    public function return(Request $request, string $gateway, string $order)
    {
        abort_unless(in_array($gateway, ['telr', 'tabby', 'tamara', 'stripe'], true), 404);
        try {
            if ($gateway !== 'stripe') {
                $this->gateways->for($gateway)->confirm($request);
            }
        } catch (Throwable $e) {
            Log::warning("Payment return check failed for {$order}", ['error' => $e->getMessage()]);
        }

        return redirect()->route('checkout.complete', $order);
    }

    public function webhook(Request $request, string $gateway)
    {
        abort_unless(in_array($gateway, ['stripe', 'telr', 'tabby', 'tamara'], true), 404);
        if ($gateway === 'tamara' && config('services.tamara.notification_token')
            && ! hash_equals((string) config('services.tamara.notification_token'), (string) $request->query('tamaraToken', $request->bearerToken()))) {
            abort(401);
        }
        $order = $this->gateways->for($gateway)->confirm($request);

        return response()->json(['ok' => true, 'order' => $order?->number]);
    }
}
