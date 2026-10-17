<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceRecalcLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'price_recalc_log';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['old_price' => 'decimal:2', 'new_price' => 'decimal:2', 'rate_used' => 'decimal:2'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function rateChange(): BelongsTo
    {
        return $this->belongsTo(GoldRateHistory::class, 'gold_rate_history_id');
    }
}
