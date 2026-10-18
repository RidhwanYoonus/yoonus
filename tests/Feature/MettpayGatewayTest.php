<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GoldCategory;
use App\Models\Order;
use App\Models\Product;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MettpayGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Settings::set(['id_verification' => 'off', 'card_gateway' => 'mettpay', 'mettpay_merchant_key' => 'live-TEST', 'mettpay_merchant_secret' => 'sec-TEST']);
        Product::create(['name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 3]);
    }

    private function placeCardOrder()
    {
        $branch = Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        $this->post('/cart/add/band');
        $form = ['email' => 'm@example.com', 'phone' => '+971501234567', 'name' => 'Mariam Ali', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => 'card'];
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');

        return $this->post('/checkout', $form);
    }

    public function test_card_checkout_creates_a_mettpay_order_and_redirects_to_its_page(): void
    {
        Http::fake(['application.mettpay.com/*' => Http::response(['code' => 200, 'data' => ['orderNo' => 'MP-991', 'paymentUrl' => 'https://pay.mettpay.com/checkout/abc']])]);

        $this->placeCardOrder()->assertRedirect('https://pay.mettpay.com/checkout/abc');
        $order = Order::sole();
        $this->assertSame('MP-991', $order->payment_reference);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://application.mettpay.com/api/orders/createorder'
            && $r->hasHeader('merchantKey', 'live-TEST') && $r->hasHeader('merchantSecret', 'sec-TEST')
            && $r['order']['id'] === $order->number && (float) $r['order']['amount'] === (float) $order->amount_due
            && $r['customer']['firstName'] === 'Mariam' && $r['customer']['lastName'] === 'Ali'
            && str_contains($r['order']['returnSuccUrl'], 'result=success') && str_contains($r['order']['returnSuccUrl'], 'signature='));
    }

    public function test_success_return_without_status_api_is_flagged_for_staff_not_marked_paid(): void
    {
        Http::fake(['application.mettpay.com/*' => Http::response(['data' => ['paymentUrl' => 'https://pay.mettpay.com/x']])]);
        $this->placeCardOrder();
        $order = Order::sole();

        $this->get('/payments/mettpay/return/'.$order->number.'?result=success')->assertForbidden();   // unsigned / forged
        $this->get(URL::signedRoute('payments.return', ['gateway' => 'mettpay', 'order' => $order->number, 'result' => 'success']))->assertRedirect();

        $this->assertSame('verifying', $order->fresh()->payment_status);
        $this->assertFalse($order->fresh()->isPaid());
        $this->get(route('order.show', $order->number))->assertSee('Payment received — being confirmed');
    }

    public function test_with_a_status_url_paid_orders_are_confirmed_automatically(): void
    {
        Http::fake([
            'application.mettpay.com/api/orders/createorder' => Http::response(['data' => ['paymentUrl' => 'https://pay.mettpay.com/x']]),
            'application.mettpay.com/api/orders/status*' => fn () => Http::response(['data' => ['status' => 'PAID', 'amount' => (float) Order::sole()->amount_due]]),
        ]);
        Settings::set(['mettpay_status_url' => 'https://application.mettpay.com/api/orders/status/{id}']);
        $this->placeCardOrder();
        $order = Order::sole();

        $this->get(URL::signedRoute('payments.return', ['gateway' => 'mettpay', 'order' => $order->number, 'result' => 'success']));
        $this->assertTrue($order->fresh()->isPaid());
        $this->assertNotNull($order->fresh()->invoice_number);
    }

    public function test_mettpay_errors_are_explained(): void
    {
        Http::fake(['application.mettpay.com/*' => Http::response(['code' => 403, 'message' => 'UNAUTHORIZED'], 403)]);
        $res = $this->placeCardOrder();
        $res->assertRedirect();
        $this->followRedirects($res)->assertSee(__('shop.pay.start_failed'));
    }

    public function test_admin_sees_the_exact_reason_and_customers_can_pay_again(): void
    {
        Http::fake(['application.mettpay.com/*' => Http::sequence()
            ->push(['code' => 403, 'message' => 'UNAUTHORIZED'], 403)
            ->push(['data' => ['paymentUrl' => 'https://pay.mettpay.com/retry']])]);
        $this->actingAs(\App\Models\User::factory()->create(['is_admin' => true]));
        $res = $this->placeCardOrder();
        $this->followRedirects($res)->assertSee('Admin only — reason')->assertSee('UNAUTHORIZED')->assertSee('Pay now');

        $order = Order::sole();
        $this->post(route('order.pay', $order->number))->assertRedirect('https://pay.mettpay.com/retry');
    }

    public function test_settings_test_button_reports_mettpay_reply(): void
    {
        Http::fake(['application.mettpay.com/*' => Http::sequence()
            ->push(['data' => ['paymentUrl' => 'https://pay.mettpay.com/test']])
            ->push('Unauthorized', 401)]);
        $this->assertSame('https://pay.mettpay.com/test', \App\Payments\MettpayGateway::testConnection()['url']);
        Http::assertSent(fn (Request $r) => (float) $r['order']['amount'] === 1.0 && str_starts_with($r['order']['id'], 'TEST-'));

        try {
            \App\Payments\MettpayGateway::testConnection();
            $this->fail('should throw');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('HTTP 401', $e->getMessage());
        }
    }

    public function test_page_not_found_retries_with_trailing_slash_and_lists_server_paths(): void
    {
        Http::fake([
            'application.mettpay.com/api/orders/createorder/' => Http::response(['data' => ['paymentUrl' => 'https://pay.mettpay.com/slash']]),
            'application.mettpay.com/api/orders/createorder' => Http::response('<html><title>Page not found</title></html>', 404),
        ]);
        $this->assertSame('https://pay.mettpay.com/slash', \App\Payments\MettpayGateway::testConnection()['url']);
    }

    public function test_not_found_page_shows_the_addresses_mettpay_lists(): void
    {
        Http::fake(['application.mettpay.com/*' => Http::response('<html><body><ol><li>api/v1/ </li><li>api/v1/orders/create/ [name=\'create\']</li></ol></body></html>', 404)]);
        try {
            \App\Payments\MettpayGateway::testConnection();
            $this->fail('should throw');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('api/v1/orders/create/', $e->getMessage());
        }
    }
}
