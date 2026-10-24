<?php

namespace Tests\Feature;

use App\Affiliates\AffiliateService;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Mail\ShopMessage;
use App\Models\Affiliate;
use App\Models\Branch;
use App\Models\GoldCategory;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\DeliveryEstimate;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerEaseBatchTest extends TestCase
{
    use RefreshDatabase;

    private Product $ring;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Settings::set(['id_verification' => 'off', 'handover_otp' => false]);
        $this->ring = Product::create(['name' => 'Ring', 'sku' => 'R-1', 'slug' => 'ring', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 3, 'stock_qty' => 4, 'is_active' => true]);
    }

    private function guestOrder(string $email, string $phone): Order
    {
        $branch = Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        $this->post('/cart/add/ring');
        $form = ['email' => $email, 'phone' => $phone, 'name' => 'Huda', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => 'card'];
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');
        $this->post('/checkout', $form);
        $this->flushSession();

        return Order::latest('id')->first();
    }

    public function test_guest_signs_in_with_a_mobile_code_and_sees_earlier_orders(): void
    {
        $order = $this->guestOrder('huda@example.com', '0501234567');
        $this->get('/login')->assertSee('Sign in with a code');
        $this->post('/login/code', ['login' => '+971 50 123 4567'])->assertRedirect(route('login.code'));
        $code = null;
        preg_match('/is (\d{6})/', NotificationLog::where('template', 'login_code')->latest('id')->value('body'), $m);
        $code = $m[1];

        $this->post('/login/code/verify', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/login/code/verify', ['code' => $code])->assertRedirect(route('account'));
        $user = User::where('email', 'huda@example.com')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame($user->id, $order->fresh()->user_id);
        $this->assertNotNull($user->phone_verified_at);
        $this->get('/account')->assertSee($order->number);
    }

    public function test_email_code_signs_in_an_existing_account_but_never_staff(): void
    {
        $u = User::factory()->create(['email' => 'mona@example.com']);
        $this->post('/login/code', ['login' => 'Mona@Example.com']);
        Mail::assertSent(ShopMessage::class, function ($m) use (&$code) { preg_match('/^(\d{6})/', $m->subjectLine, $x); $code = $x[1] ?? null; return $m->hasTo('mona@example.com'); });
        $this->post('/login/code/verify', ['code' => $code]);
        $this->assertAuthenticatedAs($u);
        auth()->logout();

        User::factory()->create(['email' => 'boss@shop.ae', 'is_admin' => true]);
        $this->post('/login/code', ['login' => 'boss@shop.ae']);
        Mail::assertSent(ShopMessage::class, function ($m) use (&$code) { preg_match('/^(\d{6})/', $m->subjectLine, $x); $code = $x[1] ?? $code; return $m->hasTo('boss@shop.ae'); });
        $this->post('/login/code/verify', ['code' => $code])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post('/login/code', ['login' => 'not a number'])->assertSessionHasErrors('login');
    }

    public function test_estimated_delivery_and_pickup_dates(): void
    {
        Settings::set(['delivery_days_min' => 1, 'delivery_days_max' => 2, 'pickup_ready_days' => 0, 'order_cutoff' => '17:00', 'working_days' => [1, 2, 3, 4, 5, 6]]);
        $this->travelTo(Carbon::parse('2026-10-26 10:00', 'Asia/Dubai'));   // Monday morning
        $this->assertSame('Estimated delivery: tomorrow – Wed 28 Oct', DeliveryEstimate::deliveryText());
        $this->assertSame('Ready to collect: today', DeliveryEstimate::pickupText());
        $this->travelTo(Carbon::parse('2026-10-31 18:00', 'Asia/Dubai'));   // Saturday after cut-off → Monday start, Sunday off
        $this->assertSame('Ready to collect: Mon 2 Nov', DeliveryEstimate::pickupText());
        $this->get('/product/ring')->assertSee('Ready to collect');
    }

    public function test_low_making_watermark_only_on_pieces_in_stock(): void
    {
        $this->get('/product/ring')->assertSee('Only 3% making');
        $this->ring->update(['manufacturer_charge_value' => 8]);
        $this->get('/product/ring')->assertDontSee('making-watermark', false)->assertDontSee('Only 8% making');
    }

    public function test_product_commission_replaces_the_affiliates_rate(): void
    {
        $a = Affiliate::create(['user_id' => User::factory()->create()->id, 'code' => 'AFF1', 'making_commission_percent' => 10, 'fixed_commission_percent' => 3]);
        $svc = app(AffiliateService::class);
        $this->assertSame(300, $svc->earningsFor($a, $this->ring)['commission']);   // 10% of AED 30 making
        $this->ring->update(['affiliate_commission_type' => 'per_piece', 'affiliate_commission_value' => 50]);
        $this->assertSame(5000, $svc->earningsFor($a, $this->ring->fresh())['commission']);   // AED 50 per piece instead
        $this->ring->update(['affiliate_commission_type' => 'price_percent', 'affiliate_commission_value' => 1]);
        $this->assertSame(1030, $svc->earningsFor($a, $this->ring->fresh())['commission']);   // 1% of AED 1,030
    }

    public function test_affiliate_customers_list_can_be_switched_off_by_admin(): void
    {
        $a = Affiliate::create(['user_id' => User::factory()->create()->id, 'code' => 'AFF2', 'making_commission_percent' => 10, 'can_see_customers' => false]);
        $this->actingAs($a->user)->get('/affiliate/report')->assertOk()->assertDontSee('My customers');
        $this->get('/affiliate/report?tab=customers')->assertForbidden();
        $this->get('/affiliate/report?tab=customers&download=customers')->assertForbidden();
    }

    public function test_admin_product_screens_save_on_top_and_filter_by_stock(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Livewire::test(EditProduct::class, ['record' => $this->ring->getRouteKey()])
            ->assertActionExists('saveTop')->set('data.stock_qty', 2)->callAction('saveTop')->assertHasNoErrors();
        $this->assertSame(2, $this->ring->fresh()->stock_qty);
        $other = Product::create(['name' => 'Chain', 'sku' => 'C-1', 'slug' => 'chain', 'gold_category_id' => $this->ring->gold_category_id,
            'weight_grams' => 3, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 9, 'is_active' => true]);
        Livewire::test(ListProducts::class)->filterTable('stock', 'two')->assertCanSeeTableRecords([$this->ring])->assertCanNotSeeTableRecords([$other])
            ->resetTableFilters()->filterTable('stock_max', ['max' => 9])->assertCanSeeTableRecords([$this->ring, $other]);
    }

    public function test_mobile_code_is_confirmed_when_six_digits_are_typed(): void
    {
        Settings::set(['phone_verification' => true]);
        $this->get('/register')->assertSee('one-time-code', false);
        $js = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('d.length === 6', $js);
    }
}
