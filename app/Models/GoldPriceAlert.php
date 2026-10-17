<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoldPriceAlert extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['target_rate' => 'decimal:2', 'last_notified_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function goldCategory(): BelongsTo
    {
        return $this->belongsTo(GoldCategory::class);
    }
}
