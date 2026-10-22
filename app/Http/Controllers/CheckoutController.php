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
use App\Support\IdDocument;
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
        $offered = $this->gateways->available($quote);
        // Card is the default (Apple Pay is listed first but only shows on Apple devices).
        $method = old('payment_method', isset($offered['card']) ? 'card' : array_key_first($offered));
        $quote = $this->quotes->build($this->cart->lines(), $request->user(), $request->user()?->email, zone: $zone, fulfilment: $fulfilment, paymentMethod: $method);
        // Back from a failed submit (e.g. a missing ID photo): price with what was entered, so an applied code keeps its discount.
        if ($old = $request->session()->getOldInput()) {
            $quote = $this->buildFrom($request->duplicate($old));
        }

        return view('shop.checkout', [
            'seo' => Seo::page(__('shop.checkout.title')),
            'quote' => $quote,
            'changes' => $changes,
            'zones' => ShippingZone::where('is_active', true)->orderBy('sort_order')->get(),
            'branches' => self::pickupBranches(),
            'deliveryEnabled' => $delivery,
            'idMode' => (string) Settings::get('id_verification', 'delivery'),
            'phoneVerification' => \App\Shop\PhoneVerification::enabled(),
            'verifiedPhones' => array_values(array_filter([...$request->session()->get('verified_phones', []),
                $request->user()?->phone_verified_at ? \App\Shop\PhoneVerification::normalise($request->user()->phone) : null])),
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
            'payment_method' => ['required', Rule::in(['applepay', 'googlepay', 'card', 'tabby', 'tamara', 'cod', 'giftcard'])],
            'expected_total' => 'required|numeric',
            'accept_terms' => [\App\Support\Settings::bool('require_terms_acceptance') ? 'accepted' : 'nullable'],
            'coupon' => 'nullable|string|max:32',
            'gift_card' => 'nullable|string|max:24',
            'loyalty_points' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:500',
            'save_address' => 'nullable|boolean',
            'tax_free' => 'nullable|boolean',
        ] + $this->idRules($request), [
            'id_front.uploaded' => __('shop.id.upload_failed', ['max' => ini_get('upload_max_filesize')]),
            'id_back.uploaded' => __('shop.id.upload_failed', ['max' => ini_get('upload_max_filesize')]),
        ]);

        if ($this->ratesBlocked()) {
            return back()->withInput()->withErrors(['checkout' => __('shop.checkout.rates_stale')]);
        }
        if (\App\Shop\PhoneVerification::enabled() && ! \App\Shop\PhoneVerification::isVerified($request, $data['phone'])) {
            return back()->withInput()->withErrors(['phone_verification' => __('shop.verify.required')]);
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
        if (self::taxFree($request) && Money::toAed($quote->grandTotal) < \App\Support\TaxFree::minimum()) {
            return back()->withInput()->withErrors(['tax_free' => __('shop.tax_free.too_small', ['min' => 'AED '.number_format(\App\Support\TaxFree::minimum())])]);
        }

        $address = $data['fulfilment'] === 'delivery' ? [
            'name' => $data['name'], 'phone' => $data['phone'], 'line1' => $data['line1'], 'line2' => $data['line2'] ?? null,
            'city' => $data['city'], 'zone' => $quote->zone?->name, 'country' => 'AE',
        ] : null;

        // Ordering again from a bag whose earlier order was never paid: cancel that one first, so its
        // stock, coupon and gold price are released instead of being held twice.
        if ($previous = $this->cart->waitingOrder()) {
            app(OrderService::class)->expireUnpaid($previous, 'Not paid — the customer placed a new order from the same cart instead. Stock returned.');
        }

        $id = $this->idDocument($request, $quote->fulfilment);
        try {
            $affiliate = app(\App\Affiliates\AffiliateContext::class);
            $order = app(OrderService::class)->place($quote, $data + ['address' => $address, 'id' => $id, 'tax_free' => self::taxFree($request),
                'affiliate' => $affiliate->preview ? null : $affiliate->affiliate, 'affiliate_white' => $affiliate->whitePage], $request->user());
        } catch (OutOfStockException $e) {
            if ($id && ! $id['saved']) {
                IdDocument::delete($id['front'], $id['back']);
            }

            return back()->withInput()->withErrors(['checkout' => $e->getMessage()]);
        }
        \App\Affiliates\PriceLocks::used($order);
        if ($request->boolean('accept_terms')) {
            // Proof for card disputes: when, from where, and which policy version the customer agreed to.
            $order->forceFill(['terms_accepted_at' => now(), 'terms_accepted_ip' => $request->ip(), 'terms_version' => \App\Support\Policies::UPDATED])->save();
        }
        if ($id && ! $id['saved'] && $request->user() && $request->boolean('save_id')) {
            $request->user()->forceFill([
                'id_type' => $id['type'], 'id_number' => $id['number'], 'id_last4' => $id['last4'], 'id_expiry' => $id['expiry'],
                'id_front_path' => $id['front'], 'id_back_path' => $id['back'], 'id_saved_at' => now(),
                ...(\Illuminate\Support\Facades\Schema::hasColumn('users', 'id_country') ? ['id_country' => $id['country'] ?? null] : []),
            ])->save();
        }

        if ($address && $request->user() && $request->boolean('save_address')) {
            $request->user()->addresses()->firstOrCreate(
                ['line1' => $address['line1'], 'city' => $address['city']],
                ['name' => $data['name'], 'phone' => $data['phone'], 'line2' => $address['line2'], 'zone_code' => $quote->zone?->code ?? 'dubai'],
            );
        }

        in_array($order->payment_method, ['cod', 'giftcard'], true) || $order->fresh()->isPaid()
            ? $this->cart->markConverted($order->id)
            : $this->cart->holdForPayment($order->id);
        $request->session()->push('orders', $order->number);
        $request->attributes->set('activity_order', $order->number);   // for the Activity log

        if (in_array($order->payment_method, ['cod', 'giftcard'], true)) {
            return redirect()->route('checkout.complete', $order->number);
        }

        try {
            return redirect()->away($this->gateways->for($order->payment_method)->start($order));
        } catch (Throwable $e) {
            return self::paymentStartFailed($request, $order, $e);
        }
    }

    /** Customers see a short message; admins/staff also see the provider's exact reason, to fix setup. */
    public static function paymentStartFailed(Request $request, Order $order, Throwable $e)
    {
        Log::error('Payment start failed', ['order' => $order->number, 'error' => $e->getMessage()]);
        $message = __('shop.pay.start_failed');
        if ($request->user()?->canAccessPanel(\Filament\Facades\Filament::getPanel('admin'))) {
            $message .= ' [Admin only — reason: '.mb_substr($e->getMessage(), 0, 400).']';
        }

        return redirect()->route('order.show', $order->number)->withErrors(['payment' => $message]);
    }

    /** "Pay now" on the order page: try the card payment again for an unpaid order. */
    public function retry(Request $request, string $order)
    {
        $order = Order::where('number', $order)->firstOrFail();
        abort_unless(OrderController::canView($request, $order), 403);
        if (app(OrderService::class)->expireUnpaid($order)) {
            return OrderController::backToBag($order);
        }
        if (! $order->awaitingPayment()) {
            return redirect()->route('order.show', $order->number);
        }

        try {
            return redirect()->away($this->gateways->for($order->payment_method)->start($order));
        } catch (Throwable $e) {
            return self::paymentStartFailed($request, $order, $e);
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
        // An affiliate's coupon code is a "code sale": normal shop prices, never their white-page mark-up.
        if (($code = strtoupper(trim((string) $request->input('coupon')))) !== ''
            && rescue(fn () => \App\Models\Coupon::where('code', $code)->whereNotNull('affiliate_id')->exists(), false, false)) {
            app(\App\Pricing\PricingService::class)->setMarkup(\App\Pricing\Markup::none());
            app(\App\Affiliates\AffiliateContext::class)->whitePage = false;
        }

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

    /** Proof-of-ID rules: only when this order needs it and the customer isn't reusing a saved, valid ID. */
    private function idRules(Request $request): array
    {
        $fulfilment = $request->input('fulfilment') === 'pickup' || ! self::deliveryEnabled() ? 'pickup' : 'delivery';
        $taxFree = self::taxFree($request);
        if ((! IdDocument::requiredFor($fulfilment) && ! $taxFree) || $this->usesSavedId($request)) {
            return [];
        }
        $file = ['file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:15360'];

        return [
            // Tourist tax-free: only a passport is accepted, with its nationality (not UAE).
            'id_type' => ['required', $taxFree ? Rule::in(['passport']) : Rule::in(IdDocument::TYPES)],
            'id_country' => [$taxFree ? 'required' : 'nullable', Rule::in(array_keys(\App\Support\TaxFree::countries()))],
            'id_number' => ['required', 'string', 'max:32', IdDocument::numberRule($request->input('id_type'))],
            'id_expiry' => ['nullable', 'date', 'after:today'],
            // Settings → Delivery & pickup → ID photos: optional (default), required, or not asked.
            'id_front' => [self::idPhotos() === 'required' ? 'required' : 'nullable', ...$file],
            'id_back' => [self::idPhotos() === 'required' ? 'required' : 'nullable', ...$file],
            'save_id' => 'nullable|boolean',
        ];
    }

    /** optional | required | hidden */
    public static function idPhotos(): string
    {
        return in_array($v = (string) \App\Support\Settings::get('id_photos', 'optional'), ['optional', 'required', 'hidden'], true) ? $v : 'optional';
    }

    private function usesSavedId(Request $request): bool
    {
        $u = $request->user();

        return $request->boolean('use_saved_id') && (bool) $u?->hasValidSavedId()
            && (! self::taxFree($request) || ($u->id_type === 'passport' && filled($u->id_country)));
    }

    /** The shopper asked for a tourist tax-free invoice (and the shop offers it). */
    public static function taxFree(Request $request): bool
    {
        return \App\Support\TaxFree::enabled() && $request->boolean('tax_free');
    }

    /** Stores the uploaded ID privately (or reuses the saved one) and returns the order snapshot. */
    private function idDocument(Request $request, string $fulfilment): ?array
    {
        if (! IdDocument::requiredFor($fulfilment) && ! self::taxFree($request)) {
            return null;
        }
        if ($this->usesSavedId($request)) {
            $u = $request->user();

            return ['type' => $u->id_type, 'number' => $u->id_number, 'last4' => $u->id_last4, 'expiry' => $u->id_expiry,
                'front' => $u->id_front_path, 'back' => $u->id_back_path, 'country' => $u->id_country, 'saved' => true];
        }
        $number = IdDocument::normalise($request->input('id_type'), $request->input('id_number'));

        return ['type' => $request->input('id_type'), 'number' => $number, 'last4' => IdDocument::last4($number), 'expiry' => $request->input('id_expiry'),
            'front' => self::idPhotos() !== 'hidden' && $request->hasFile('id_front') ? IdDocument::store($request->file('id_front')) : null,
            'back' => self::idPhotos() !== 'hidden' && $request->hasFile('id_back') ? IdDocument::store($request->file('id_back')) : null,
            'country' => $request->input('id_country') ?: null, 'saved' => false];
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
