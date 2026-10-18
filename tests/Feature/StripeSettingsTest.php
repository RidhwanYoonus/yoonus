<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GoldCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Payments\StripeGateway;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StripeSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Settings::set(['id_verification' => 'off', 'card_gateway' => 'stripe', 'stripe_secret_key' => 'sk_test_ADMIN']);
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

    public function test_checkout_uses_the_secret_key_from_settings_and_confirms_on_return(): void
    {
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1'])]);
        $this->placeCardOrder()->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');
        $order = Order::sole();
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer sk_test_ADMIN')
            && $r['payment_method_types[0]'] === 'card'
            && str_contains($r['success_url'], '/payments/stripe/return/'.$order->number.'?session_id={CHECKOUT_SESSION_ID}'));

        Http::fake(['api.stripe.com/v1/checkout/sessions/cs_test_1' => Http::response([
            'id' => 'cs_test_1', 'payment_status' => 'paid', 'payment_intent' => 'pi_1', 'client_reference_id' => $order->number,
            'metadata' => ['order_number' => $order->number], 'amount_total' => (int) round((float) $order->amount_due * 100),
        ])]);
        $this->get("/payments/stripe/return/{$order->number}?session_id=cs_test_1")->assertRedirect(route('checkout.complete', $order->number));
        $this->assertTrue($order->fresh()->isPaid());
    }

    public function test_unpaid_or_wrong_amount_session_does_not_mark_paid(): void
    {
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_2', 'url' => 'https://checkout.stripe.com/x'])]);
        $this->placeCardOrder();
        $order = Order::sole();
        Http::fake(['api.stripe.com/v1/checkout/sessions/*' => Http::response(['id' => 'cs_test_2', 'payment_status' => 'paid',
            'metadata' => ['order_number' => $order->number], 'amount_total' => 100])]);
        $this->get("/payments/stripe/return/{$order->number}?session_id=cs_test_2");
        $this->assertFalse($order->fresh()->isPaid());
    }

    public function test_settings_page_shows_stripe_fields_and_test_checks_the_key(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('Stripe card payments')->assertSee('Test Stripe');

        Http::fake(['api.stripe.com/v1/balance' => Http::response(['livemode' => false])]);
        $this->assertSame('test', StripeGateway::testConnection());

        Settings::set(['stripe_secret_key' => 'pk_test_WRONG']);
        $this->expectExceptionMessage('publishable key');
        StripeGateway::testConnection();
    }
}
