<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * booster: commission × multiplier on orders placed in the period (optionally one category).
 * target:  a one-off bonus when the affiliate reaches X pieces or AED X of delivered sales in the period.
 */
class AffiliateCampaign extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'affiliate_ids' => 'array', 'is_active' => 'boolean',
            'multiplier' => 'decimal:2', 'target_value' => 'decimal:2', 'bonus_amount' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeRunning(Builder $q, $at = null): Builder
    {
        $at ??= now();

        return $q->where('is_active', true)->where('starts_at', '<=', $at)->where('ends_at', '>=', $at);
    }

    public function appliesTo(Affiliate $affiliate): bool
    {
        $ids = array_map('intval', (array) ($this->affiliate_ids ?? []));

        return $ids === [] || in_array($affiliate->id, $ids, true);
    }
}
