<?php

namespace Database\Seeders;

use App\Models\GoldCategory;
use App\Models\ShippingZone;
use App\Models\VatSetting;
use Illuminate\Database\Seeder;

/** Karats, VAT and UAE/GCC shipping zones — needed on every install. */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        VatSetting::firstOrCreate(['is_default' => true], ['vat_percent' => 5.00]);

        $k24 = GoldCategory::firstOrCreate(['name' => '24K'], ['karat' => 24, 'purity_percent' => 99.90, 'sort_order' => 1]);
        foreach ([['22K', 22, 91.60, 2], ['21K', 21, 87.50, 3], ['18K', 18, 75.00, 4]] as [$name, $karat, $purity, $sort]) {
            GoldCategory::firstOrCreate(['name' => $name], [
                'karat' => $karat, 'purity_percent' => $purity, 'auto_derive' => true, 'base_category_id' => $k24->id, 'sort_order' => $sort,
            ]);
        }

        foreach ([
            ['dubai', 'Dubai', 'دبي', 0, null, 1],
            ['abu_dhabi', 'Abu Dhabi', 'أبوظبي', 25, 1000, 1],
            ['sharjah', 'Sharjah', 'الشارقة', 20, 1000, 1],
            ['other_emirates', 'Other Emirates', 'الإمارات الأخرى', 35, 1500, 2],
            ['gcc', 'GCC', 'دول الخليج', 120, 5000, 5],
        ] as $i => [$code, $name, $ar, $rate, $free, $eta]) {
            ShippingZone::firstOrCreate(['code' => $code], ['name' => $name, 'name_ar' => $ar, 'flat_rate' => $rate, 'free_above' => $free, 'eta_days' => $eta, 'sort_order' => $i]);
        }
    }
}
