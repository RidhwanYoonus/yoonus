<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\GoldCategory;
use App\Models\Order;
use App\Models\ShippingZone;
use App\Payments\GatewayManager;
use App\Pricing\Money;
use App\Shop\CartService;
use App\Shop\OrderService;
use App\Shop\OutOfStockException;
use App\Shop\Quote;
use App\Shop\QuoteBuilder;
use App\Support\Seo;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly QuoteBuilder $quotes,
        private readonly GatewayManager $gateways,
    ) {}

    public function show(Request $request)
    {
        $lines = $this->cart->lines();
        if (! $lines) {
            return redirect()->route('cart.show');
        }
        $changes = $this->cart->revalidate();
        $delivery = self::deliveryEnabled();
        $zone = $delivery ? ShippingZone::where('is_active', true)->orderBy('sort_order')->first() : null;
        $fulfilment = $delivery ? 'delivery' : 'pickup';
        $quote = $this->quotes->build($this->cart->lines(), $request->user(), $request->user()?->email, zone: $zone, fulfilment: $fulfilment);
        // Price the first available payment method so its fee is included from the start.
        $method = old('payment_method', array_key_first($this->gateways->available($quote)));
        $quote = $this->quotes->build($this->cart->lines(), $request->user(), $request->user()?->email, zone: $zone, fulfilment: $fulfilment, paymentMethod: $method);

        return view('shop.checkout', [
            'seo' => Seo::page(__('shop.checkout.title')),
            'quote' => $quote,
            'changes' => $changes,
            'zones' => ShippingZone::where('is_active', true)->orderBy('sort_order')->get(),
            'branches' => self::pickupBranches(),
            'deliveryEnabled' => $delivery,
            'addresses' => $request->user()?->addresses ?? collect(),
            'methods' => $this->gateways->available($quote),
            'blocked' => $this->ratesBlocked(),
            'loyaltyBalance' => $request->user()?->loyaltyBalance() ?? 0,
        ]);
    }

    /** Live totals while the shopper edits the checkout form. */
    public function quote(Request $request)
    {
        $quote = $this->buildFrom($request);

        return response()->json($this->present($quote));
    }

    public function place(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email:rfc|max:191',
            'phone' => ['required', 'string', 'max:32', 'regex:/^[+0-9 ()-]{7,}$/'],
            'name' => 'required|string|max:120',
            'buyer_trn' => 'nullable|digits:15',
            'fulfilment' => ['required', Rule::in(self::deliveryEnabled() ? ['delivery', 'pickup'] : ['pickup'])],
            'zone_id' => 'required_if:fulfilment,delivery|nullable|exists:shipping_zones,id',
            'line1' => 'required_if:fulfilment,delivery|nullable|string|max:191',
            'line2' => 'nullable|string|max:191',
            'city' => 'required_if:fulfilment,delivery|nullable|string|max:64',
            'pickup_branch_id' => 'required_if:fulfilment,pickup|nullable|exists:branches,id',
            'payment_method' => ['required', Rule::in(['card', 'tabby', 'tamara', 'cod', 'giftcard'])],
            'expected_total' => 'required|numeric',
            'coupon' => 'nullable|string|max:32',
            'gift_card' => 'nullable|string|max:24',
            'loyalty_points' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:500',
            'save_address' => 'nullable|boolean',
        ]);

        if ($this->ratesBlocked()) {
            return back()->withInput()->withErrors(['checkout' => __('shop.checkout.rates_stale')]);
        }

        $this->cart->attachContact($data['email'], $data['phone']);
        $quote = $this->buildFrom($request);
        if ($quote->isEmpty()) {
            return redirect()->route('cart.show');
        }

        // Server-side re-validation: if the gold rate moved since the page was rendered, stop and show the new price.
        if (Money::toFils($data['expected_total']) !== $quote->grandTotal) {
            $this->cart->revalidate();

            return back()->withInput()->with('price_updated', __('shop.checkout.price_updated', ['total' => Money::format($quote->grandTotal)]));
        }
        if (! array_key_exists($data['payment_method'], $this->gateways->available($quote))) {
            return back()->withInput()->withErrors(['payment_method' => __('shop.checkout.method_unavailable')]);
        }

        $address = $data['fulfilment'] === 'delivery' ? [
            'name' => $data['name'], 'phone' => $data['phone'], 'line1' => $data['line1'], 'line2' => $data['line2'] ?? null,
            'city' => $data['city'], 'zone' => $quote->zone?->name, 'country' => 'AE',
        ] : null;

        try {
            $order = app(OrderService::class)->place($quote, $data + ['address' => $address], $request->user());
        } catch (OutOfStockException $e) {
            return back()->withInput()->withErrors(['checkout' => $e->getMessage()]);
        }

        if ($address && $request->user() && $request->boolean('save_address')) {
            $request->user()->addresses()->firstOrCreate(
                ['line1' => $address['line1'], 'city' => $address['city']],
                ['name' => $data['name'], 'phone' => $data['phone'], 'line2' => $address['line2'], 'zone_code' => $quote->zone?->code ?? 'dubai'],
            );
        }

        $this->cart->markConverted($order->id);
        $request->session()->push('orders', $order->number);

        if (in_array($order->payment_method, ['cod', 'giftcard'], true)) {
            return redirect()->route('checkout.complete', $order->number);
        }

        try {
            return redirect()->away($this->gateways->for($order->payment_method)->start($order));
        } catch (Throwable $e) {
            Log::error('Payment start failed', ['order' => $order->number, 'error' => $e->getMessage()]);

            return redirect()->route('order.show', $order->number)->withErrors(['payment' => __('shop.pay.start_failed')]);
        }
    }

    public function complete(Request $request, string $order)
    {
        $order = Order::with('items')->where('number', $order)->firstOrFail();
        abort_unless(OrderController::canView($request, $order), 403);

        return view('shop.complete', ['seo' => Seo::page(__('shop.checkout.thanks')), 'order' => $order]);
    }

    private function buildFrom(Request $request): Quote
    {
        $fulfilment = $request->input('fulfilment') === 'pickup' || ! self::deliveryEnabled() ? 'pickup' : 'delivery';

        return $this->quotes->build(
            $this->cart->lines(),
            $request->user(),
            $request->input('email') ?: $request->user()?->email,
            $request->input('coupon'),
            ShippingZone::where('is_active', true)->find($request->input('zone_id')),
            $fulfilment,
            (int) $request->input('loyalty_points', 0),
            $request->input('gift_card'),
            $request->input('payment_method'),
        );
    }

    private function present(Quote $q): array
    {
        $f = fn (int $fils) => Money::format($fils);

        return [
            'lines' => collect($q->lines)->map(fn ($l, $i) => [
                'name' => $l->product->localized('name'), 'qty' => $l->qty,
                'subtotal' => $f($l->subtotal()), 'discount' => $l->discount ? $f($l->discount) : null, 'total' => $f($q->lineTaxable($i)),
            ])->all(),
            'items_subtotal' => $f($q->itemsSubtotal),
            'discount' => $q->discountTotal ? $f($q->discountTotal) : null,
            'taxable' => $f($q->taxable),
            'vat' => $f($q->vatTotal()),
            'shipping' => $q->shipping ? $f($q->shipping) : __('shop.checkout.free'),
            'payment_fee' => $q->paymentFee ? $f($q->paymentFee) : null,
            'taxable_all' => $f($q->taxable + $q->shipping + $q->paymentFee),
            'grand_total' => $f($q->grandTotal),
            'grand_total_raw' => Money::toAed($q->grandTotal),
            'gift_card' => $q->giftCardAmount ? $f($q->giftCardAmount) : null,
            'loyalty' => $q->loyaltyDiscount ? ['points' => $q->loyaltyPoints, 'value' => $f($q->loyaltyDiscount)] : null,
            'amount_due' => $f($q->amountDue),
            'coupons' => $q->couponCodes(),
            'messages' => array_values(array_unique($q->messages)),
            'methods' => $this->gateways->available($q),
        ];
    }

    /** Home delivery can be paused from Settings → Payments; pickup then becomes the only option. */
    public static function deliveryEnabled(): bool
    {
        return Settings::bool('delivery_enabled');
    }

    public static function pickupBranches()
    {
        return Settings::bool('pickup_enabled') ? Branch::where('pickup_enabled', true)->get() : collect();
    }

    private function ratesBlocked(): bool
    {
        return Settings::bool('block_checkout_when_stale')
            && GoldCategory::where('is_active', true)->get()->contains(fn ($c) => $c->isStale());
    }
}
