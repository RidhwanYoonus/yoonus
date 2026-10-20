<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Affiliate money not tied to one order line: target bonus, in-store sale credit, manual correction. */
class AffiliateAdjustment extends Model
{
    protected $guarded = ['id'];

    public const TYPES = ['bonus' => 'Target bonus', 'store_sale' => 'In-store sale', 'manual' => 'Manual adjustment'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'details' => 'array'];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AffiliateCampaign::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
