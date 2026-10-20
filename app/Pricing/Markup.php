<?php

namespace App\Pricing;

/**
 * An affiliate's white-page mark-up, in exact integer units:
 *   percent mode → by-weight items: makingBp of the making charge; fixed-price items: fixedBp of the price
 *   amount mode  → amountFils added to every piece
 */
final class Markup
{
    public function __construct(
        public readonly int $makingBp = 0,
        public readonly int $fixedBp = 0,
        public readonly int $amountFils = 0,
    ) {}

    public static function none(): self
    {
        return new self;
    }

    public function isActive(): bool
    {
        return $this->makingBp > 0 || $this->fixedBp > 0 || $this->amountFils > 0;
    }

    /** Extra fils on a by-weight item's making charge. */
    public function onMaking(int $makingFils): int
    {
        return Money::percentOf($makingFils, $this->makingBp) + $this->amountFils;
    }

    /** Extra fils on a fixed-price item. */
    public function onFixed(int $priceFils): int
    {
        return Money::percentOf($priceFils, $this->fixedBp) + $this->amountFils;
    }

    public function key(): string
    {
        return $this->makingBp.'-'.$this->fixedBp.'-'.$this->amountFils;
    }
}
