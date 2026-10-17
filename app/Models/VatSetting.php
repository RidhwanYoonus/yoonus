<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VatSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['vat_percent' => 'decimal:2', 'is_default' => 'boolean'];
    }

    public function goldCategory(): BelongsTo
    {
        return $this->belongsTo(GoldCategory::class);
    }
}
