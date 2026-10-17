<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\GoldCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Payments\StripeGateway;
use App\Pricing\GoldRateService;
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
        app(GoldRateService::class)->updateRate(GoldCategory::where('name', '24K')->first(), '218.18');
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
            'payment_method' => 'cod', 'expected_total' => '1190.00',   // 1155 + AED 35 delivery (free only above AED 1,500)
        ];
    }

    public function test_guest_cod_checkout_creates_snapshot_order(): void
    {
        $this->post('/cart/add/band')->assertRedirect();
        $this->get('/checkout')->assertOk()->assertSee('Tabby');

        $res = $this->post('/checkout', $this->form());
        $order = Order::sole();
        $res->assertRedirect(route('checkout.complete', $order->number));
        $this->assertSame('1190.00', $order->grand_total);
        $this->assertSame('cod', $order->payment_status);
        $this->assertSame(2, $this->product->fresh()->stock_qty);
        $this->get(route('checkout.complete', $order->number))->assertOk()->assertSee($order->number);
    }

    public function test_price_change_during_checkout_is_flagged_not_charged(): void
    {
        $this->post('/cart/add/band');
        app(GoldRateService::class)->updateRate(GoldCategory::where('name', '24K')->first(), '230');

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
        $res = $this->post('/checkout', $this->form(['payment_method' => 'card']));
        $order = Order::sole();
        $this->assertStringContainsString('/payments/mock/'.$order->number, $res->headers->get('Location'));

        $this->post($res->headers->get('Location'))->assertRedirect(route('checkout.complete', $order->number));
        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertNotNull($order->invoice_number);
        $this->get(route('order.invoice', $order->number))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_tiered_and_free_shipping_coupons(): void
    {
        Coupon::create(['code' => 'FREESHIP', 'name' => 'Free', 'type' => 'free_shipping', 'scope' => 'cart', 'stackable' => true]);
        $this->post('/cart/add/band');
        $json = $this->postJson('/checkout/quote', ['coupon' => 'FREESHIP', 'zone_id' => ShippingZone::where('code', 'abu_dhabi')->value('id')])->json();
        $this->assertSame(__('shop.checkout.free'), $json['shipping']);
        $this->assertSame('AED 1,155.00', $json['grand_total']);

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
}
