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

    public const DIAMOND_ORIGINS = ['natural' => 'Natural diamond', 'lab_grown' => 'Lab-grown diamond'];

    /** Carat / colour / clarity shown over the photo of diamond pieces: [[label, value], …]. */
    public function diamondSpecs(): array
    {
        return array_values(array_filter([
            filled($this->diamond_carat) ? [__('shop.certificate.carat_short'), rtrim(rtrim((string) $this->diamond_carat, '0'), '.').' ct'] : null,
            filled($this->diamond_color) ? [__('shop.certificate.color'), $this->diamond_color] : null,
            filled($this->diamond_clarity) ? [__('shop.certificate.clarity'), $this->diamond_clarity] : null,
        ]));
    }

    /** YouTube video id from any YouTube link (watch, youtu.be, shorts, embed), or null. */
    public function youtubeId(): ?string
    {
        return preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', (string) $this->video_url, $m) ? $m[1] : null;
    }

    /** ['type' => 'file'|'youtube'|'link', 'url' => …] or null when the piece has no video. */
    public function video(): ?array
    {
        if (filled($this->video_path)) {
            return ['type' => 'file', 'url' => \Illuminate\Support\Facades\Storage::disk('public')->url($this->video_path)];
        }
        if ($id = $this->youtubeId()) {
            return ['type' => 'youtube', 'url' => 'https://www.youtube-nocookie.com/embed/'.$id.'?rel=0&playsinline=1', 'thumb' => 'https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg'];
        }
        if (filled($this->video_url) && preg_match('~^https://~', $this->video_url)) {
            return ['type' => 'link', 'url' => $this->video_url];
        }

        return null;
    }

    public const AFFILIATE_COMMISSION_TYPES = ['per_piece' => 'AED per piece', 'making_percent' => '% of the making charge', 'price_percent' => '% of the item price'];

    /** This piece's own affiliate commission (replaces every affiliate's rate), or null to use theirs. */
    public function affiliateCommission(): ?array
    {
        $type = $this->affiliate_commission_type ?? null;

        return isset(self::AFFILIATE_COMMISSION_TYPES[$type]) && $this->affiliate_commission_value !== null && $this->affiliate_commission_value !== ''
            ? ['type' => $type, 'value' => (string) $this->affiliate_commission_value] : null;
    }

    public const GOLD_COLOURS = ['yellow' => 'Yellow gold', 'white' => 'White gold', 'rose' => 'Rose gold', 'two_tone' => 'Two-tone gold'];

    /** Sizes the customer chooses from (ring / bangle / chain length…), [] when the piece has one size. */
    public function sizeList(): array
    {
        return array_values(array_filter(array_map(fn ($s) => trim((string) $s), (array) ($this->sizes ?? [])), 'strlen'));
    }

    /** Gold colours the customer chooses from (keys of GOLD_COLOURS), [] when there is no choice. */
    public function colourList(): array
    {
        return array_values(array_intersect(array_keys(self::GOLD_COLOURS), (array) ($this->gold_colours ?? [])));
    }

    public function needsOptions(): bool
    {
        return $this->sizeList() !== [] || $this->colourList() !== [];
    }

    public static function colourLabel(string $key): string
    {
        return isset(self::GOLD_COLOURS[$key]) ? __('shop.product.colour_'.$key) : $key;
    }

    /** ['size' => '16', 'colour' => 'rose'] → "Size 16 · Rose gold". */
    public static function optionsText(array $options): string
    {
        return implode(' · ', array_filter([
            filled($options['size'] ?? null) ? __('shop.product.size').' '.$options['size'] : null,
            filled($options['colour'] ?? null) ? self::colourLabel($options['colour']) : null,
        ]));
    }

    /** A diamond piece: diamond type chosen, carat entered, or "Diamond" among its stones. */
    public function hasDiamond(): bool
    {
        return filled($this->diamond_origin) || filled($this->diamond_carat) || str_contains(strtolower((string) $this->stone_type), 'diamond');
    }

    /** "Natural diamond" / "Lab-grown diamond" in the shop's language, or null. */
    public function diamondOriginLabel(): ?string
    {
        return isset(self::DIAMOND_ORIGINS[$this->diamond_origin]) ? __('shop.certificate.origin_'.$this->diamond_origin) : null;
    }

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'sizes' => 'array',
            'photo_originals' => 'array',
            'gold_colours' => 'array',
            'weight_grams' => 'decimal:3',
            'manufacturer_charge_value' => 'decimal:2',
            'stone_value' => 'decimal:2',
            'diamond_carat' => 'decimal:3',
            'wastage_percent' => 'decimal:2',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'video_call_enabled' => 'boolean',
            'hidden_after_sale' => 'boolean',
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

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** True once "Update database now" added the created_by / updated_by columns. */
    public static function tracksAuthors(): bool
    {
        static $has;

        return $has ??= rescue(fn () => \Illuminate\Support\Facades\Schema::hasColumn('products', 'updated_by'), false, false);
    }

    public function certificateLab(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CertificateLab::class);
    }

    /** True when the piece has diamond / certificate details to show. */
    public function hasCertificate(): bool
    {
        return filled($this->certificate_number) || filled($this->diamond_origin) || filled($this->certificate_file) || $this->certificate_lab_id
            || filled($this->diamond_carat) || filled($this->diamond_color) || filled($this->diamond_clarity);
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

    /** Shown in the shop: switched on, and not a sold piece whose order has already been handed over. */
    /** @return array<string> stones of this piece, e.g. ['Diamond', 'Emerald'] */
    public function stones(): array
    {
        return self::splitStones($this->stone_type);
    }

    /** Stones in the visitor's language, e.g. "Diamond · Emerald". */
    public function stoneLabel(string $glue = ' · '): ?string
    {
        return $this->stones() ? implode($glue, array_map(fn ($s) => StoneType::label($s), $this->stones())) : null;
    }

    public static function splitStones(?string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $value)))));
    }

    public static function joinStones(array|string|null $stones): ?string
    {
        $list = is_array($stones) ? array_values(array_unique(array_filter(array_map('trim', $stones)))) : self::splitStones($stones);

        return $list ? implode(', ', $list) : null;
    }

    /** Pieces holding this stone (also when it is one of several). */
    public function scopeWithStone(Builder $q, string $stone): Builder
    {
        return $q->where(fn ($q) => $q->where('stone_type', $stone)
            ->orWhere('stone_type', 'like', $stone.', %')
            ->orWhere('stone_type', 'like', '%, '.$stone)
            ->orWhere('stone_type', 'like', '%, '.$stone.', %'));
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->where('hidden_after_sale', false);
    }

    protected static function booted(): void
    {
        // Restocking a sold-and-delivered piece puts it back in the shop.
        static::saving(function (self $p) {
            if ($p->hidden_after_sale && $p->stock_qty > 0) {
                $p->hidden_after_sale = false;
            }
            // Who added / last changed it (admin and staff only; imports and the system leave it as is).
            $by = auth()->user();
            if ($by && ($by->is_admin || $by->is_staff) && self::tracksAuthors()) {
                if (! $p->exists) {
                    $p->created_by = $by->id;
                }
                if ($p->isDirty() || ! $p->exists) {
                    $p->updated_by = $by->id;
                }
            }
            // Neat capitals in the English name and description (Settings → Store & tax → Tidy product text).
            if (\App\Support\TextStyle::enabled()) {
                if ($p->isDirty('name')) {
                    $p->name = \App\Support\TextStyle::name($p->name);
                }
                if ($p->isDirty('description')) {
                    $p->description = \App\Support\TextStyle::description($p->description);
                }
            }
            // Empty SEO boxes are written from the product's details (Settings → Store & tax → Write SEO automatically).
            if (\App\Support\Settings::bool('seo_auto_fill', true) && filled($p->name)) {
                rescue(fn () => \App\Support\SeoText::fillBlanks($p, save: false), null, false);
            }
        });
    }

    public function isListed(): bool
    {
        return $this->is_active && ! $this->hidden_after_sale;
    }

    /** Orders that still hold this piece (not yet delivered, collected, cancelled or refunded). */
    public function hasOpenOrders(?int $exceptOrderId = null): bool
    {
        return OrderItem::where('product_id', $this->id)
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', ['delivered', 'cancelled', 'refunded'])
                ->when($exceptOrderId, fn ($q) => $q->whereKeyNot($exceptOrderId)))
            ->exists();
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

    public function videoCallAvailable(): bool
    {
        return $this->video_call_enabled && Settings::bool('video_call_enabled');
    }

    /**
     * Making-charge badge for cards, e.g. ['text' => 'Only 1% making', 'highlight' => true].
     * Null for fixed-price and per-piece items or when the badge is switched off.
     */
    public function makingBadge(): ?array
    {
        // Only gold-weight (percentage) making is advertised; fixed-price and per-piece items show none.
        if ($this->isFixedPrice() || $this->manufacturer_charge_type === self::PER_PIECE || ! Settings::bool('making_badge')
            || app(\App\Pricing\PricingService::class)->markup()->isActive()) {   // white page: the % would not match the price
            return null;
        }
        $value = (float) $this->manufacturer_charge_value;
        $pct = rtrim(rtrim(number_format($value, 2), '0'), '.');
        $low = $value <= (float) Settings::get('making_badge_highlight_below', 5);

        return ['text' => __($value <= 0 ? 'shop.product.making_zero' : ($low ? 'shop.product.making_low' : 'shop.product.making_pct'), ['pct' => $pct]), 'highlight' => $low];
    }

    /** "Only 3% making" stamped across the photo of an in-stock piece below the limit (Settings), or null. */
    public function makingWatermark(): ?string
    {
        if (! Settings::bool('making_watermark') || ! $this->inStock() || $this->isFixedPrice() || $this->manufacturer_charge_type === self::PER_PIECE
            || app(\App\Pricing\PricingService::class)->markup()->isActive()) {
            return null;
        }
        $value = (float) $this->manufacturer_charge_value;
        if ($value >= (float) Settings::get('making_watermark_below', 5)) {
            return null;
        }

        return __($value <= 0 ? 'shop.product.making_zero' : 'shop.product.making_low', ['pct' => rtrim(rtrim(number_format($value, 2), '0'), '.')]);
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
