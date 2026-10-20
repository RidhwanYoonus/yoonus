<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommission extends Model
{
    protected $guarded = ['id'];

    public const STATUSES = ['pending' => 'Pending (not delivered yet)', 'available' => 'Earned (ready to collect)', 'void' => 'Cancelled'];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2', 'rate_percent' => 'decimal:2', 'commission_amount' => 'decimal:2',
            'markup_amount' => 'decimal:2', 'amount' => 'decimal:2', 'available_at' => 'datetime',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
