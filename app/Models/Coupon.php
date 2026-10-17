<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_cart_value' => 'decimal:2',
            'rules' => 'array',
            'scope_ids' => 'array',
            'first_order_only' => 'boolean',
            'stackable' => 'boolean',
            'auto_apply' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (Coupon $c) => $c->code = strtoupper(trim($c->code)));
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function scopeCurrentlyValid(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function isWithinSchedule(): bool
    {
        return $this->is_active
            && (! $this->starts_at || $this->starts_at->lte(now()))
            && (! $this->ends_at || $this->ends_at->gt(now()));
    }

    /** Whether a product falls inside this coupon's scope. */
    public function covers(Product $product): bool
    {
        $ids = array_map('intval', $this->scope_ids ?? []);

        return match ($this->scope) {
            'category' => in_array((int) $product->category_id, $ids, true),
            'products' => in_array((int) $product->id, $ids, true),
            'gold_category' => in_array((int) $product->gold_category_id, $ids, true),
            default => true,
        };
    }
}
