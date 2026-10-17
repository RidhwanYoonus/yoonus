<?php

namespace App\Models;

use App\Models\Concerns\Localized;
use App\Pricing\PriceBreakdown;
use App\Pricing\PricingService;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use Localized;

    public const PER_PIECE = 'PER_PIECE';

    public const PERCENTAGE = 'PERCENTAGE';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'weight_grams' => 'decimal:3',
            'manufacturer_charge_value' => 'decimal:2',
            'stone_value' => 'decimal:2',
            'wastage_percent' => 'decimal:2',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'fixed_price' => 'decimal:2',
            'cached_gold_value' => 'decimal:2',
            'cached_making_fee' => 'decimal:2',
            'cached_subtotal' => 'decimal:2',
            'cached_vat' => 'decimal:2',
            'cached_sales_price' => 'decimal:2',
            'rate_snapshot' => 'decimal:2',
            'price_calculated_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function goldCategory(): BelongsTo
    {
        return $this->belongsTo(GoldCategory::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('status', 'approved')->latest();
    }

    public function recalcLog(): HasMany
    {
        return $this->hasMany(PriceRecalcLog::class)->latest('id');
    }

    public function branchStock(): HasMany
    {
        return $this->hasMany(BranchStock::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Live, server-side price (cached 60s, invalidated on any rate change). */
    public function price(): PriceBreakdown
    {
        return app(PricingService::class)->forProduct($this);
    }

    /** Price after any auto-applied campaign coupon that covers this product. */
    public function displayPrice(): PriceBreakdown
    {
        return app(PricingService::class)->displayPriceFor($this);
    }

    public function isFixedPrice(): bool
    {
        return $this->pricing_type === 'FIXED';
    }

    /** Short spec line for cards and the bag, e.g. "22K · 5 g", "18K", or the category for non-gold items. */
    public function specLine(): string
    {
        $weight = (float) $this->weight_grams > 0 ? rtrim(rtrim((string) $this->weight_grams, '0'), '.').' '.__('shop.product.g') : null;
        $parts = array_filter([$this->goldCategory?->name, $weight]);

        return $parts ? implode(' · ', $parts) : (string) $this->category?->localized('name');
    }

    /**
     * Making-charge badge for cards, e.g. ['text' => 'Only 1% making', 'highlight' => true].
     * Null for fixed-price items or when the badge is switched off.
     */
    public function makingBadge(): ?array
    {
        // Only gold-weight (percentage) making is advertised; fixed-price and per-piece items show none.
        if ($this->isFixedPrice() || $this->manufacturer_charge_type === self::PER_PIECE || ! Settings::bool('making_badge')) {
            return null;
        }
        $value = (float) $this->manufacturer_charge_value;
        $pct = rtrim(rtrim(number_format($value, 2), '0'), '.');
        $low = $value <= (float) Settings::get('making_badge_highlight_below', 5);

        return ['text' => __($value <= 0 ? 'shop.product.making_zero' : ($low ? 'shop.product.making_low' : 'shop.product.making_pct'), ['pct' => $pct]), 'highlight' => $low];
    }

    public function inStock(): bool
    {
        return $this->stock_qty > 0;
    }

    public function isLowStock(): bool
    {
        return $this->stock_qty <= $this->low_stock_threshold;
    }

    public function imageUrls(): array
    {
        return collect($this->images ?? [])
            ->map(fn ($p) => str_starts_with($p, 'http') || str_starts_with($p, '/') ? $p : Storage::disk('public')->url($p))
            ->values()->all();
    }

    public function primaryImage(): string
    {
        return $this->imageUrls()[0] ?? route('placeholder', ['product' => $this->slug]);
    }

    public function url(?string $locale = null): string
    {
        return route('product.show', ['product' => $this->slug, 'lang' => $locale]);
    }
}
