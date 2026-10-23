<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\Branch;
use App\Models\GoldCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AffiliateSignupLinkTest extends TestCase
{
    use RefreshDatabase;

    private Affiliate $affiliate;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Settings::set(['id_verification' => 'off', 'handover_otp' => false]);
        Product::create(['name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 5, 'is_active' => true]);
        $partner = User::factory()->create(['name' => 'Huda Partner', 'email' => 'huda@partner.ae']);
        $this->affiliate = Affiliate::create(['user_id' => $partner->id, 'code' => 'HUDA10', 'making_commission_percent' => 10, 'fixed_commission_percent' => 3,
            'white_page_enabled' => false]);
    }

    public function test_sign_up_link_fills_the_code_and_welcomes_the_customer(): void
    {
        $this->assertSame(route('register', ['ref' => 'HUDA10']), $this->affiliate->signupLink());
        $this->get($this->affiliate->signupLink())->assertOk()->assertSee('value="HUDA10"', false)->assertSee('You were invited by');
        $this->actingAs($this->affiliate->user)->get('/affiliate')->assertOk()->assertSee('Your sign-up link')->assertSee($this->affiliate->signupLink());
    }

    public function test_without_white_page_the_signed_up_customer_pays_normal_prices_and_the_affiliate_earns_commission(): void
    {
        $this->post('/register', ['name' => 'Sara', 'email' => 'sara@example.com', 'password' => 'Secret123', 'password_confirmation' => 'Secret123', 'referral_code' => 'HUDA10']);
        $this->get('/product/band')->assertSee('1,100.00');   // normal price

        $branch = Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        $this->post('/cart/add/band');
        $form = ['email' => 'sara@example.com', 'phone' => '+971501234567', 'name' => 'Sara', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => 'card'];
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');
        $this->post('/checkout', $form);
        $order = Order::latest('id')->firstOrFail();

        $this->assertSame($this->affiliate->id, $order->affiliate_id);
        $this->assertFalse((bool) $order->affiliate_white_page);
        $this->assertSame('10.00', AffiliateCommission::where('order_id', $order->id)->value('amount'));   // 10% of AED 100 making
    }

    public function test_affiliate_can_share_products_with_the_photo(): void
    {
        $this->actingAs($this->affiliate->user)->get('/affiliate/share')->assertOk()->assertSee('Share with photo')->assertSee('navigator.canShare', false);
    }
}
