<?php

namespace App\Pricing;

use App\Models\Product;

/** The product attributes that drive price, in exact integer units. */
final class PricingInput
{
    public function __construct(
        public readonly int $weightMg,          // grams × 1000
        public readonly string $chargeType,     // PER_PIECE | PERCENTAGE
        public readonly int $chargeValue,       // fils (PER_PIECE) or basis points (PERCENTAGE)
        public readonly int $stoneFils,
        public readonly int $wastageBp,
        public readonly string $pricingType = 'GOLD_RATE',   // GOLD_RATE | FIXED
        public readonly int $fixedFils = 0,                 // FIXED: selling price incl. VAT
    ) {}

    public static function fromProduct(Product $p): self
    {
        return self::fromArray($p->only(['weight_grams', 'manufacturer_charge_type', 'manufacturer_charge_value', 'stone_value', 'wastage_percent', 'pricing_type', 'fixed_price']));
    }

    /** Accepts raw form/CSV values (used by the admin live preview and importer). */
    public static function fromArray(array $a): self
    {
        $type = strtoupper((string) ($a['manufacturer_charge_type'] ?? 'PERCENTAGE')) === 'PER_PIECE' ? 'PER_PIECE' : 'PERCENTAGE';

        return new self(
            weightMg: max(0, Money::scaled($a['weight_grams'] ?? 0, 3)),
            chargeType: $type,
            chargeValue: max(0, Money::scaled($a['manufacturer_charge_value'] ?? 0, 2)),
            stoneFils: max(0, Money::scaled($a['stone_value'] ?? 0, 2)),
            wastageBp: max(0, Money::scaled($a['wastage_percent'] ?? 0, 2)),
            pricingType: strtoupper((string) ($a['pricing_type'] ?? 'GOLD_RATE')) === 'FIXED' ? 'FIXED' : 'GOLD_RATE',
            fixedFils: max(0, Money::scaled($a['fixed_price'] ?? 0, 2)),
        );
    }
}
