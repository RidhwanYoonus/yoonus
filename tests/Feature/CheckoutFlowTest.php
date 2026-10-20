<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Coupon;
use App\Models\GoldCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Payments\StripeGateway;
use App\Shop\OrderService;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        $this->product = Product::create([
            'name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 3,
        ]);
    }

    private function form(array $over = []): array
    {
        return $over + [
            'email' => 'guest@example.com', 'phone' => '+971501234567', 'name' => 'Guest', 'fulfilment' => 'delivery',
            'zone_id' => ShippingZone::where('code', 'other_emirates')->value('id'), 'line1' => 'Tower 1', 'city' => 'Al Reem',
            // 1,100 goods + AED 35 delivery (free only above AED 1,500), then 5% VAT on both: 1,135 + 56.75
            'payment_method' => 'cod', 'expected_total' => '1191.75',
        ] + $this->idFields();
    }

    public function test_guest_cod_checkout_creates_snapshot_order(): void
    {
        $this->post('/cart/add/band')->assertRedirect();
        $this->get('/checkout')->assertOk()->assertSee('Tabby');

        $res = $this->post('/checkout', $this->form());
        $order = Order::sole();
        $res->assertRedirect(route('checkout.complete', $order->number));
        $this->assertSame('1191.75', $order->grand_total);
        $this->assertSame('0.00', $order->payment_fee);
        $this->assertSame('cod', $order->payment_status);
        $this->assertSame(2, $this->product->fresh()->stock_qty);
        $this->get(route('checkout.complete', $order->number))->assertOk()->assertSee($order->number);
    }

    public function test_applied_code_keeps_its_discount_after_a_failed_submit(): void
    {
        Coupon::create(['code' => 'OFF10', 'name' => 'Ten', 'type' => 'fixed', 'value' => 10, 'scope' => 'cart']);
        $this->post('/cart/add/band');

        $this->from('/checkout')->post('/checkout', $this->form(['coupon' => 'OFF10', 'name' => '']))->assertSessionHasErrors('name');
        $page = $this->get('/checkout')->assertOk()->assertSee('OFF10');
        $this->assertStringContainsString('AED 10.00', html_entity_decode($page->getContent()));
        $this->assertSame(0, Order::count());
    }

    public function test_price_change_during_checkout_is_flagged_not_charged(): void
    {
        $this->post('/cart/add/band');
        $this->setGoldRates(['22K' => '210']);

        $this->post('/checkout', $this->form())->assertSessionHas('price_updated');
        $this->assertSame(0, Order::count());
    }

    public function test_checkout_blocked_when_rate_is_stale_and_setting_on(): void
    {
        Settings::set(['block_checkout_when_stale' => true]);
        $this->post('/cart/add/band');
        $this->travel(25)->hours();
        $this->post('/checkout', $this->form())->assertSessionHasErrors('checkout');
        $this->assertSame(0, Order::count());
    }

    public function test_mock_card_payment_marks_paid_and_issues_invoice(): void
    {
        $this->post('/cart/add/band');
        $quoted = $this->postJson('/checkout/quote', $this->form(['payment_method' => 'card']))->json('grand_total_raw');
        $res = $this->post('/checkout', $this->form(['payment_method' => 'card', 'expected_total' => $quoted]));
        $order = Order::sole();
        $this->assertStringContainsString('/payments/mock/'.$order->number, $res->headers->get('Location'));

        $this->post($res->headers->get('Location'))->assertRedirect(route('checkout.complete', $order->number));
        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertNotNull($order->invoice_number);
        $this->get(route('order.invoice', $order->number))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_card_fee_and_vat_are_added_at_checkout_only(): void
    {
        Settings::set(['card_fee_percent' => 2.5, 'card_fee_fixed' => 1]);
        $this->post('/cart/add/band');
        $this->get('/cart')->assertSee('AED 1,100.00')->assertDontSee('AED 1,155.00');

        $form = $this->form(['zone_id' => ShippingZone::where('code', 'dubai')->value('id')]);   // free delivery
        $cod = $this->postJson('/checkout/quote', $form)->json();
        $this->assertSame('AED 1,155.00', $cod['grand_total']);      // 1,100 + 5% VAT, no fee
        $this->assertNull($cod['payment_fee']);

        $card = $this->postJson('/checkout/quote', ['payment_method' => 'card'] + $form)->json();
        // fee = 2.5% of 1,155 + AED 1 = 29.88 → VAT on fee 1.49 → total 1,155 + 29.88 + 1.49
        $this->assertSame('AED 29.88', $card['payment_fee']);
        $this->assertSame('AED 56.49', $card['vat']);                 // 55.00 goods + 1.49 fee
        $this->assertSame('AED 1,186.37', $card['grand_total']);

        $res = $this->post('/checkout', ['payment_method' => 'card', 'expected_total' => $card['grand_total_raw']] + $form);
        $order = Order::sole();
        $this->assertSame(['29.88', '1.49', '1186.37'], [$order->payment_fee, $order->payment_fee_vat, $order->grand_total]);

        $this->post($res->headers->get('Location'));   // simulated card payment → invoice issued
        $html = view('invoices.tax-invoice', ['order' => $order->fresh()->load('items'), 'ar' => fn ($t) => $t])->render();
        $this->assertStringContainsString('Payment processing fee (CARD)', $html);
        $this->assertStringContainsString('1,186.37', $html);
    }

    public function test_bag_shows_gold_price_breakdown_but_not_for_fixed_price_items(): void
    {
        Product::create(['name' => 'Watch', 'sku' => 'W-9', 'slug' => 'watch', 'pricing_type' => 'FIXED', 'fixed_price' => 800, 'weight_grams' => 0, 'stock_qty' => 2]);
        $this->post('/cart/add/band', ['qty' => 2]);
        $this->post('/cart/add/watch');

        $html = $this->get('/cart')->assertOk()->getContent();
        $this->assertStringContainsString('Gold value (5.000 g × AED 200.00) × 2', $html);
        $this->assertStringContainsString('AED 2,000.00', $html);                 // gold value for 2 pieces
        $this->assertStringContainsString('Making charge (10%)', $html);
        $this->assertStringContainsString('AED 200.00', $html);                   // making for 2 pieces
        $this->assertSame(1, substr_count($html, 'Making charge'));               // the watch has no breakdown
    }

    public function test_store_pickup_orders_go_ready_then_collected_never_shipped(): void
    {
        Settings::set(['handover_otp' => false]);   // the handover code has its own test
        $branch = Branch::create(['name' => 'Gold Souk', 'emirate' => 'Dubai', 'address' => 'Deira']);
        $this->post('/cart/add/band');
        $form = ['email' => 'p@example.com', 'phone' => '+971501234567', 'name' => 'P', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => 'card'];
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');
        $this->post('/checkout', $form)->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertTrue($order->isPickup());
        $service = app(OrderService::class);

        $service->transition($order, 'paid');
        $order->refresh();
        $service->transition($order, 'packed');
        $order->refresh();
        $this->assertSame(['ready', 'refunded'], $order->allowedTransitions());   // no "shipped" for pickup
        $service->transition($order, 'ready');
        $order->refresh();
        $this->assertSame('Ready for pickup', $order->statusLabel());
        $service->transition($order, 'delivered');
        $this->assertSame('Collected', $order->fresh()->statusLabel());

        $this->expectException(\RuntimeException::class);
        $service->transition($order->fresh(), 'shipped');
    }

    public function test_delivery_orders_still_ship(): void
    {
        $this->post('/cart/add/band');
        $this->post('/checkout', $this->form());
        $order = Order::sole();
        $this->assertContains('packed', $order->allowedTransitions());   // COD can be packed before payment
        app(OrderService::class)->transition($order, 'packed');
        $this->assertSame(['shipped', 'refunded'], $order->fresh()->allowedTransitions());
    }

    public function test_making_charge_badge_and_store_caption(): void
    {
        Product::create(['name' => 'Bangle', 'sku' => 'B-2', 'slug' => 'bangle', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 10, 'manufacturer_charge_type' => 'PER_PIECE', 'manufacturer_charge_value' => 350, 'stock_qty' => 1]);
        Product::create(['name' => 'Coin', 'sku' => 'C-1', 'slug' => 'coin', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 8, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 1, 'stock_qty' => 1]);

        $this->get('/shop')->assertOk()
            ->assertSee('10% making')          // the 5 g band at 10%
            ->assertSee('Only 1% making')      // low charges are highlighted
            ->assertDontSee('Making AED 350');
        $this->get('/')->assertSee('Order direct from the factory · Collect from our premium retail store');
        $this->get('/?lang=ar')->assertSee('مصنعية');

        Settings::set(['making_badge' => false]);
        $this->get('/shop')->assertDontSee('Only 1% making');
    }

    public function test_tiered_and_free_shipping_coupons(): void
    {
        Coupon::create(['code' => 'FREESHIP', 'name' => 'Free', 'type' => 'free_shipping', 'scope' => 'cart', 'stackable' => true]);
        $this->post('/cart/add/band');
        $json = $this->postJson('/checkout/quote', ['coupon' => 'FREESHIP', 'zone_id' => ShippingZone::where('code', 'abu_dhabi')->value('id')])->json();
        $this->assertSame(__('shop.checkout.free'), $json['shipping']);
        $this->assertSame('AED 1,155.00', $json['grand_total']);   // 1,100 + 5% VAT, delivery waived

        Coupon::create(['code' => 'TIER', 'name' => 'Tier', 'type' => 'tiered', 'rules' => [['min' => 5000, 'percent' => 10]], 'scope' => 'cart']);
        $json = $this->postJson('/checkout/quote', ['coupon' => 'TIER'])->json();
        $this->assertNotEmpty($json['messages']);   // below the AED 5,000 tier
    }

    public function test_stripe_webhook_signature_is_verified(): void
    {
        $gw = new StripeGateway;
        $payload = '{"id":"evt_1"}';
        $t = time();
        $sig = hash_hmac('sha256', "{$t}.{$payload}", 'whsec_test');
        $this->assertTrue($gw->validSignature($payload, "t={$t},v1={$sig}", 'whsec_test'));
        $this->assertFalse($gw->validSignature($payload, "t={$t},v1=deadbeef", 'whsec_test'));
        $this->assertFalse($gw->validSignature($payload, 't='.($t - 3600).",v1={$sig}", 'whsec_test'));
    }

    public function test_storefront_pages_render_in_both_languages(): void
    {
        foreach (['en', 'ar'] as $lang) {
            foreach (['/', '/shop', '/product/band', '/cart', '/login', '/register', '/track', '/gift-cards', '/compare', '/wishlist'] as $url) {
                $this->get($url.'?lang='.$lang)->assertOk();
            }
        }
        $this->get('/sitemap.xml')->assertOk()->assertSee('/product/band', false);
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap:');
    }

    public function test_translation_files_have_identical_keys(): void
    {
        $flat = fn ($a, $p = '') => collect($a)->flatMap(fn ($v, $k) => is_array($v) ? ($GLOBALS['__f'])($v, "$p$k.") : ["$p$k"]);
        $GLOBALS['__f'] = $flat;
        $this->assertEquals($flat(require lang_path('en/shop.php'))->sort()->values(), $flat(require lang_path('ar/shop.php'))->sort()->values());
    }

    public function test_saved_pieces_show_gold_heart_and_header_count(): void
    {
        $this->get('/shop')->assertDontSee('aria-pressed="true"', false);
        $this->post('/wishlist/band')->assertRedirect();
        $this->get('/shop')->assertOk()
            ->assertSee('aria-pressed="true"', false)
            ->assertSeeInOrder([route('wishlist'), '>1</span>'], false);
        $this->get('/product/band')->assertSee('Saved');
    }
}
