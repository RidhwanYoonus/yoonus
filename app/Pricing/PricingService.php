<?php

namespace App\Pricing;

use App\Models\Coupon;
use App\Models\GoldCategory;
use App\Models\PriceRecalcLog;
use App\Models\Product;
use App\Models\VatSetting;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;

/**
 * Server-side prices. Every read recomputes from the current gold rate; results are
 * cached for 60s under a version key that is bumped on every rate change, so a new
 * rate is visible immediately (no stale prices while the recalc job runs).
 */
class PricingService
{
    /** White-page mark-up for the current visitor, set by the affiliate middleware. */
    private Markup $markup;

    public function __construct(private readonly PriceCalculator $calculator)
    {
        $this->markup = Markup::none();
    }

    public function setMarkup(Markup $markup): void
    {
        $this->markup = $markup;
    }

    public function markup(): Markup
    {
        return $this->markup;
    }

    /** Price without any affiliate mark-up (what the shop itself charges). */
    public function base(Product $product): PriceBreakdown
    {
        $saved = $this->markup;
        $this->markup = Markup::none();
        try {
            return $this->compute($product);
        } finally {
            $this->markup = $saved;
        }
    }

    public function version(): int
    {
        return (int) Cache::get('pricing.version', 1);
    }

    public function bumpVersion(): void
    {
        Cache::forever('pricing.version', $this->version() + 1);
        Cache::forget('pricing.auto_coupons');
    }

    public function ttl(): int
    {
        return (int) config('shop.price_cache_seconds', 60);
    }

    /** @return array<int,int> gold_category_id => rate in fils */
    public function rates(): array
    {
        return Cache::remember('pricing.rates.v'.$this->version(), $this->ttl(), fn () => GoldCategory::query()
            ->pluck('rate_per_gram', 'id')->map(fn ($r) => Money::toFils($r))->all());
    }

    public function rateFor(?int $goldCategoryId): int
    {
        return $goldCategoryId ? ($this->rates()[$goldCategoryId] ?? 0) : 0;
    }

    public function vatBpFor(?int $goldCategoryId): int
    {
        $map = Cache::remember('pricing.vat.v'.$this->version(), $this->ttl(), function () {
            return VatSetting::query()->whereNotNull('gold_category_id')->pluck('vat_percent', 'gold_category_id')
                ->map(fn ($v) => Money::scaled($v, 2))->all()
                + ['default' => Money::scaled(VatSetting::where('is_default', true)->value('vat_percent') ?? Settings::get('default_vat_percent', 5), 2)];
        });

        return ($goldCategoryId ? ($map[$goldCategoryId] ?? null) : null) ?? $map['default'];
    }

    public function roundingMode(): RoundingMode
    {
        return RoundingMode::tryFrom((string) Settings::get('rounding_mode', 'fils')) ?? RoundingMode::Fils;
    }

    public function forProduct(Product $product, int $discount = 0): PriceBreakdown
    {
        if ($discount > 0) {
            return $this->compute($product, $discount);
        }
        // An affiliate's price-lock link opened by this visitor keeps the price frozen until it expires.
        if ($locked = \App\Affiliates\PriceLocks::lockedPrice($product->id)) {
            return $locked;
        }
        $key = sprintf('price.v%d.p%d.%s.m%s', $this->version(), $product->id, $product->updated_at?->timestamp, $this->markup->key());

        return PriceBreakdown::fromArray(Cache::remember($key, $this->ttl(), fn () => $this->compute($product)->raw()));
    }

    public function compute(Product $product, int $discount = 0): PriceBreakdown
    {
        return $this->calculator->calculate(
            PricingInput::fromProduct($product),
            $this->rateFor($product->gold_category_id),
            $this->vatBpFor($product->gold_category_id),
            $this->roundingMode(),
            $discount,
            $this->markup,
        );
    }

    /** Admin live preview: price arbitrary, unsaved form values. */
    public function preview(array $attributes, ?int $goldCategoryId, ?string $rateOverride = null): PriceBreakdown
    {
        $rate = $rateOverride !== null ? Money::toFils($rateOverride) : ($goldCategoryId ? (int) Money::toFils(GoldCategory::find($goldCategoryId)?->rate_per_gram) : 0);

        return $this->calculator->calculate(PricingInput::fromArray($attributes), $rate, $this->vatBpFor($goldCategoryId), $this->roundingMode());
    }

    /**
     * Storefront price after an auto-applied campaign coupon (e.g. "Eid Sale — 15% off 22K").
     * Only unconditional percentage/fixed coupons are reflected on product cards.
     */
    public function displayPriceFor(Product $product): PriceBreakdown
    {
        $base = $this->forProduct($product);
        $coupon = collect($this->autoCoupons())->first(fn (Coupon $c) => $c->covers($product));
        if (! $coupon) {
            return $base;
        }
        $discount = $coupon->type === 'percentage'
            ? Money::percentOf($base->taxable, Money::scaled($coupon->value, 2))
            : min($base->taxable, Money::toFils($coupon->value));

        return $this->forProduct($product, $discount);
    }

    /** @return array<Coupon> */
    public function autoCoupons(): array
    {
        return Cache::remember('pricing.auto_coupons', $this->ttl(), fn () => Coupon::query()
            ->currentlyValid()->where('auto_apply', true)
            ->whereIn('type', ['percentage', 'fixed'])
            ->whereNull('min_cart_value')->where('first_order_only', false)
            ->whereNull('issued_to_user_id')
            ->orderByDesc('value')->get()->all());
    }

    /** Persist the price cache columns and log the change for the audit trail. */
    public function refreshCachedPrice(Product $product, ?int $rateHistoryId = null, string $reason = 'rate_change'): PriceBreakdown
    {
        $price = $this->base($product);   // stored prices never include an affiliate mark-up
        $old = $product->cached_sales_price;
        $new = Money::toAed($price->total);

        $product->forceFill([
            'cached_gold_value' => Money::toAed($price->goldValue),
            'cached_making_fee' => Money::toAed($price->makingFee),
            'cached_subtotal' => Money::toAed($price->subtotal),
            'cached_vat' => Money::toAed($price->vat),
            'cached_sales_price' => $new,
            'rate_snapshot' => Money::toAed($price->rateFils),
            'price_calculated_at' => now(),
        ])->saveQuietly();

        if ($old === null || Money::toFils($old) !== $price->total) {
            PriceRecalcLog::create([
                'product_id' => $product->id,
                'gold_rate_history_id' => $rateHistoryId,
                'old_price' => $old,
                'new_price' => $new,
                'rate_used' => Money::toAed($price->rateFils),
                'reason' => $reason,
            ]);
        }

        return $price;
    }
}
