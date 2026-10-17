<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GiftCard extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['initial_value' => 'decimal:2', 'balance' => 'decimal:2', 'expires_at' => 'datetime', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (GiftCard $g) {
            $g->code = strtoupper($g->code ?: 'GC-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4)));
            $g->balance ??= $g->initial_value;
        });
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(GiftCardTransaction::class)->latest('id');
    }

    public function isUsable(): bool
    {
        return $this->is_active && (float) $this->balance > 0 && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
