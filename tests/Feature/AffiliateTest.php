<?php

namespace Tests\Feature;

use App\Affiliates\AffiliateService;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\Branch;
use App\Models\GoldCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Shop\OrderService;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AffiliateTest extends TestCase
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
        // 5 g × 200 = 1,000 gold + 10% making 100 → 1,100 before VAT
        Product::create(['name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 5]);
        Product::create(['name' => 'Watch', 'sku' => 'W-1', 'slug' => 'watch', 'pricing_type' => 'FIXED', 'fixed_price' => 500,
            'weight_grams' => 0, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 0, 'stock_qty' => 5]);
        $user = User::factory()->create(['name' => 'Huda Partner', 'email' => 'huda@partner.ae']);
        $this->affiliate = Affiliate::create(['user_id' => $user->id, 'code' => 'HUDA10', 'making_commission_percent' => 10, 'fixed_commission_percent' => 3,
            'white_page_enabled' => true, 'markup_mode' => 'percent', 'making_markup_percent' => 20, 'fixed_markup_percent' => 5,
            'max_making_markup_percent' => 30, 'max_fixed_markup_percent' => 10, 'max_markup_amount' => 100]);
    }

    private function checkout(array $slugs = ['band', 'watch']): Order
    {
        $branch = Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        foreach ($slugs as $slug) {
            $this->post("/cart/add/{$slug}");
        }
        $form = ['email' => 'buyer@example.com', 'phone' => '+971501234567', 'name' => 'Buyer', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => 'card'];
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');
        $this->post('/checkout', $form);

        return Order::latest('id')->firstOrFail();
    }

    private function deliver(Order $order): void
    {
        $svc = app(OrderService::class);
        $svc->markPaid($order, 'stripe', 'pi_'.$order->id);
        foreach (['packed', 'ready', 'delivered'] as $to) {
            $svc->transition($order->fresh(), $to);
        }
    }

    public function test_referral_link_earns_making_percent_on_gold_items_and_total_percent_on_fixed_items(): void
    {
        $this->get('/?aff=huda10')->assertOk()->assertCookie('aff');
        $order = $this->checkout();
        $this->assertSame($this->affiliate->id, $order->affiliate_id);

        $band = AffiliateCommission::where('basis', 'making')->sole();
        $this->assertSame('100.00', $band->base_amount);      // making charge
        $this->assertSame('10.00', $band->amount);            // 10% of making
        $watch = AffiliateCommission::where('basis', 'total')->sole();
        $this->assertSame('500.00', $watch->base_amount);     // item total
        $this->assertSame('15.00', $watch->amount);           // 3% of total
        $this->assertSame(2500, $this->affiliate->totals()['pending']);
        $this->assertSame(0, $this->affiliate->totals()['balance']);

        $this->deliver($order);
        $t = $this->affiliate->fresh()->totals();
        $this->assertSame(0, $t['pending']);
        $this->assertSame(2500, $t['balance']);   // AED 25 ready to collect
    }

    public function test_white_page_marks_up_only_the_making_on_gold_items_and_the_price_on_fixed_items(): void
    {
        $this->get('/product/band')->assertSee('1,100.00');
        // Band: making 100 × 20% = +20 → 1,120 (the AED 1,000 gold is never marked up). Watch: 500 × 5% = +25 → 525.
        $this->get('/w/HUDA10')->assertOk()->assertSee('Huda Partner')->assertSee('1,120.00')->assertSee('525.00');
        $this->get('/product/band')->assertSee('1,120.00')->assertSee('You are shopping with');

        $order = $this->checkout();
        $this->assertTrue($order->affiliate_white_page);
        $this->assertSame('1645.00', $order->taxable_amount);   // 1,120 + 525
        $band = AffiliateCommission::where('basis', 'making')->sole();
        $this->assertSame('100.00', $band->base_amount);         // the shop's own making charge
        $this->assertSame('10.00', $band->commission_amount);    // 10% of making
        $this->assertSame('20.00', $band->markup_amount);
        $watch = AffiliateCommission::where('basis', 'total')->sole();
        $this->assertSame('500.00', $watch->base_amount);
        $this->assertSame('15.00', $watch->commission_amount);   // 3% of the shop price
        $this->assertSame('25.00', $watch->markup_amount);

        // Stored prices and other visitors are not affected.
        $this->assertSame('1100.00', Product::where('slug', 'band')->value('cached_subtotal'));
    }

    public function test_same_amount_on_every_piece(): void
    {
        $this->affiliate->update(['markup_mode' => 'amount', 'markup_amount' => 50]);
        $this->get('/w/HUDA10')->assertSee('1,150.00')->assertSee('550.00');
        $this->checkout();
        $this->assertSame(['50.00', '50.00'], AffiliateCommission::orderBy('id')->pluck('markup_amount')->all());
    }

    public function test_markup_is_capped_by_admin_and_affiliate_cannot_go_above_it(): void
    {
        $this->affiliate->update(['making_markup_percent' => 90]);   // above the 30% max set by admin
        $this->get('/w/HUDA10');
        $this->get('/product/band')->assertSee('1,130.00');           // capped at +30% of making

        $as = $this->actingAs($this->affiliate->user);
        $as->post('/affiliate/page', ['markup_mode' => 'percent', 'making_markup_percent' => 40, 'fixed_markup_percent' => 5])->assertSessionHasErrors('making_markup_percent');
        $as->post('/affiliate/page', ['markup_mode' => 'amount', 'markup_amount' => 150])->assertSessionHasErrors('markup_amount');
        $as->post('/affiliate/page', ['markup_mode' => 'amount', 'markup_amount' => 60, 'display_name' => 'Huda Gold'])->assertSessionHasNoErrors();
        $fresh = $this->affiliate->fresh();
        $this->assertSame('amount', $fresh->markup_mode);
        $this->assertSame('60.00', $fresh->markup_amount);
        $this->assertSame('Huda Gold', $fresh->display_name);

        $this->affiliate->update(['white_page_enabled' => false]);   // admin switches it off
        auth()->logout();
        $this->get('/product/band')->assertSee('1,100.00');
    }

    public function test_cancelled_or_unpaid_orders_earn_nothing(): void
    {
        $this->get('/?aff=HUDA10');
        $order = $this->checkout(['band']);
        $this->travel(31)->minutes();
        app(OrderService::class)->expireUnpaid($order);
        $this->assertSame('void', AffiliateCommission::sole()->status);
        $this->assertSame(0, $this->affiliate->totals()['pending'] + $this->affiliate->totals()['balance']);
    }

    public function test_affiliate_does_not_earn_on_own_purchase(): void
    {
        $this->actingAs($this->affiliate->user);
        $this->get('/?aff=HUDA10');
        $branch = Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        $this->post('/cart/add/band');
        $form = ['email' => 'huda@partner.ae', 'phone' => '+971501234567', 'name' => 'Huda', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => 'card'];
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');
        $this->post('/checkout', $form);
        $this->assertNull(Order::sole()->affiliate_id);
        $this->assertSame(0, AffiliateCommission::count());
    }

    public function test_collection_at_store_is_taken_off_the_balance_and_shown_on_dashboard(): void
    {
        $this->get('/?aff=HUDA10');
        $this->deliver($this->checkout());
        $admin = User::factory()->create(['is_admin' => true]);
        $svc = app(AffiliateService::class);

        $svc->payout($this->affiliate, '20.00', $admin, null, 'R-001');
        $this->assertSame(500, $this->affiliate->totals()['balance']);
        $this->expectExceptionMessage('more than the balance');
        try {
            $svc->payout($this->affiliate, '10.00', $admin);
        } finally {
            $this->actingAs($this->affiliate->user)->get('/affiliate')->assertOk()
                ->assertSee('Ready to collect')->assertSee('R-001')->assertSee('AED 5.00');
        }
    }

    public function test_admin_creates_affiliate_and_sees_reports(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        \Livewire\Livewire::actingAs($admin)->test(\App\Filament\Resources\AffiliateResource\Pages\CreateAffiliate::class)
            ->fillForm(['user_name' => 'Sara Agent', 'user_email' => 'sara@agent.ae', 'user_password' => 'Secret123', 'is_active' => true,
                'making_commission_percent' => 12, 'fixed_commission_percent' => 2, 'white_page_enabled' => false])
            ->call('create')->assertHasNoFormErrors();
        $sara = Affiliate::whereHas('user', fn ($q) => $q->where('email', 'sara@agent.ae'))->sole();
        $this->assertNotEmpty($sara->code);
        $this->assertFalse((bool) $sara->user->is_staff);
        $this->assertFalse((bool) $sara->user->is_admin);

        $this->actingAs($admin)->get('/admin/affiliates')->assertOk()->assertSee($sara->code);
        $this->actingAs($admin)->get('/admin/affiliate-commissions')->assertOk()->assertSee('Still to collect');
        $this->actingAs($admin)->get('/admin/affiliate-collections')->assertOk();
        $this->actingAs($admin)->get('/admin/affiliates/'.$sara->id.'/edit')->assertOk();

        $staff = User::factory()->create(['is_staff' => true]);
        $staff->forceFill(['permissions' => ['orders' => ['view']]])->save();
        $this->actingAs($staff)->get('/admin/affiliates')->assertForbidden();
        $this->actingAs($sara->user)->get('/admin')->assertForbidden();
    }

    public function test_affiliate_signs_in_like_a_customer_lands_on_dashboard_and_can_sign_out(): void
    {
        $this->affiliate->user->forceFill(['password' => 'Secret123'])->save();
        $this->post('/login', ['email' => 'huda@partner.ae', 'password' => 'Secret123'])->assertRedirect(route('affiliate.dashboard'));
        $this->get('/')->assertSee(route('affiliate.dashboard'), false)->assertSee(route('logout'), false);
        $this->get('/affiliate')->assertOk()->assertSee('HUDA10');

        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
        $this->post('/logout')->assertRedirect(route('home'));   // already signed out (old tab): no error
    }

    public function test_affiliate_previews_own_page_with_markup_but_never_earns_on_it(): void
    {
        $this->actingAs($this->affiliate->user);
        $this->get('/w/HUDA10')->assertOk()->assertSee('Preview of your page')->assertSee('1,120.00');
        $this->get('/product/band')->assertSee('1,100.00');   // preview only; not tracked as a customer visit
        $this->assertNull(session('aff'));
    }

    public function test_old_affiliates_get_an_amount_limit_and_form_explains_limits(): void
    {
        $this->affiliate->update(['max_markup_amount' => 0]);
        (require database_path('migrations/2026_10_21_000300_default_affiliate_amount_markup_limit.php'))->up();
        $this->assertSame('100.00', $this->affiliate->fresh()->max_markup_amount);

        $as = $this->actingAs($this->affiliate->user);
        $as->get('/affiliate')->assertSee('up to AED 100')->assertSee('up to 30%');
        $as->post('/affiliate/page', ['markup_mode' => 'amount', 'markup_amount' => 50])->assertSessionHasNoErrors();
        $as->post('/affiliate/page', ['markup_mode' => 'amount', 'markup_amount' => 500])->assertSessionHasErrors(['markup_amount' => 'The most the shop allows here is 100.']);

        $this->affiliate->fresh()->update(['max_markup_amount' => 0, 'max_making_markup_percent' => 0, 'max_fixed_markup_percent' => 0]);
        $this->actingAs($this->affiliate->user->fresh())->get('/affiliate')->assertSee('Mark-up is not switched on');
    }
}
