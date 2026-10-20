<?php

namespace App\Models;

use App\Pricing\PriceBreakdown;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A product price frozen by an affiliate for a customer, valid until expires_at. */
class AffiliatePriceLock extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price' => 'array', 'expires_at' => 'datetime'];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isValid(): bool
    {
        return $this->expires_at->isFuture() && $this->affiliate?->is_active && $this->affiliate?->can_lock_price;
    }

    public function breakdown(): PriceBreakdown
    {
        return PriceBreakdown::fromArray($this->price);
    }

    public function link(): string
    {
        return route('affiliate.lock', $this->token);
    }
}
