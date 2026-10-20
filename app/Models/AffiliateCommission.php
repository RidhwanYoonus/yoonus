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

    /** How the line was worked out, e.g. "Making AED 300 × 10%" or "12.50 g × AED 2 per gram". */
    public function calculation(): string
    {
        $pct = rtrim(rtrim((string) $this->rate_percent, '0'), '.');

        return match ($this->basis) {
            'gram' => $this->base_amount.' g × AED '.$this->rate_percent.' per gram',
            'making' => 'Making AED '.$this->base_amount.' × '.$pct.'%',
            default => 'Item total AED '.$this->base_amount.' × '.$pct.'%',
        };
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
