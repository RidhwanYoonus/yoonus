<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUSES = ['pending', 'paid', 'packed', 'shipped', 'ready', 'delivered', 'cancelled', 'refunded'];

    /** Home delivery: Pending → Paid → Packed → Shipped → Delivered. */
    public const TRANSITIONS = [
        'pending' => ['paid', 'cancelled'],
        'paid' => ['packed', 'cancelled', 'refunded'],
        'packed' => ['shipped', 'refunded'],
        'shipped' => ['delivered', 'refunded'],
        'delivered' => ['refunded'],
        'cancelled' => [],
        'refunded' => [],
    ];

    /** Store pickup: Pending → Paid → Packed → Ready for pickup → Collected (stored as "delivered"). */
    public const PICKUP_TRANSITIONS = [
        'pending' => ['paid', 'cancelled'],
        'paid' => ['packed', 'cancelled', 'refunded'],
        'packed' => ['ready', 'refunded'],
        'ready' => ['delivered', 'cancelled', 'refunded'],
        'delivered' => ['refunded'],
        'cancelled' => [],
        'refunded' => [],
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        $money = collect(['items_subtotal', 'discount_total', 'taxable_amount', 'vat_total', 'shipping_total', 'shipping_vat', 'payment_fee', 'payment_fee_vat', 'grand_total', 'gift_card_amount', 'amount_due'])
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

    public function isPickup(): bool
    {
        return $this->fulfilment === 'pickup';
    }

    /** Next statuses allowed from the current one, for this order's fulfilment type. */
    public function allowedTransitions(): array
    {
        $next = ($this->isPickup() ? self::PICKUP_TRANSITIONS : self::TRANSITIONS)[$this->status] ?? [];
        // Cash on delivery is paid at the door, so it can be packed before it is paid.
        if ($this->payment_method === 'cod' && $this->status === 'pending') {
            array_splice($next, 1, 0, ['packed']);
        }

        return $next;
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /** Status name for staff and customers; "delivered" reads as "Collected" for pickup orders. */
    public function statusLabel(?string $status = null): string
    {
        $status ??= $this->status;

        return __('shop.status.'.($this->isPickup() && $status === 'delivered' ? 'collected' : $status));
    }

    /** The customer-facing progress steps for this order. */
    public function steps(): array
    {
        return $this->isPickup()
            ? ['pending', 'paid', 'packed', 'ready', 'delivered']
            : ['pending', 'paid', 'packed', 'shipped', 'delivered'];
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }
}
