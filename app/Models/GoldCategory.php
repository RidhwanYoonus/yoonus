<?php

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoldCategory extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'karat' => 'decimal:2',
            'purity_percent' => 'decimal:2',
            'rate_per_gram' => 'decimal:2',
            'auto_derive' => 'boolean',
            'is_active' => 'boolean',
            'rate_updated_at' => 'datetime',
        ];
    }

    public function base(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_category_id');
    }

    public function derived(): HasMany
    {
        return $this->hasMany(self::class, 'base_category_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(GoldRateHistory::class)->latest('effective_from');
    }

    public function isStale(): bool
    {
        $hours = (int) Settings::get('rate_stale_hours', 24);

        return ! $this->rate_updated_at || $this->rate_updated_at->lt(now()->subHours($hours));
    }
}
