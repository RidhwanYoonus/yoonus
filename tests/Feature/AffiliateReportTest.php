<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliatePayout;
use App\Models\GoldCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AffiliateReportTest extends TestCase
{
    use RefreshDatabase;

    private Affiliate $affiliate;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['18K' => '300.00']);
        Settings::set(['id_verification' => 'off', 'handover_otp' => false]);
        Product::create(['name' => 'Ring', 'sku' => 'R-1', 'slug' => 'ring', 'gold_category_id' => GoldCategory::where('name', '18K')->value('id'),
            'weight_grams' => 4, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 5, 'is_active' => true]);
        $user = User::factory()->create(['name' => 'Huda Partner']);
        $this->affiliate = Affiliate::create(['user_id' => $user->id, 'code' => 'HUDA10', 'making_commission_type' => 'per_gram', 'commission_per_gram' => 5,
            'fixed_commission_percent' => 3, 'white_page_enabled' => false]);
    }

    private function codOrder(string $name, string $email, string $phone): Order
    {
        $this->get('/?aff=HUDA10');
        $this->post('/cart/add/ring');
        $form = ['email' => $email, 'phone' => $phone, 'name' => $name, 'fulfilment' => 'delivery',
            'zone_id' => \App\Models\ShippingZone::where('code', 'dubai')->value('id'), 'line1' => 'Tower 1', 'city' => 'Dubai', 'payment_method' => 'cod'];
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');
        $this->post('/checkout', $form)->assertSessionHasNoErrors();

        return Order::latest('id')->firstOrFail();
    }

    public function test_affiliate_sees_every_transaction_and_can_download_it(): void
    {
        $order = $this->codOrder('Mariam Ali', 'mariam@example.com', '+971501112233');
        AffiliatePayout::create(['affiliate_id' => $this->affiliate->id, 'amount' => 10, 'paid_at' => now(), 'reference' => 'RC-77']);

        $this->actingAs($this->affiliate->user)->get('/affiliate')->assertSee('Full report &amp; my customers', false);
        $this->get('/affiliate/report')->assertOk()
            ->assertSee($order->number)->assertSee('Mariam Ali')->assertSee('g × AED 5')
            ->assertSee('Commission')->assertSee('Collected')->assertSee('RC-77');
        $this->get('/affiliate/report?type=collected')->assertSee('RC-77')->assertDontSee($order->number);
        $this->get('/affiliate/report?from='.now()->addDay()->toDateString())->assertSee('Nothing in this period yet.');

        $csv = $this->get('/affiliate/report?download=transactions')->assertOk()->streamedContent();
        $this->assertStringContainsString('How it is worked out', $csv);
        $this->assertStringContainsString($order->number, $csv);
        $this->assertStringContainsString('-10.00', $csv);
    }

    public function test_affiliate_sees_customers_from_sign_up_and_orders_for_marketing(): void
    {
        User::factory()->create(['name' => 'Salma Signup', 'phone' => '+971509990000', 'signup_affiliate_id' => $this->affiliate->id, 'marketing_opt_in' => true]);
        User::factory()->create(['name' => 'Stopped Person', 'signup_affiliate_id' => $this->affiliate->id, 'affiliate_commission_off' => true]);
        $this->codOrder('Mariam Ali', 'mariam@example.com', '+971501112233');
        $this->codOrder('Mariam Ali', 'mariam@example.com', '+971501112233');
        auth()->logout();

        $page = $this->actingAs($this->affiliate->user)->get('/affiliate/report?tab=customers')->assertOk()
            ->assertSee('Salma Signup')->assertSee('Signed up with your link')->assertSee('Offers OK')
            ->assertSee('Mariam Ali')->assertSee('Ordered through you')->assertSee('+971501112233')->assertSee('wa.me/971501112233', false)
            ->assertDontSee('Stopped Person');
        $this->assertSame(1, substr_count($page->getContent(), 'mariam@example.com'));   // two orders, one customer row

        $csv = $this->get('/affiliate/report?tab=customers&download=customers')->streamedContent();
        $this->assertStringContainsString('Salma Signup', $csv);
        $this->assertMatchesRegularExpression('/"Mariam Ali",\+971501112233,mariam@example.com,"Ordered through you",[0-9-]+,2,/', $csv);

        // The shop can hide full contact details.
        Settings::set(['affiliate_customer_contacts' => false]);
        $this->get('/affiliate/report?tab=customers')->assertDontSee('+971501112233')->assertSee('m***@example.com')->assertDontSee('wa.me/971501112233', false);
    }

    public function test_only_affiliates_open_the_report(): void
    {
        $this->actingAs(User::factory()->create())->get('/affiliate/report')->assertNotFound();
    }
}
