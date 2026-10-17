<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Review extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['photos' => 'array', 'verified_purchase' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photoUrls(): array
    {
        return array_map(fn ($p) => Storage::disk('public')->url($p), $this->photos ?? []);
    }
}
