<?php

namespace Tests\Feature;

use App\Filament\Resources\AffiliateResource\Pages\ListAffiliates;
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
use Livewire\Livewire;
use Tests\TestCase;

class AffiliateStoneMarkupAndLoginAsTest extends TestCase
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
        // 4 g × 300 = 1,200 gold + 10% making 120 + stone 2,400 = 3,720
        Product::create(['name' => 'Diamond Ring', 'sku' => 'DR-1', 'slug' => 'diamond-ring', 'gold_category_id' => GoldCategory::where('name', '18K')->value('id'),
            'weight_grams' => 4, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stone_value' => 2400, 'stone_type' => 'Diamond',
            'stock_qty' => 3, 'is_active' => true]);
        $user = User::factory()->create(['name' => 'Huda Partner', 'email' => 'huda@partner.ae']);
        $this->affiliate = Affiliate::create(['user_id' => $user->id, 'code' => 'HUDA10', 'making_commission_type' => 'per_gram', 'commission_per_gram' => 5,
            'stone_commission_percent' => 2, 'fixed_commission_percent' => 3, 'white_page_enabled' => true, 'markup_mode' => 'percent',
            'making_markup_percent' => 20, 'fixed_markup_percent' => 0, 'stone_markup_percent' => 5, 'max_stone_markup_percent' => 10]);
    }

    public function test_white_page_marks_up_the_stone_and_commission_uses_the_shop_stone_value(): void
    {
        // white page: making 120 + 20% = 144, stone 2,400 + 5% = 2,520 → 1,200 + 144 + 2,520 = 3,864
        $this->get('/w/HUDA10');
        $this->get('/product/diamond-ring')->assertSee('3,864.00');

        $branch = Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        $this->post('/cart/add/diamond-ring');
        $form = ['email' => 'b@example.com', 'phone' => '+971501234567', 'name' => 'Buyer', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => 'card'];
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');
        $this->post('/checkout', $form);
        $line = AffiliateCommission::where('order_id', Order::latest('id')->value('id'))->firstOrFail();
        // commission: 4 g × 5 = 20 + 2% of the shop's stone 2,400 = 48 → 68; mark-up: 24 making + 120 stone = 144
        $this->assertSame('68.00', $line->commission_amount);
        $this->assertSame('144.00', $line->markup_amount);
    }

    public function test_affiliate_sees_the_working_out_and_their_rates(): void
    {
        $this->actingAs($this->affiliate->user)->get('/affiliate/share')->assertOk()
            ->assertSee('How is this calculated?')->assertSee('Commission the shop pays you')
            ->assertSee('4 g × AED 5.00 per gram = AED 20.00')->assertSee('Stone AED 2,400.00 × 2% = AED 48.00')
            ->assertSee('Stone AED 2,400.00 × 5% = AED 120.00');
    }

    public function test_affiliate_can_set_a_stone_markup_within_the_maximum(): void
    {
        $as = $this->actingAs($this->affiliate->user);
        $as->post('/affiliate/page', ['markup_mode' => 'percent', 'making_markup_percent' => 20, 'stone_markup_percent' => 15])->assertSessionHasErrors('stone_markup_percent');
        $as->post('/affiliate/page', ['markup_mode' => 'percent', 'making_markup_percent' => 20, 'stone_markup_percent' => 8])->assertSessionHasNoErrors();
        $this->assertSame('8.00', $this->affiliate->fresh()->stone_markup_percent);
    }

    public function test_admin_logs_in_as_the_affiliate_quietly_and_goes_back(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        Livewire::test(ListAffiliates::class)->callTableAction('loginAs', $this->affiliate)->assertRedirect(route('affiliate.dashboard'));

        $this->assertSame($this->affiliate->user_id, auth()->id());
        $this->assertNull($this->affiliate->user->fresh()->last_login_at);
        $this->get('/affiliate')->assertOk()->assertSee('Admin view — logged in as');
        $this->post('/checkout', [])->assertSessionHasErrors('checkout');

        $this->post('/login-as/leave')->assertRedirect(url('/admin/affiliates'));
        $this->assertSame($admin->id, auth()->id());
    }

    public function test_only_admins_can_log_in_as_and_never_as_another_admin(): void
    {
        $other = User::factory()->create(['is_admin' => true]);
        $this->assertFalse(\App\Support\Impersonation::allowed($this->affiliate->user, $other));
        $this->assertFalse(\App\Support\Impersonation::allowed(User::factory()->create(['is_admin' => true]), $other));
    }
}
