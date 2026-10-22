<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\Affiliate;
use App\Models\GoldCategory;
use App\Models\Product;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class SignupAffiliateMarkupTest extends TestCase
{
    use RefreshDatabase;

    private array $form = ['name' => 'Sara', 'email' => 'sara@example.com', 'password' => 'Secret123', 'password_confirmation' => 'Secret123', 'referral_code' => 'huda10'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Settings::set(['id_verification' => 'off']);
        // 5 g × 200 = 1,000 gold + 10% making 100 = AED 1,100; +20% of making on the white page = AED 1,120
        Product::create(['name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 3, 'is_active' => true]);
        $partner = User::factory()->create(['email' => 'huda@partner.ae']);
        Affiliate::create(['user_id' => $partner->id, 'code' => 'HUDA10', 'making_commission_percent' => 10, 'fixed_commission_percent' => 3,
            'white_page_enabled' => true, 'markup_mode' => 'percent', 'making_markup_percent' => 20, 'fixed_markup_percent' => 0]);
    }

    public function test_customer_who_signed_up_with_the_affiliate_code_always_sees_the_mark_up(): void
    {
        Settings::set(['affiliate_signup_markup' => true]);
        $this->post('/register', $this->form)->assertSessionHasNoErrors();
        $sara = User::where('email', 'sara@example.com')->sole();
        $this->assertSame(Affiliate::first()->id, $sara->signup_affiliate_id);

        $this->get('/product/band')->assertSee('1,120.00')->assertDontSee('1,100.00');
    }

    public function test_switched_off_in_settings_or_for_the_customer_shows_normal_prices(): void
    {
        $this->post('/register', $this->form);
        $this->get('/product/band')->assertSee('1,100.00');   // setting is off by default

        Settings::set(['affiliate_signup_markup' => true]);
        $sara = User::where('email', 'sara@example.com')->sole();
        $sara->forceFill(['affiliate_markup_on' => false])->save();
        Cache::flush();
        $this->actingAs($sara->fresh())->get('/product/band')->assertSee('1,100.00');
    }

    public function test_admin_can_switch_off_or_remove_the_link(): void
    {
        Settings::set(['affiliate_signup_markup' => true]);
        $this->post('/register', $this->form);
        $sara = User::where('email', 'sara@example.com')->sole();
        auth()->logout();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        Livewire::test(EditUser::class, ['record' => $sara->getRouteKey()])
            ->assertSee('Affiliate prices')->assertSee('HUDA10')
            ->fillForm(['affiliate_markup_on' => false])->call('save')->assertHasNoFormErrors();
        $this->assertFalse($sara->fresh()->affiliate_markup_on);
        $this->assertNull($sara->fresh()->markupAffiliate());

        Livewire::test(EditUser::class, ['record' => $sara->getRouteKey()])
            ->callAction('removeAffiliateLink');
        $this->assertNull($sara->fresh()->signup_affiliate_id);
    }

    public function test_mobile_verified_in_the_sign_up_form_skips_the_verify_page(): void
    {
        Settings::set(['phone_verification' => true]);
        $this->withSession(['verified_phones' => ['+971501112233']])
            ->post('/register', $this->form + ['phone' => '0501112233'])->assertRedirect(route('account'));
        $this->assertNotNull(User::where('email', 'sara@example.com')->value('phone_verified_at'));
    }

    public function test_sign_up_page_offers_mobile_verification(): void
    {
        Settings::set(['phone_verification' => true]);
        $this->get('/register')->assertOk()->assertSee('verify it now with a WhatsApp code');
    }
}
