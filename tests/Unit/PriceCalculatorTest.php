<?php

namespace Tests\Unit;

use App\Pricing\Money;
use App\Pricing\PriceCalculator;
use App\Pricing\PricingInput;
use App\Pricing\RoundingMode;
use PHPUnit\Framework\TestCase;

class PriceCalculatorTest extends TestCase
{
    private function input(array $a): PricingInput
    {
        return PricingInput::fromArray($a + ['weight_grams' => '5.000', 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => '10']);
    }

    public function test_spec_worked_example_is_exact(): void
    {
        $p = (new PriceCalculator)->calculate($this->input([]), 20000, 500);

        $this->assertSame(100000, $p->goldValue);
        $this->assertSame(10000, $p->makingFee);
        $this->assertSame(110000, $p->subtotal);
        $this->assertSame(5500, $p->vat);
        $this->assertSame('1155.00', Money::toAed($p->total));
    }

    public function test_per_piece_stone_and_wastage(): void
    {
        $p = (new PriceCalculator)->calculate($this->input([
            'weight_grams' => '3.2', 'manufacturer_charge_type' => 'PER_PIECE', 'manufacturer_charge_value' => '350',
            'stone_value' => '2400', 'wastage_percent' => '2.5',
        ]), 16364, 500);

        $this->assertSame(52365, $p->goldValue);          // 3.2 × 163.64 = 523.648 → 523.65
        $this->assertSame(35000, $p->makingFee);
        $this->assertSame(1309, $p->wastage);              // 2.5% of 523.65
        $this->assertSame(52365 + 35000 + 240000 + 1309, $p->subtotal);
        $this->assertSame($p->taxable + $p->vat, $p->total);
    }

    public function test_rounding_modes_keep_vat_consistent(): void
    {
        $in = $this->input(['weight_grams' => '4.37']);
        foreach ([RoundingMode::Quarter, RoundingMode::Dirham] as $mode) {
            $p = (new PriceCalculator)->calculate($in, 21083, 500, $mode);
            $this->assertSame(0, $p->total % $mode->step(), $mode->value);
            $this->assertSame($p->taxable + $p->vat, $p->total);
            $this->assertEqualsWithDelta(Money::percentOf($p->taxable, 500), $p->vat, 1);
        }
    }

    public function test_discount_applies_before_vat_and_never_below_gold(): void
    {
        $calc = new PriceCalculator;
        $p = $calc->calculate($this->input(['stone_value' => '1000']), 20000, 500, RoundingMode::Fils, 31500);
        $this->assertSame(178500, $p->taxable);
        $this->assertSame(8925, $p->vat);
        $this->assertSame('1874.25', Money::toAed($p->total));
        $this->assertSame(115500 + 105000, $p->originalTotal);

        $floored = $calc->calculate($this->input([]), 20000, 500, RoundingMode::Fils, 999999);
        $this->assertSame($floored->goldValue, $floored->taxable);
    }

    public function test_money_parsing(): void
    {
        $this->assertSame(500, Money::scaled('0.5', 3));
        $this->assertSame(29, Money::toFils('0.29'));
        $this->assertSame(116, Money::toFils('1.155'));
        $this->assertSame('-3.05', Money::toAed(-305));
    }
}
