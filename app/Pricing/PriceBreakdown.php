<?php

namespace App\Pricing;

/** Immutable result of a price calculation; every amount is in fils. */
final class PriceBreakdown
{
    public function __construct(
        public readonly int $rateFils,
        public readonly int $goldValue,
        public readonly int $makingFee,
        public readonly int $stoneValue,
        public readonly int $wastage,
        public readonly int $rounding,
        public readonly int $subtotal,       // before discount, excl. VAT (incl. rounding)
        public readonly int $discount,
        public readonly int $taxable,        // subtotal − discount: VAT base (FTA)
        public readonly int $vatBp,
        public readonly int $vat,
        public readonly int $total,          // sales price incl. VAT
        public readonly int $originalTotal,  // total had no discount been applied
    ) {}

    public function hasDiscount(): bool
    {
        return $this->discount > 0 && $this->originalTotal > $this->total;
    }

    public function savings(): int
    {
        return max(0, $this->originalTotal - $this->total);
    }

    public function savingsPercent(): int
    {
        return $this->originalTotal > 0 ? (int) floor($this->savings() * 100 / $this->originalTotal) : 0;
    }

    public function vatPercent(): string
    {
        return Money::toAed($this->vatBp);
    }

    public function toArray(): array
    {
        return [
            'rate' => Money::toAed($this->rateFils),
            'gold_value' => Money::toAed($this->goldValue),
            'making_fee' => Money::toAed($this->makingFee),
            'stone_value' => Money::toAed($this->stoneValue),
            'wastage' => Money::toAed($this->wastage),
            'rounding' => Money::toAed($this->rounding),
            'subtotal' => Money::toAed($this->subtotal),
            'discount' => Money::toAed($this->discount),
            'taxable' => Money::toAed($this->taxable),
            'vat_percent' => $this->vatPercent(),
            'vat' => Money::toAed($this->vat),
            'total' => Money::toAed($this->total),
            'original_total' => Money::toAed($this->originalTotal),
        ];
    }

    public static function fromArray(array $a): self
    {
        return new self(...array_map('intval', $a));
    }

    public function raw(): array
    {
        return get_object_vars($this);
    }
}
