<?php

namespace App\Pricing;

/**
 * An affiliate's white-page mark-up, in exact integer units:
 *   by-weight items  → makingBp of the making charge and/or perGramFils × grams
 *   fixed-price items → fixedBp of the price and/or fixedPieceFils per piece
 *   stones            → stoneBp of the stone value (by-weight items with stones)
 *   every piece       → amountFils ("same amount per piece for every item")
 */
final class Markup
{
    public function __construct(
        public readonly int $makingBp = 0,
        public readonly int $fixedBp = 0,
        public readonly int $amountFils = 0,
        public readonly int $perGramFils = 0,
        public readonly int $fixedPieceFils = 0,
        public readonly int $stoneBp = 0,
    ) {}

    public static function none(): self
    {
        return new self;
    }

    public function isActive(): bool
    {
        return $this->makingBp > 0 || $this->fixedBp > 0 || $this->amountFils > 0 || $this->perGramFils > 0 || $this->fixedPieceFils > 0 || $this->stoneBp > 0;
    }

    /** Extra fils on a by-weight item's making charge (weight in milligrams). */
    public function onMaking(int $makingFils, int $weightMg = 0): int
    {
        return Money::percentOf($makingFils, $this->makingBp) + Money::divRound($weightMg * $this->perGramFils, 1000) + $this->amountFils;
    }

    /** Extra fils on the stone value of a by-weight item. */
    public function onStone(int $stoneFils): int
    {
        return Money::percentOf($stoneFils, $this->stoneBp);
    }

    /** Extra fils on a fixed-price item. */
    public function onFixed(int $priceFils): int
    {
        return Money::percentOf($priceFils, $this->fixedBp) + $this->fixedPieceFils + $this->amountFils;
    }

    public function key(): string
    {
        return implode('-', [$this->makingBp, $this->fixedBp, $this->amountFils, $this->perGramFils, $this->fixedPieceFils, $this->stoneBp]);
    }
}
