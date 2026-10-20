<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A customer asked to visit the store (often sent by an affiliate) to see a piece. */
class StoreVisit extends Model
{
    protected $guarded = ['id'];

    public const STATUSES = ['new' => 'New', 'confirmed' => 'Confirmed', 'visited' => 'Visited', 'cancelled' => 'Cancelled'];

    protected function casts(): array
    {
        return ['preferred_at' => 'datetime'];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
