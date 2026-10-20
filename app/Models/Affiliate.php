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
            'fixed_commission_percent' => 'decimal:2',
            'max_markup_percent' => 'decimal:2',
            'markup_percent' => 'decimal:2',
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

    /** Mark-up actually applied on the white page: the affiliate's choice, capped by the admin's maximum. */
    public function effectiveMarkupBp(): int
    {
        if (! $this->white_page_enabled || ! $this->is_active) {
            return 0;
        }

        return max(0, min(Money::scaled($this->markup_percent, 2), Money::scaled($this->max_markup_percent, 2)));
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
