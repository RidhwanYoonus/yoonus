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
            'fixed_commission_percent' => 'decimal:2',
            'making_markup_percent' => 'decimal:2', 'fixed_markup_percent' => 'decimal:2', 'markup_amount' => 'decimal:2',
            'max_making_markup_percent' => 'decimal:2', 'max_fixed_markup_percent' => 'decimal:2', 'max_markup_amount' => 'decimal:2',
            'white_page_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        $sum = fn (string $status) => Money::toFils((string) $this->commissions()->where('status', $status)->sum('amount'));
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
