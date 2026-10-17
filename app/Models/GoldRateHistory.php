<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/** Append-only audit trail of every rate change. */
class GoldRateHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'gold_rate_history';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'previous_rate' => 'decimal:2',
            'rate_per_gram' => 'decimal:2',
            'input_value' => 'decimal:2',
            'effective_from' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Gold rate history is append-only.'));
    }

    public function goldCategory(): BelongsTo
    {
        return $this->belongsTo(GoldCategory::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function priceChanges(): HasMany
    {
        return $this->hasMany(PriceRecalcLog::class);
    }
}
