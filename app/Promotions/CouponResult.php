<?php

namespace App\Promotions;

use App\Models\Coupon;

final class CouponResult
{
    /** @param array<int,int> $lineDiscounts line index => fils */
    public function __construct(
        public readonly Coupon $coupon,
        public readonly array $lineDiscounts = [],
        public readonly bool $freeShipping = false,
        public readonly ?string $error = null,
    ) {}

    public static function invalid(Coupon $coupon, string $error): self
    {
        return new self($coupon, error: $error);
    }

    public function ok(): bool
    {
        return $this->error === null;
    }

    public function total(): int
    {
        return array_sum($this->lineDiscounts);
    }

    /** Ranking value when choosing between non-stackable offers. */
    public function value(int $shippingFils): int
    {
        return $this->total() + ($this->freeShipping ? $shippingFils : 0);
    }
}
