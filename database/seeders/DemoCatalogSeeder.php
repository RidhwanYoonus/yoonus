<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\GoldCategory;
use App\Models\Product;
use App\Models\User;
use App\Pricing\GoldRateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Sample catalogue, branches, a live campaign and the EID15 coupon for demos and QA. */
class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Shop Admin', 'password' => 'ChangeMe-2026', 'phone' => '+971500000000',
        ])->forceFill(['is_admin' => true])->save();

        // Each karat is set by hand. 22K at AED 200.00/g reproduces the spec's worked example.
        foreach (['24K' => '218.18', '22K' => '200.00', '21K' => '190.91', '18K' => '163.64'] as $karat => $rate) {
            app(GoldRateService::class)->updateRate(GoldCategory::where('name', $karat)->firstOrFail(), $rate, 'gram', null, 'seed');
        }

        $cats = collect([
            ['Rings', 'خواتم'], ['Necklaces', 'قلائد'], ['Bangles', 'أساور'], ['Earrings', 'أقراط'], ['Pendants', 'معلقات'], ['Sets', 'أطقم'],
        ])->mapWithKeys(fn ($c, $i) => [$c[0] => Category::firstOrCreate(['slug' => Str::slug($c[0])], ['name' => $c[0], 'name_ar' => $c[1], 'sort_order' => $i])]);

        $karat = GoldCategory::pluck('id', 'name');
        $products = [
            ['Classic Band Ring', 'خاتم كلاسيكي', 'Rings', '22K', '5.000', 'PERCENTAGE', 10, 0, null, 0, true],
            ['Diamond Solitaire Ring', 'خاتم سوليتير ألماس', 'Rings', '18K', '3.200', 'PER_PIECE', 350, 2400, 'Diamond', 0, true],
            ['Arabesque Filigree Ring', 'خاتم مخرّم عربي', 'Rings', '21K', '6.450', 'PERCENTAGE', 14, 0, null, 2, false],
            ['Rope Chain Necklace', 'سلسلة حبل', 'Necklaces', '22K', '18.750', 'PERCENTAGE', 8, 0, null, 0, true],
            ['Emerald Drop Necklace', 'قلادة زمرد', 'Necklaces', '18K', '9.800', 'PER_PIECE', 650, 1850, 'Emerald', 0, true],
            ['Kada Bangle', 'سوار كادا', 'Bangles', '22K', '24.300', 'PERCENTAGE', 9, 0, null, 1.5, true],
            ['Twisted Bangle Pair', 'زوج أساور ملتوية', 'Bangles', '21K', '31.600', 'PERCENTAGE', 7.5, 0, null, 0, false],
            ['Pearl Stud Earrings', 'أقراط لؤلؤ', 'Earrings', '18K', '2.400', 'PER_PIECE', 180, 420, 'Pearl', 0, true],
            ['Jhumka Earrings', 'أقراط جومكا', 'Earrings', '22K', '11.200', 'PERCENTAGE', 15, 0, null, 0, false],
            ['Hamsa Pendant', 'معلقة خمسة', 'Pendants', '21K', '4.100', 'PERCENTAGE', 12, 0, null, 0, false],
            ['Coin Pendant 24K', 'معلقة عملة 24 قيراط', 'Pendants', '24K', '8.000', 'PER_PIECE', 60, 0, null, 0, true],
            ['Bridal Set', 'طقم عروس', 'Sets', '22K', '78.500', 'PERCENTAGE', 11, 0, null, 2, true],
        ];

        foreach ($products as $i => [$name, $ar, $cat, $k, $w, $type, $charge, $stone, $stoneType, $wastage, $featured]) {
            Product::updateOrCreate(['sku' => sprintf('JW-%04d', $i + 1)], [
                'name' => $name, 'name_ar' => $ar, 'slug' => Str::slug($name),
                'description' => "{$name} crafted in {$k} gold. Hallmarked and supplied with a certificate of authenticity.",
                'description_ar' => "{$ar} مصنوع من ذهب {$k}. مدموغ ومرفق بشهادة أصالة.",
                'category_id' => $cats[$cat]->id, 'gold_category_id' => $karat[$k],
                'weight_grams' => $w, 'manufacturer_charge_type' => $type, 'manufacturer_charge_value' => $charge,
                'stone_value' => $stone, 'stone_type' => $stoneType, 'wastage_percent' => $wastage,
                'stock_qty' => $i === 2 ? 1 : 5 + $i, 'is_featured' => $featured, 'hallmark_ref' => 'AE-'.(1000 + $i),
            ]);
        }

        $branch = Branch::firstOrCreate(['name' => 'Gold Souk, Deira'], ['name_ar' => 'سوق الذهب، ديرة', 'emirate' => 'Dubai', 'address' => 'Gold Souk, Deira, Dubai', 'phone' => '+971 4 000 0000', 'opening_hours' => '10:00–22:00']);
        Product::pluck('id')->each(fn ($id) => BranchStock::firstOrCreate(['branch_id' => $branch->id, 'product_id' => $id], ['qty' => 2]));

        $campaign = Campaign::firstOrCreate(['slug' => 'eid-sale'], [
            'name' => 'Eid Sale', 'name_ar' => 'تخفيضات العيد',
            'headline' => '15% off the making value of all 22K gold', 'headline_ar' => 'خصم 15% على جميع قطع الذهب عيار 22',
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(10),
        ]);

        Coupon::firstOrCreate(['code' => 'EID15'], [
            'name' => 'Eid 15% off', 'campaign_id' => $campaign->id, 'type' => 'percentage', 'value' => 15, 'scope' => 'cart',
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(10),
        ]);
        Coupon::firstOrCreate(['code' => 'SPEND5000'], [
            'name' => 'Spend 5,000 get 10% off', 'type' => 'tiered', 'rules' => [['min' => 5000, 'percent' => 10], ['min' => 10000, 'percent' => 12]],
            'scope' => 'cart', 'stackable' => false,
        ]);
        Coupon::firstOrCreate(['code' => 'FREESHIP'], ['name' => 'Free delivery', 'type' => 'free_shipping', 'scope' => 'cart', 'stackable' => true]);
    }
}
