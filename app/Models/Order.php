<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUSES = ['pending', 'paid', 'packed', 'shipped', 'delivered', 'cancelled', 'refunded'];

    /** Allowed forward moves in the fulfilment pipeline. */
    public const TRANSITIONS = [
        'pending' => ['paid', 'cancelled'],
        'paid' => ['packed', 'cancelled', 'refunded'],
        'packed' => ['shipped', 'refunded'],
        'shipped' => ['delivered', 'refunded'],
        'delivered' => ['refunded'],
        'cancelled' => [],
        'refunded' => [],
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        $money = collect(['items_subtotal', 'discount_total', 'taxable_amount', 'vat_total', 'shipping_total', 'shipping_vat', 'grand_total', 'gift_card_amount', 'amount_due'])
            ->mapWithKeys(fn ($c) => [$c => 'decimal:2'])->all();

        return $money + [
            'shipping_address' => 'array',
            'paid_at' => 'datetime',
            'invoiced_at' => 'datetime',
            'conversion_sent' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }

    public function pickupBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'pickup_branch_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest('id');
    }

    public function priceOverrides(): HasMany
    {
        return $this->hasMany(OrderPriceOverride::class)->latest('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }
}
