<?php

namespace App\Shop;

use App\Models\GiftCard;
use App\Models\ShippingZone;
use App\Pricing\Money;
use App\Promotions\CouponResult;

/** A fully priced checkout: lines, discounts, VAT, shipping, tenders. All fils. */
final class Quote
{
    /** @var array<int,CouponResult> */
    public array $applied = [];

    /** @var array<string> */
    public array $messages = [];

    public int $loyaltyPoints = 0;

    public int $loyaltyDiscount = 0;

    public int $itemsSubtotal = 0;

    public int $discountTotal = 0;

    public int $taxable = 0;

    public int $vat = 0;

    public int $shipping = 0;

    public int $shippingVat = 0;

    public bool $freeShipping = false;

    public ?string $paymentMethod = null;

    /** Card / transaction fee (excl. VAT) and its VAT. */
    public int $paymentFee = 0;

    public int $paymentFeeVat = 0;

    /** What the customer pays before any payment fee (used for BNPL limits and the fee itself). */
    public int $amountBeforeFee = 0;

    public int $grandTotal = 0;

    public int $giftCardAmount = 0;

    public int $amountDue = 0;

    public ?GiftCard $giftCard = null;

    /** @var array<int,int> line index => vat fils */
    public array $lineVat = [];

    /** @param array<int,CartLine> $lines */
    public function __construct(
        public readonly array $lines,
        public readonly ?ShippingZone $zone,
        public readonly string $fulfilment,
    ) {}

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function couponCodes(): array
    {
        return array_map(fn (CouponResult $r) => $r->coupon->code, $this->applied);
    }

    public function lineTaxable(int $i): int
    {
        return $this->lines[$i]->subtotal() - $this->lines[$i]->discount;
    }

    public function lineTotal(int $i): int
    {
        return $this->lineTaxable($i) + ($this->lineVat[$i] ?? 0);
    }

    /** Every VAT component: goods, delivery and payment fee. */
    public function vatTotal(): int
    {
        return $this->vat + $this->shippingVat + $this->paymentFeeVat;
    }

    public function itemsGross(): int
    {
        return array_sum(array_map(fn (CartLine $l) => $l->gross(), $this->lines));
    }

    public function toArray(): array
    {
        return collect(['itemsSubtotal', 'discountTotal', 'taxable', 'vat', 'shipping', 'shippingVat', 'paymentFee', 'paymentFeeVat', 'grandTotal', 'giftCardAmount', 'amountDue', 'loyaltyDiscount'])
            ->mapWithKeys(fn ($k) => [$k => Money::toAed($this->{$k})])->all();
    }
}
