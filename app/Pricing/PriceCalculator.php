<?php

namespace App\Pricing;

/**
 * Pure pricing formula (spec §2):
 *
 *   gold_value       = weight × rate_per_gram
 *   manufacturer_fee = PER_PIECE: fixed  |  PERCENTAGE: gold_value × value%
 *   wastage          = gold_value × wastage%
 *   subtotal         = gold + making + stone + wastage
 *   taxable          = subtotal − discount   (discount never below gold value)
 *   vat              = taxable × vat%
 *   sales_price      = round(taxable + vat)
 *
 * When rounding to AED 0.25 / AED 1 the rounding difference is folded into the
 * taxable amount, so VAT stays exactly vat% of the taxable value on the invoice.
 */
final class PriceCalculator
{
    public function calculate(PricingInput $in, int $rateFils, int $vatBp, RoundingMode $rounding = RoundingMode::Fils, int $discount = 0): PriceBreakdown
    {
        if ($in->pricingType === 'FIXED') {
            return $this->fixed($in, $vatBp, $discount);
        }

        $gold = Money::divRound($in->weightMg * $rateFils, 1000);
        $making = $in->chargeType === 'PER_PIECE' ? $in->chargeValue : Money::percentOf($gold, $in->chargeValue);
        $wastage = Money::percentOf($gold, $in->wastageBp);
        $base = $gold + $making + $in->stoneFils + $wastage;

        // Undiscounted reference price (for strikethrough display).
        [$origTaxable, $origVat, $origRounding] = $this->finalise($base, $vatBp, $rounding, $gold);

        // Hard floor: a discount can never take the taxable value below gold cost.
        $discount = max(0, min($discount, $origTaxable - $gold));
        if ($discount === 0) {
            return new PriceBreakdown($rateFils, $gold, $making, $in->stoneFils, $wastage, $origRounding,
                $origTaxable, 0, $origTaxable, $vatBp, $origVat, $origTaxable + $origVat, $origTaxable + $origVat);
        }

        [$taxable, $vat] = $this->finalise($origTaxable - $discount, $vatBp, $rounding, $gold);
        $discount = $origTaxable - $taxable;

        return new PriceBreakdown($rateFils, $gold, $making, $in->stoneFils, $wastage, $origRounding,
            $origTaxable, $discount, $taxable, $vatBp, $vat, $taxable + $vat, $origTaxable + $origVat);
    }

    /**
     * Fixed-price product: the admin enters the selling price before VAT (like every price in
     * the shop); VAT is added at checkout. Discounts come off the pre-VAT amount.
     */
    private function fixed(PricingInput $in, int $vatBp, int $discount): PriceBreakdown
    {
        $taxable = $in->fixedFils;
        $vat = Money::percentOf($taxable, $vatBp);
        $total = $taxable + $vat;

        $discount = max(0, min($discount, $taxable));
        if ($discount === 0) {
            return new PriceBreakdown(0, 0, 0, 0, 0, 0, $taxable, 0, $taxable, $vatBp, $vat, $total, $total);
        }
        $newTaxable = $taxable - $discount;
        $newVat = Money::percentOf($newTaxable, $vatBp);

        return new PriceBreakdown(0, 0, 0, 0, 0, 0, $taxable, $discount, $newTaxable, $vatBp, $newVat, $newTaxable + $newVat, $total);
    }

    /** @return array{0:int,1:int,2:int} taxable, vat, rounding adjustment */
    public function finalise(int $taxable, int $vatBp, RoundingMode $rounding, int $floor = 0): array
    {
        $vat = Money::percentOf($taxable, $vatBp);
        if ($rounding === RoundingMode::Fils) {
            return [$taxable, $vat, 0];
        }

        $target = $rounding->apply($taxable + $vat);
        $newTaxable = Money::divRound($target * 10000, 10000 + $vatBp);
        if ($newTaxable < $floor) {
            $target += $rounding->step();
            $newTaxable = Money::divRound($target * 10000, 10000 + $vatBp);
        }

        return [$newTaxable, $target - $newTaxable, $newTaxable - $taxable];
    }
}
