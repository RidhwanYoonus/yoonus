<?php

namespace Tests\Feature;

use App\Affiliates\AffiliateService;
use App\Mail\ShopMessage;
use App\Models\Affiliate;
use App\Models\AffiliateAdjustment;
use App\Models\AffiliateApplication;
use App\Models\AffiliateCampaign;
use App\Models\AffiliateClick;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePriceLock;
use App\Models\Branch;
use App\Models\GoldCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreVisit;
use App\Models\User;
use App\Shop\OrderService;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AffiliateGrowthTest extends TestCase
{
    use RefreshDatabase;

    private Affiliate $affiliate;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Settings::set(['id_verification' => 'off', 'handover_otp' => false, 'affiliate_tiers' => []]);
        // Band: 5 g × 200 = 1,000 gold + 10% making 100 → 1,100 before VAT
        Product::create(['name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 9, 'is_featured' => true]);
        $user = User::factory()->create(['name' => 'Huda Partner', 'email' => 'huda@partner.ae', 'phone' => '+971501110000']);
        $this->affiliate = Affiliate::create(['user_id' => $user->id, 'code' => 'HUDA10', 'making_commission_percent' => 10, 'fixed_commission_percent' => 3]);
    }

    private function checkout(int $qty = 1, array $extra = []): Order
    {
        $branch = Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        $this->post('/cart/add/band', ['qty' => $qty]);
        $form = ['email' => 'buyer'.uniqid().'@example.com', 'phone' => '+971501234567', 'name' => 'Buyer', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => 'card'] + $extra;
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

    public function test_affiliate_gets_share_tools_on_product_page_and_dashboard(): void
    {
        $this->actingAs($this->affiliate->user);
        $this->get('/product/band')->assertSee('Share this piece and earn')->assertSee('aff=HUDA10', false);
        $this->get('/affiliate')->assertOk()->assertSee('Ready-made posts')->assertSee('Band')
            ->assertSee('Share today')->assertSee('22K: AED 200.00/g')->assertSee('Build your team')->assertSee('affiliate/join?ref=HUDA10', false);
    }

    public function test_affiliate_coupon_code_gives_discount_and_counts_without_the_link(): void
    {
        $this->affiliate->update(['coupon_discount_percent' => 5]);
        $order = $this->checkout(1, ['coupon' => 'huda10']);
        $this->assertSame($this->affiliate->id, $order->affiliate_id);
        $this->assertSame('55.00', $order->discount_total);   // 5% of 1,100
        $this->assertSame(1, AffiliateCommission::count());
    }

    public function test_affiliate_is_notified_of_new_sale_and_money_ready_to_collect(): void
    {
        $this->get('/?aff=HUDA10');
        $order = $this->checkout();
        Mail::assertSent(ShopMessage::class, fn ($m) => $m->hasTo('huda@partner.ae') && str_contains($m->subjectLine, 'New sale'));
        $this->deliver($order);
        Mail::assertSent(ShopMessage::class, fn ($m) => $m->hasTo('huda@partner.ae') && str_contains($m->subjectLine, 'ready to collect'));
    }

    public function test_level_and_booster_add_extra_commission(): void
    {
        Settings::set(['affiliate_tiers' => [['name' => 'Silver', 'min_sales' => 0, 'extra_percent' => 0], ['name' => 'Gold', 'min_sales' => 1000, 'extra_percent' => 50]]]);
        $this->get('/?aff=HUDA10');
        $this->checkout();
        $this->assertSame('0.00', AffiliateCommission::latest('id')->first()->bonus_amount);   // first sale: Silver
        $this->checkout();
        $second = AffiliateCommission::latest('id')->first();
        $this->assertSame('5.00', $second->bonus_amount);   // Gold: +50% of AED 10
        $this->assertStringContainsString('Gold', $second->bonus_note);

        AffiliateCampaign::create(['name' => 'Eid', 'type' => 'booster', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'multiplier' => 2]);
        $this->checkout();
        $this->assertSame('15.00', AffiliateCommission::latest('id')->first()->bonus_amount);   // +5 Gold, +10 booster
        $this->assertSame('25.00', AffiliateCommission::latest('id')->first()->amount);
        $this->actingAs($this->affiliate->user)->get('/affiliate')->assertSee('Your level')->assertSee('Gold')->assertSee('Eid');
    }

    public function test_target_bonus_is_paid_once_when_reached(): void
    {
        $campaign = AffiliateCampaign::create(['name' => 'Sell 2', 'type' => 'target', 'starts_at' => now()->subDay(), 'ends_at' => now()->addWeek(),
            'target_metric' => 'pieces', 'target_value' => 2, 'bonus_amount' => 100]);
        $this->get('/?aff=HUDA10');
        $this->deliver($this->checkout(1));
        $this->assertSame(0, AffiliateAdjustment::count());
        $this->deliver($this->checkout(1));
        $bonus = AffiliateAdjustment::sole();
        $this->assertSame('100.00', $bonus->amount);
        $this->assertSame($campaign->id, $bonus->campaign_id);
        $this->assertSame(2000 + 10000, $this->affiliate->totals()['balance']);   // 2 × AED 10 + AED 100
        app(AffiliateService::class)->evaluateTargets($this->affiliate);
        $this->assertSame(1, AffiliateAdjustment::count());
    }

    public function test_price_lock_link_holds_todays_price_until_it_expires(): void
    {
        $this->affiliate->update(['can_lock_price' => true]);
        $this->actingAs($this->affiliate->user)->post('/affiliate/lock', ['product_id' => Product::first()->id])->assertSessionHas('new_lock');
        $lock = AffiliatePriceLock::sole();
        auth()->logout();

        $this->setGoldRates(['22K' => '300.00']);   // gold jumps: 1,500 + 150 = 1,650
        $this->get('/product/band')->assertSee('1,650.00');
        $this->get('/l/'.$lock->token)->assertRedirect(route('product.show', 'band'));
        $this->get('/product/band')->assertSee('1,100.00')->assertSee('Price held for you');
        $order = $this->checkout();
        $this->assertSame('1100.00', $order->taxable_amount);
        $this->assertSame($this->affiliate->id, $order->affiliate_id);
        $this->assertSame(1, $lock->fresh()->uses);

        $this->get('/l/'.$lock->token);
        $this->travel(25)->hours();
        \App\Affiliates\PriceLocks::flush();
        $this->get('/product/band')->assertSee('1,650.00');
    }

    public function test_store_visit_booking_and_in_store_sale_credit(): void
    {
        $this->get('/?aff=HUDA10');
        $this->post('/visit', ['name' => 'Mona', 'phone' => '+971509990000', 'product_id' => Product::first()->id, 'preferred_at' => now()->addDay()->format('Y-m-d H:i')])
            ->assertSessionHas('status');
        $visit = StoreVisit::sole();
        $this->assertSame($this->affiliate->id, $visit->affiliate_id);

        app(AffiliateService::class)->storeSale($this->affiliate, ['item_type' => 'gold', 'pieces' => 1, 'making_charge' => 300, 'weight_grams' => 10, 'receipt' => 'R-77'], null);
        $this->assertSame(3000, $this->affiliate->totals()['balance']);   // 10% of AED 300 making
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/store-visits')->assertOk()->assertSee('Mona');
        $this->actingAs($this->affiliate->user->fresh())->get('/affiliate')->assertSee('In-store sale')->assertSee('R-77')->assertSee('Mona');
    }

    public function test_clicks_and_product_views_are_counted_once_per_visitor(): void
    {
        $this->get('/?aff=HUDA10');
        $this->get('/?aff=HUDA10');
        $this->get('/product/band');
        $this->get('/product/band');
        $this->assertSame(1, AffiliateClick::where('type', 'visit')->count());
        $this->assertSame(1, AffiliateClick::where('type', 'product')->count());
        $this->checkout();
        $this->actingAs($this->affiliate->user)->get('/affiliate')->assertSee('Link visits')->assertSee('100%');
    }

    public function test_join_link_application_approval_and_team_commission(): void
    {
        Settings::set(['affiliate_recruiter_percent' => 10]);
        $this->get('/affiliate/join?ref=HUDA10')->assertOk()->assertSee('Huda Partner');
        $this->post('/affiliate/join', ['name' => 'Sara New', 'email' => 'sara@new.ae', 'phone' => '+971502223333', 'ref' => 'HUDA10'])->assertSessionHas('status');
        $app = AffiliateApplication::sole();
        $this->assertSame($this->affiliate->id, $app->referred_by_id);

        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $sara = \App\Filament\Resources\AffiliateApplicationResource::approve($app);
        auth()->logout();
        $this->assertSame($this->affiliate->id, $sara->recruited_by_id);
        Mail::assertSent(ShopMessage::class, fn ($m) => $m->hasTo('sara@new.ae'));

        $this->get('/?aff='.$sara->code);
        $order = $this->checkout();
        $team = AffiliateCommission::where('affiliate_id', $this->affiliate->id)->sole();
        $this->assertSame('team', $team->basis);
        $this->assertSame('1.00', $team->amount);   // 10% of Sara's AED 10
        $this->deliver($order);
        $this->assertSame(100, $this->affiliate->fresh()->totals()['balance']);
    }

    public function test_admin_growth_pages_load(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        foreach (['/admin/affiliate-campaigns', '/admin/store-visits', '/admin/affiliate-applications', '/admin/affiliates', '/admin/affiliates/'.$this->affiliate->id.'/edit', '/admin/settings'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_customer_discount_with_affiliate_code_is_taken_from_their_commission(): void
    {
        $this->affiliate->update(['coupon_discount_percent' => 5]);   // 5% of 1,100 = 55
        $this->affiliate->update(['commission_mode' => 'per_piece', 'commission_per_piece' => 100]);
        $order = $this->checkout(1, ['coupon' => 'HUDA10']);
        $line = AffiliateCommission::sole();
        $this->assertSame('100.00', $line->commission_amount);
        $this->assertSame('55.00', $line->coupon_deduction);
        $this->assertSame('45.00', $line->amount);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/orders/'.$order->id)->assertSee('Their code discount')->assertSee('was taken from their commission');
        $this->actingAs($admin)->get('/admin/affiliate-commissions')->assertSee('Customer discount (their code)');
    }

    public function test_discount_never_takes_affiliate_below_zero(): void
    {
        $this->affiliate->update(['coupon_discount_percent' => 20]);   // 220 discount, only AED 10 commission
        $this->checkout(1, ['coupon' => 'HUDA10']);
        $line = AffiliateCommission::sole();
        $this->assertSame('10.00', $line->coupon_deduction);
        $this->assertSame('0.00', $line->amount);
    }

    public function test_booster_can_target_several_categories_and_one_item_type(): void
    {
        $rings = \App\Models\Category::create(['name' => 'Rings', 'slug' => 'rings']);
        $other = \App\Models\Category::create(['name' => 'Bangles', 'slug' => 'bangles']);
        Product::first()->update(['category_id' => $rings->id]);
        $booster = AffiliateCampaign::create(['name' => 'Rings x2', 'type' => 'booster', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
            'multiplier' => 2, 'category_ids' => [$other->id, $rings->id], 'item_type' => 'gold']);
        $this->get('/?aff=HUDA10');
        $this->checkout();
        $this->assertSame('10.00', AffiliateCommission::latest('id')->first()->bonus_amount);

        $booster->update(['item_type' => 'fixed']);   // only fixed-price items: the gold band no longer qualifies
        $this->checkout();
        $this->assertSame('0.00', AffiliateCommission::latest('id')->first()->bonus_amount);

        $booster->update(['item_type' => 'all', 'category_ids' => [$other->id]]);
        $this->checkout();
        $this->assertSame('0.00', AffiliateCommission::latest('id')->first()->bonus_amount);
        $this->assertSame('Bangles', $booster->fresh()->coverageLabel());
    }
}
