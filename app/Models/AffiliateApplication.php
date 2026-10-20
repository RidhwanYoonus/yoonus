<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Someone asked to become an affiliate (often through an existing affiliate's join link). */
class AffiliateApplication extends Model
{
    protected $guarded = ['id'];

    public const STATUSES = ['new' => 'New', 'approved' => 'Approved', 'rejected' => 'Rejected'];

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'referred_by_id');
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
