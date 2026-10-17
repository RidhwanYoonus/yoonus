<?php

namespace Tests\Feature;

use App\Console\Commands\SendAbandonedCartReminders;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\GoldCategory;
use App\Models\GoldRateHistory;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\PriceRecalcLog;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use App\Payments\GatewayManager;
use App\Pricing\GoldRateService;
use App\Pricing\Money;
use App\Shop\CartLine;
use App\Shop\InvoiceService;
use App\Shop\OrderService;
use App\Shop\QuoteBuilder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/** One test per item of spec §11 (Acceptance Criteria), plus guard rails. */
class AcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private GoldCategory $k24;

    private GoldCategory $k22;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->k24 = GoldCategory::where('name', '24K')->first();
        $this->k22 = GoldCategory::where('name', '22K')->first();
        $this->setGoldRates(['24K' => '218.18', '22K' => '200.00', '18K' => '163.64']);
    }

    private function product(array $attrs = []): Product
    {
        $name = $attrs['name'] ?? 'Ring '.Str::random(5);

        return Product::create($attrs + [
            'name' => $name, 'name_ar' => 'خاتم', 'sku' => Str::upper(Str::random(8)), 'slug' => Str::slug($name),
            'gold_category_id' => $this->k22->id, 'weight_grams' => '5.000',
            'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 10,
        ]);
    }

    private function quote(array $products, ?string $coupon = null, ?string $email = 'buyer@example.com')
    {
        $lines = array_map(fn (Product $p) => new CartLine($p, 1, $p->fresh()->price()), $products);

        return app(QuoteBuilder::class)->build($lines, null, $email, $coupon, ShippingZone::where('code', 'dubai')->first());
    }

    private function placeOrder(array $products, string $method = 'cod', ?string $coupon = null): Order
    {
        return app(OrderService::class)->place($this->quote($products, $coupon), [
            'email' => 'buyer@example.com', 'phone' => '+971501234567', 'name' => 'Test Buyer', 'payment_method' => $method,
            'address' => ['line1' => 'Villa 1', 'city' => 'Jumeirah', 'zone' => 'Dubai'],
        ], null);
    }

    /** 1. Changing the 22K rate updates every 22K product price (immediately, and the cache via the job). */
    public function test_01_rate_change_updates_all_22k_products(): void
    {
        $products = collect(range(1, 3))->map(fn () => $this->product());
        $other = $this->product(['gold_category_id' => GoldCategory::where('name', '18K')->value('id')]);

        app(GoldRateService::class)->updateRate($this->k22->fresh(), '210');

        foreach ($products as $p) {
            $p->refresh();
            $this->assertSame('1212.75', $p->cached_sales_price);                  // 5 × 210 × 1.10 × 1.05
            $this->assertSame('1212.75', Money::toAed($p->price()->total));        // live read
        }
        $this->assertNotEquals('1212.75', $other->fresh()->cached_sales_price);
    }

    /** 2. 5 g @ 22K AED 200/g, 10% making, 5% VAT → AED 1,155.00. */
    public function test_02_worked_example(): void
    {
        $this->assertSame('200.00', $this->k22->fresh()->rate_per_gram);
        $this->assertSame('1155.00', Money::toAed($this->product()->price()->total));
    }

    /** 3. Switching making charge % → per piece recalculates instantly. */
    public function test_03_switch_charge_type(): void
    {
        $p = $this->product();
        $this->assertSame('1155.00', $p->fresh()->cached_sales_price);
        $this->travel(1)->seconds();
        $p->update(['manufacturer_charge_type' => 'PER_PIECE', 'manufacturer_charge_value' => 250]);

        $this->assertSame('1312.50', Money::toAed($p->fresh()->price()->total));   // (1000 + 250) × 1.05
        $this->assertSame('1312.50', $p->fresh()->cached_sales_price);
    }

    /** 4. Past orders keep their price snapshot after a rate change. */
    public function test_04_order_snapshot_survives_rate_change(): void
    {
        $order = $this->placeOrder([$this->product()]);
        $this->setGoldRates(['22K' => '220']);

        $item = $order->fresh()->items->first();
        $this->assertSame('200.00', $item->gold_rate_snapshot);
        $this->assertSame('1155.00', $item->line_total);
        $this->assertSame('1155.00', $order->fresh()->grand_total);
    }

    /** 5. EID15 = 15% off the subtotal; VAT is charged on the discounted amount. */
    public function test_05_eid15_discount_before_vat(): void
    {
        Coupon::create(['code' => 'EID15', 'name' => 'Eid', 'type' => 'percentage', 'value' => 15, 'scope' => 'cart']);
        $q = $this->quote([$this->product(['stone_value' => 1000])], 'eid15');

        $this->assertSame(['EID15'], $q->couponCodes());
        $this->assertSame(210000, $q->itemsSubtotal);
        $this->assertSame(31500, $q->discountTotal);
        $this->assertSame(178500, $q->taxable);
        $this->assertSame(8925, $q->vat);
        $this->assertSame('1874.25', Money::toAed($q->grandTotal));
    }

    public function test_05b_coupon_never_drops_below_gold_value(): void
    {
        Coupon::create(['code' => 'BIG', 'name' => 'Big', 'type' => 'percentage', 'value' => 50, 'scope' => 'cart']);
        $q = $this->quote([$this->product()], 'BIG');

        $this->assertSame(100000, $q->taxable);     // = gold value; only the making charge was discountable
        $this->assertSame(105000, $q->grandTotal);
    }

    /** 6. Google Merchant feed shows the new price straight after a rate change. */
    public function test_06_feed_follows_rate(): void
    {
        $p = $this->product();
        $this->get('/feed/google.xml')->assertOk()->assertSee('<g:price>1155.00 AED</g:price>', false);

        $this->setGoldRates(['22K' => '220']);
        $expected = Money::toAed($p->fresh()->price()->total);
        $this->assertNotSame('1155.00', $expected);
        $this->get('/feed/google.xml')->assertOk()->assertSee("<g:price>{$expected} AED</g:price>", false)->assertSee('<g:id>'.$p->sku.'</g:id>', false);
    }

    /** 7. Arabic flips the site to RTL with Arabic copy and prices. */
    public function test_07_arabic_rtl(): void
    {
        $p = $this->product();
        $this->get('/product/'.$p->slug.'?lang=ar')->assertOk()
            ->assertSee('dir="rtl"', false)->assertSee('خاتم')->assertSee('1,100.00 د.إ')->assertSee('تفاصيل السعر');
        $this->get('/?lang=en')->assertSee('dir="ltr"', false);
    }

    /** 8. Tabby & Tamara appear only for eligible totals. */
    public function test_08_bnpl_eligibility(): void
    {
        $gm = app(GatewayManager::class);
        $methods = array_keys($gm->available($this->quote([$this->product()])));
        $this->assertContains('tabby', $methods);
        $this->assertContains('tamara', $methods);

        $huge = $this->product(['weight_grams' => '120']);   // ~AED 27,720 — above both limits
        $methods = array_keys($gm->available($this->quote([$huge])));
        $this->assertNotContains('tabby', $methods);
        $this->assertNotContains('tamara', $methods);
        $this->assertContains('card', $methods);
    }

    /** 9. Abandoned cart sends a WhatsApp message after 1 hour (and not before). */
    public function test_09_abandoned_cart_whatsapp(): void
    {
        $p = $this->product();
        $cart = Cart::create(['token' => (string) Str::uuid(), 'email' => 'a@example.com', 'phone' => '0501234567', 'items' => [['product_id' => $p->id, 'qty' => 1]], 'last_known_total' => '1155.00', 'last_activity_at' => now()->subMinutes(30)]);

        $this->artisan('shop:abandoned-carts')->assertSuccessful();
        $this->assertSame(0, NotificationLog::where('channel', 'whatsapp')->count());

        $cart->update(['last_activity_at' => now()->subMinutes(61)]);
        $this->artisan('shop:abandoned-carts')->assertSuccessful();
        $log = NotificationLog::where('channel', 'whatsapp')->sole();
        $this->assertSame('+971501234567', $log->recipient);
        $this->assertSame('abandoned_1', $log->template);
        $this->assertSame(1, $cart->fresh()->reminders_sent);
        $this->assertCount(3, SendAbandonedCartReminders::STAGES);
    }

    /** 10. FTA tax invoice: TRN, sequential number, VAT breakdown, PDF. */
    public function test_10_tax_invoice(): void
    {
        $a = app(OrderService::class)->markPaid($this->placeOrder([$this->product()], 'card'), 'mock', 'ref-1');
        $b = app(OrderService::class)->markPaid($this->placeOrder([$this->product()], 'card'), 'mock', 'ref-2');
        $year = now()->year;
        $this->assertSame("INV-{$year}-000001", $a->invoice_number);
        $this->assertSame("INV-{$year}-000002", $b->invoice_number);

        $html = view('invoices.tax-invoice', ['order' => $a->load('items'), 'ar' => fn ($t) => $t])->render();
        foreach (['TAX INVOICE', 'TRN: '.config('shop.defaults.store_trn'), 'INV-'.$year.'-000001', 'Total VAT', '55.00', '1,155.00', 'Taxable amount'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        $this->assertStringStartsWith('%PDF', app(InvoiceService::class)->pdf($a)->output());
    }

    /** 11. Full audit log of every rate change and the prices it produced. */
    public function test_11_rate_audit_log(): void
    {
        $admin = User::factory()->create();
        $p = $this->product();
        app(GoldRateService::class)->updateRate($this->k22->fresh(), '2200', 'ten_gram', $admin->id);

        $h = GoldRateHistory::where('gold_category_id', $this->k22->id)->latest('id')->first();
        $this->assertSame('200.00', $h->previous_rate);
        $this->assertSame('220.00', $h->rate_per_gram);   // AED 2,200 per 10 g
        $this->assertSame($admin->id, $h->changed_by);
        $log = PriceRecalcLog::where('product_id', $p->id)->where('gold_rate_history_id', $h->id)->sole();
        $this->assertSame('1155.00', $log->old_price);
        $this->assertSame('1270.50', $log->new_price);

        $this->expectException(\LogicException::class);
        $h->delete();
    }

    public function test_11b_changing_24k_leaves_other_karats_alone(): void
    {
        $p = $this->product();
        // Even a karat still flagged as "derived" in an old database must not follow 24K.
        $this->k22->update(['auto_derive' => true, 'base_category_id' => $this->k24->id]);
        $this->setGoldRates(['24K' => '260']);

        $this->assertSame('200.00', $this->k22->fresh()->rate_per_gram);
        $this->assertSame('1155.00', $p->fresh()->cached_sales_price);
    }

    /** 12. Product page carries Schema.org Product + Offer markup in AED. */
    public function test_12_product_json_ld(): void
    {
        $p = $this->product();
        $html = $this->get('/product/'.$p->slug)->assertOk()->getContent();
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $schema = collect($m[1])->map(fn ($j) => json_decode($j, true))->firstWhere('@type', 'Product');

        $this->assertNotNull($schema);
        $this->assertSame('AED', $schema['offers']['priceCurrency']);
        $this->assertSame('1100.00', $schema['offers']['price']);   // shown before VAT, like the page
        $this->assertFalse($schema['offers']['priceSpecification']['valueAddedTaxIncluded']);
        $this->assertSame('https://schema.org/InStock', $schema['offers']['availability']);
        $this->assertSame($p->sku, $schema['sku']);
        $this->assertNotEmpty($schema['image']);
    }
}
