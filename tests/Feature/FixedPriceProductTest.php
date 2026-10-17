<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\GoldCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Pricing\Money;
use App\Shop\CartLine;
use App\Shop\QuoteBuilder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FixedPriceProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['24K' => '218.18', '22K' => '200.00', '18K' => '163.64']);
    }

    private function fixed(array $attrs = []): Product
    {
        return Product::create($attrs + [
            'name' => 'Silver Watch', 'sku' => 'W-1', 'slug' => 'silver-watch', 'pricing_type' => 'FIXED',
            'fixed_price' => '1050.00', 'gold_category_id' => null, 'weight_grams' => 0, 'stock_qty' => 5,
            'category_id' => Category::create(['name' => 'Watches', 'slug' => 'watches'])->id,
        ]);
    }

    public function test_fixed_price_splits_vat_and_ignores_gold_rate(): void
    {
        $p = $this->fixed();
        $price = $p->price();
        $this->assertSame('1050.00', Money::toAed($price->total));
        $this->assertSame('1000.00', Money::toAed($price->taxable));
        $this->assertSame('50.00', Money::toAed($price->vat));
        $this->assertSame('1050.00', $p->fresh()->cached_sales_price);

        $this->setGoldRates(['24K' => '260', '22K' => '240']);
        $this->assertSame('1050.00', Money::toAed($p->fresh()->price()->total));
    }

    public function test_fixed_price_with_a_karat_still_ignores_the_rate(): void
    {
        $p = $this->fixed(['gold_category_id' => GoldCategory::where('name', '18K')->value('id'), 'weight_grams' => 3.5]);
        $this->setGoldRates(['18K' => '190']);

        $this->assertSame('1050.00', $p->fresh()->cached_sales_price);
        $this->assertSame('18K · 3.5 g', $p->fresh()->specLine());
    }

    public function test_coupon_discounts_before_vat(): void
    {
        Coupon::create(['code' => 'TEN', 'name' => 'Ten', 'type' => 'percentage', 'value' => 10, 'scope' => 'cart']);
        $p = $this->fixed();
        $q = app(QuoteBuilder::class)->build([new CartLine($p, 1, $p->price())], null, 'a@example.com', 'TEN', ShippingZone::where('code', 'dubai')->first());

        $this->assertSame(10000, $q->discountTotal);           // 10% of AED 1,000 (excl. VAT)
        $this->assertSame(90000, $q->taxable);
        $this->assertSame(4500, $q->vat);
        $this->assertSame('945.00', Money::toAed($q->grandTotal));
    }

    public function test_product_page_and_checkout_work_without_a_karat(): void
    {
        $this->fixed();
        $this->get('/product/silver-watch')->assertOk()->assertSee('AED 1,050.00')->assertSee('Price (excl. VAT)')->assertDontSee('Gold rate AED');
        $this->get('/product/silver-watch?lang=ar')->assertOk();
        $this->get('/shop')->assertOk()->assertSee('Watches');
        $this->get('/feed/google.xml')->assertOk()->assertSee('<g:price>1050.00 AED</g:price>', false);

        $this->post('/cart/add/silver-watch');
        $this->post('/checkout', [
            'email' => 'g@example.com', 'phone' => '+971501234567', 'name' => 'G', 'fulfilment' => 'delivery',
            'zone_id' => ShippingZone::where('code', 'dubai')->value('id'), 'line1' => 'A', 'city' => 'B',
            'payment_method' => 'cod', 'expected_total' => '1050.00',
        ])->assertRedirect();

        $item = Order::sole()->items->first();
        $this->assertSame('Fixed price', $item->karat);
        $this->assertSame('1050.00', $item->line_total);
        $this->assertSame('50.00', $item->vat_amount);
    }
}
