<?php

namespace App\Models;

use App\Pricing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Affiliate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'making_commission_percent' => 'decimal:2',
            'commission_per_gram' => 'decimal:2',
            'commission_per_piece' => 'decimal:2',
            'fixed_commission_amount' => 'decimal:2',
            'fixed_commission_percent' => 'decimal:2',
            'making_markup_percent' => 'decimal:2', 'fixed_markup_percent' => 'decimal:2', 'markup_amount' => 'decimal:2',
            'max_making_markup_percent' => 'decimal:2', 'max_fixed_markup_percent' => 'decimal:2', 'max_markup_amount' => 'decimal:2',
            'white_page_enabled' => 'boolean',
            'is_active' => 'boolean',
            'coupon_discount_percent' => 'decimal:2',
            'can_lock_price' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // The affiliate's code also works as a coupon at checkout when a customer discount is set.
        static::saved(fn (Affiliate $a) => $a->syncCoupon());
    }

    public function syncCoupon(): void
    {
        try {
            $coupon = Coupon::where('affiliate_id', $this->id)->first();
            $active = ($this->is_active ?? true) && (float) $this->coupon_discount_percent > 0;   // new records: DB default is active
            if (! $coupon && ! $active) {
                return;
            }
            $taken = Coupon::where('code', $this->code)->where(fn ($q) => $q->whereNull('affiliate_id')->orWhere('affiliate_id', '!=', $this->id))->exists();
            if ($taken) {
                return;   // an ordinary coupon already uses this code
            }
            ($coupon ?? new Coupon)->forceFill([
                'affiliate_id' => $this->id, 'code' => $this->code, 'name' => 'Affiliate '.$this->code,
                'type' => 'percentage', 'value' => (float) $this->coupon_discount_percent, 'scope' => 'cart',
                'stackable' => false, 'auto_apply' => false, 'is_active' => $active,
            ])->save();
        } catch (\Throwable $e) {
            report($e);   // e.g. before the database update has run
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(AffiliateAdjustment::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }

    public function recruitedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'recruited_by_id');
    }

    public function recruits(): HasMany
    {
        return $this->hasMany(self::class, 'recruited_by_id');
    }

    public function joinLink(): string
    {
        return route('affiliate.apply', ['ref' => $this->code]);
    }

    /** Sales (excl. VAT) of this affiliate's orders in the month, not counting cancelled / unpaid ones. In fils. */
    public function monthlySales(?\Illuminate\Support\Carbon $month = null): int
    {
        $month ??= now();

        return Money::toFils((string) Order::where('affiliate_id', $this->id)
            ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->whereNotIn('status', ['cancelled', 'refunded'])->where('payment_status', '!=', 'expired')
            ->sum('taxable_amount'));
    }

    /**
     * Level from Settings → Affiliates (e.g. Silver / Gold / Platinum by monthly sales).
     *
     * @return array{name: ?string, extra_bp: int, next: ?array{name: string, needed: int}}
     */
    public function level(?int $salesFils = null): array
    {
        $sales = $salesFils ?? $this->monthlySales();
        $tiers = collect((array) \App\Support\Settings::get('affiliate_tiers', []))
            ->filter(fn ($t) => filled($t['name'] ?? null))
            ->map(fn ($t) => ['name' => $t['name'], 'min' => Money::toFils((string) ($t['min_sales'] ?? 0)), 'extra_bp' => Money::scaled($t['extra_percent'] ?? 0, 2)])
            ->sortBy('min')->values();
        $current = $tiers->last(fn ($t) => $sales >= $t['min']);
        $next = $tiers->first(fn ($t) => $t['min'] > $sales);

        return [
            'name' => $current['name'] ?? null,
            'extra_bp' => $current['extra_bp'] ?? 0,
            'next' => $next ? ['name' => $next['name'], 'needed' => $next['min'] - $sales] : null,
            'progress' => $next ? (int) round(100 * $sales / max(1, $next['min'])) : 100,
        ];
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(AffiliatePayout::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function name(): string
    {
        return $this->display_name ?: ($this->user?->name ?? $this->code);
    }

    /** Mark-up actually applied on the white page: the affiliate's choice, capped by the admin's maximums. */
    public function effectiveMarkup(): \App\Pricing\Markup
    {
        if (! $this->white_page_enabled || ! $this->is_active) {
            return \App\Pricing\Markup::none();
        }
        $cap = fn (string $value, string $max) => max(0, min(Money::scaled($this->{$value}, 2), Money::scaled($this->{$max}, 2)));
        if ($this->markup_mode === 'amount') {
            return new \App\Pricing\Markup(amountFils: $cap('markup_amount', 'max_markup_amount'));
        }

        return new \App\Pricing\Markup(makingBp: $cap('making_markup_percent', 'max_making_markup_percent'), fixedBp: $cap('fixed_markup_percent', 'max_fixed_markup_percent'));
    }

    /** Whole commission rule in one line, for the admin list and the affiliate's dashboard. */
    public function commissionSummary(): string
    {
        $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') ?: '0';
        if ($this->commission_mode === 'per_piece') {
            return __('shop.affiliate.all_per_piece', ['amount' => 'AED '.$n($this->commission_per_piece)]);
        }
        $fixed = $this->fixed_commission_type === 'per_piece'
            ? __('shop.affiliate.fixed_per_piece', ['amount' => 'AED '.$n($this->fixed_commission_amount)])
            : __('shop.affiliate.fixed_percent', ['pct' => $n($this->fixed_commission_percent)]);

        return __('shop.affiliate.by_type', ['gold' => $this->goldCommissionLabel(), 'fixed' => $fixed]);
    }

    /** e.g. "10% of making" or "AED 2 per gram" (by-weight items). */
    public function goldCommissionLabel(): string
    {
        $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') ?: '0';

        return $this->making_commission_type === 'per_gram'
            ? __('shop.affiliate.per_gram_label', ['amount' => 'AED '.$n($this->commission_per_gram)])
            : __('shop.affiliate.making_label', ['pct' => $n($this->making_commission_percent)]);
    }

    /** Short description, e.g. "10% of making · 5% on fixed-price items" or "AED 50 per piece". */
    public function markupSummary(): string
    {
        $m = $this->effectiveMarkup();
        $n = fn (int $v) => rtrim(rtrim(Money::toAed($v), '0'), '.');
        if ($m->amountFils > 0) {
            return __('shop.affiliate.summary_amount', ['amount' => Money::format($m->amountFils)]);
        }

        return __('shop.affiliate.summary_percent', ['making' => $n($m->makingBp), 'fixed' => $n($m->fixedBp)]);
    }

    /** All amounts in fils. */
    public function totals(): array
    {
        $sum = fn (string $status) => Money::toFils((string) $this->commissions()->where('status', $status)->sum('amount'))
            + rescue(fn () => Money::toFils((string) $this->adjustments()->where('status', $status)->sum('amount')), 0, false);
        $available = $sum('available');
        $collected = Money::toFils((string) $this->payouts()->sum('amount'));

        return [
            'pending' => $sum('pending'),
            'earned' => $available,
            'collected' => $collected,
            'balance' => $available - $collected,
        ];
    }

    public function referralLink(): string
    {
        return route('home', ['aff' => $this->code]);
    }

    public function whitePageLink(): string
    {
        return route('affiliate.page', $this->code);
    }
}
