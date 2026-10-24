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
        'shipped' => ['delivered', 'refunded'],   // pickup orders marked "shipped" before this pipeline existed
        'delivered' => ['refunded'],
        'cancelled' => [],
        'refunded' => [],
    ];

    protected $guarded = ['id'];

    protected $hidden = ['id_number', 'handover_otp_hash'];

    protected function casts(): array
    {
        $money = collect(['items_subtotal', 'discount_total', 'taxable_amount', 'vat_total', 'shipping_total', 'shipping_vat', 'payment_fee', 'payment_fee_vat', 'grand_total', 'gift_card_amount', 'amount_due'])
            ->mapWithKeys(fn ($c) => [$c => 'decimal:2'])->all();

        return $money + [
            'shipping_address' => 'array',
            'paid_at' => 'datetime',
            'payment_expires_at' => 'datetime',
            'affiliate_white_page' => 'boolean',
            'invoiced_at' => 'datetime',
            'conversion_sent' => 'boolean',
            'id_number' => 'encrypted',
            'id_expiry' => 'date',
            'tax_free_requested' => 'boolean',
            'handover_otp_sent_at' => 'datetime',
            'handover_verified_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function affiliate(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function affiliateCommissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
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

    public function hasIdDocument(): bool
    {
        return (bool) $this->id_type;
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

    /** Unpaid online order still inside its payment window. */
    /** Google Maps link to the delivery spot the customer shared (map pin or pasted link), or null. */
    public function deliveryMapUrl(): ?string
    {
        if (filled($this->delivery_lat ?? null) && filled($this->delivery_lng ?? null)) {
            return 'https://www.google.com/maps/search/?api=1&query='.((float) $this->delivery_lat).','.((float) $this->delivery_lng);
        }
        $link = is_array($this->shipping_address) ? ($this->shipping_address['map_link'] ?? null) : null;

        return filled($link) ? $link : null;
    }

    public function awaitingPayment(): bool
    {
        return $this->status === 'pending' && $this->payment_status === 'unpaid'
            && in_array($this->payment_method, ['card', 'applepay', 'googlepay', 'tabby', 'tamara'], true)
            && (! $this->payment_expires_at || $this->payment_expires_at->isFuture());
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /** How an affiliate order came in: their coupon code, their own (white) page, or their link. Null when not an affiliate order. */
    public function affiliateSource(): ?string
    {
        if (! $this->affiliate_id || ! $this->affiliate) {
            return null;
        }

        return in_array($this->affiliate->code, explode(',', (string) $this->coupon_code), true) ? 'Code' : ($this->affiliate_white_page ? 'Own page' : 'Link');
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
