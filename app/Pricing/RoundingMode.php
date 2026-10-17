<?php

namespace App\Pricing;

enum RoundingMode: string
{
    case Fils = 'fils';        // 2 decimals (default)
    case Quarter = 'quarter';  // nearest AED 0.25
    case Dirham = 'dirham';    // nearest AED 1

    public function step(): int
    {
        return match ($this) {
            self::Fils => 1,
            self::Quarter => 25,
            self::Dirham => 100,
        };
    }

    public function apply(int $fils): int
    {
        return Money::divRound($fils, $this->step()) * $this->step();
    }

    public function label(): string
    {
        return match ($this) {
            self::Fils => '2 decimals (AED 0.01)',
            self::Quarter => 'Nearest AED 0.25',
            self::Dirham => 'Nearest AED 1',
        };
    }
}
