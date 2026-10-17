<?php

namespace App\Shop;

use App\Models\Product;
use App\Pricing\PriceBreakdown;

final class CartLine
{
    public int $discount = 0;

    /** @var array<string,int> coupon code => fils */
    public array $discountsByCode = [];

    public function __construct(
        public readonly Product $product,
        public readonly int $qty,
        public readonly PriceBreakdown $unit,
        public readonly ?int $priceSeen = null,
    ) {}

    public function subtotal(): int
    {
        return $this->unit->taxable * $this->qty;
    }

    public function gross(): int
    {
        return $this->unit->total * $this->qty;
    }

    /** Lowest taxable value allowed for this line: the gold cost. */
    public function floor(): int
    {
        return $this->unit->goldValue * $this->qty;
    }

    public function maxDiscount(): int
    {
        return max(0, $this->subtotal() - $this->floor() - $this->discount);
    }

    public function priceChanged(): bool
    {
        return $this->priceSeen !== null && $this->priceSeen !== $this->unit->total;
    }
}
