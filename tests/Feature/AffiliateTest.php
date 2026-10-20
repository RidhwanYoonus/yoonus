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
        $this->actingAs($this->affiliate->user->fresh())->get('/affiliate')->assertSee('no limit');   // maximum 0 = no limit
    }

    public function test_commission_can_be_a_fixed_amount_per_gram_on_gold_items(): void
    {
        $this->affiliate->update(['making_commission_type' => 'per_gram', 'commission_per_gram' => 2.5, 'white_page_enabled' => false]);
        $this->get('/?aff=HUDA10');
        $this->post('/cart/add/band', ['qty' => 2]);   // 2 × 5 g
        $order = $this->checkout(['watch']);

        $band = AffiliateCommission::where('basis', 'gram')->sole();
        $this->assertSame('10.00', $band->base_amount);      // grams
        $this->assertSame('25.00', $band->amount);           // 10 g × AED 2.50
        $this->assertSame('15.00', AffiliateCommission::where('basis', 'total')->sole()->amount);   // fixed item still 3%
        $this->actingAs(\App\Models\User::factory()->create(['is_admin' => true]))->get('/admin/affiliate-commissions')->assertSee('10.00 g × AED 2.50 per gram');
        $this->actingAs($this->affiliate->user->fresh())->get('/affiliate')->assertSee('AED 2.5 per gram');
    }

    public function test_admin_sees_affiliate_orders_highlighted_with_full_calculation(): void
    {
        $this->get('/w/HUDA10');
        $order = $this->checkout();
        $plain = Order::create(collect($order->getAttributes())->except(['id', 'number', 'affiliate_id', 'affiliate_white_page'])->all() + ['number' => 'JW-PLAIN']);
        $admin = User::factory()->create(['is_admin' => true]);

        $list = $this->actingAs($admin)->get('/admin/orders')->assertOk()->assertSee('HUDA10')->assertSee('affiliate-order-row');
        $this->assertSame(1, substr_count($list->getContent(), 'affiliate-order-row"') + substr_count($list->getContent(), 'affiliate-order-row '));
        \Livewire\Livewire::actingAs($admin)->test(\App\Filament\Resources\OrderResource\Pages\ListOrders::class)
            ->filterTable('affiliate_id', true)->assertCanSeeTableRecords([$order])->assertCanNotSeeTableRecords([$plain]);

        $this->actingAs($admin)->get('/admin/orders/'.$order->id)->assertOk()
            ->assertSee('Calculation per item')->assertSee('Making AED 100.00 × 10%')->assertSee('Item total AED 500.00 × 3%')
            ->assertSee('Huda Partner')->assertSee('Shop keeps')->assertSee('70.00')->assertSee('1,575.00');   // affiliate 10 + 20 + 15 + 25; shop 1,645 − 70
    }

    public function test_same_amount_per_piece_for_every_item(): void
    {
        $this->affiliate->update(['commission_mode' => 'per_piece', 'commission_per_piece' => 10, 'white_page_enabled' => false]);
        $this->get('/?aff=HUDA10');
        $this->post('/cart/add/watch', ['qty' => 2]);   // 2 fixed-price pieces
        $this->checkout(['band']);                     // + 1 gold piece
        $this->assertSame(['piece'], AffiliateCommission::distinct()->pluck('basis')->all());
        $this->assertSame(3000, $this->affiliate->totals()['pending']);   // 3 × AED 10
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin/affiliate-commissions')->assertSee('2 pc × AED 10.00 per piece');
    }

    public function test_fixed_price_items_can_earn_a_fixed_amount_per_piece(): void
    {
        $this->affiliate->update(['fixed_commission_type' => 'per_piece', 'fixed_commission_amount' => 12, 'white_page_enabled' => false]);
        $this->get('/?aff=HUDA10');
        $this->checkout();
        $this->assertSame('12.00', AffiliateCommission::where('basis', 'piece')->sole()->amount);   // watch
        $this->assertSame('10.00', AffiliateCommission::where('basis', 'making')->sole()->amount);  // band still 10% of making
    }

    public function test_white_page_markup_per_gram_and_per_piece_and_zero_max_means_no_limit(): void
    {
        // Band 5 g: + AED 3/g = 15 → 1,115. Watch: + AED 40 per piece → 540.
        $this->affiliate->update(['markup_mode' => 'percent', 'making_markup_type' => 'per_gram', 'making_markup_per_gram' => 3, 'max_making_markup_per_gram' => 0,
            'fixed_markup_type' => 'per_piece', 'fixed_markup_amount' => 40, 'max_fixed_markup_amount' => 25]);
        $this->get('/w/HUDA10')->assertSee('1,115.00')->assertSee('525.00');   // per piece capped at AED 25; per gram has no limit
        $this->affiliate->update(['max_fixed_markup_amount' => 0]);
        $this->get('/w/HUDA10')->assertSee('540.00');
        $order = $this->checkout();
        $this->assertSame(['15.00', '40.00'], AffiliateCommission::orderBy('id')->pluck('markup_amount')->all());

        $as = $this->actingAs($this->affiliate->user->fresh());
        $as->get('/affiliate')->assertSee('AED per gram')->assertSee('AED per piece');
        $as->post('/affiliate/page', ['markup_mode' => 'percent', 'making_markup_type' => 'per_gram', 'making_markup_per_gram' => 999,
            'fixed_markup_type' => 'percent', 'fixed_markup_percent' => 15])->assertSessionHasErrors('fixed_markup_percent')   // max 10%
            ->assertSessionDoesntHaveErrors('making_markup_per_gram');                                                        // max 0 = no limit
        $as->post('/affiliate/page', ['markup_mode' => 'percent', 'making_markup_type' => 'per_gram', 'making_markup_per_gram' => 999,
            'fixed_markup_type' => 'percent', 'fixed_markup_percent' => 8])->assertSessionHasNoErrors();
        $this->assertSame('999.00', $this->affiliate->fresh()->making_markup_per_gram);
    }

    public function test_admin_can_disable_and_activate_an_affiliate(): void
    {
        $this->affiliate->update(['coupon_discount_percent' => 10]);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        \Livewire\Livewire::test(\App\Filament\Resources\AffiliateResource\Pages\ListAffiliates::class)
            ->assertTableActionVisible('toggleActive', $this->affiliate)->callTableAction('toggleActive', $this->affiliate);
        $this->assertFalse($this->affiliate->fresh()->is_active);
        $this->assertFalse((bool) \App\Models\Coupon::where('code', 'HUDA10')->value('is_active'), 'their code stops working');
        auth()->logout();
        $this->get('/w/HUDA10')->assertNotFound();
        $this->actingAs($this->affiliate->user)->get(route('affiliate.dashboard'))->assertOk()->assertSee('paused');

        $this->actingAs($admin);
        \Livewire\Livewire::test(\App\Filament\Resources\AffiliateResource\Pages\EditAffiliate::class, ['record' => $this->affiliate->getRouteKey()])
            ->callAction('toggleActive');
        $this->assertTrue($this->affiliate->fresh()->is_active);
        $this->assertTrue((bool) \App\Models\Coupon::where('code', 'HUDA10')->value('is_active'));
    }
}
