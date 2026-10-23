<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\ViewOrder;
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

class AffiliateEarningsToolsTest extends TestCase
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
            'making_markup_percent' => 20, 'fixed_markup_percent' => 0]);
    }

    private function order(): Order
    {
        $branch = Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        $this->get('/?aff=HUDA10');
        $this->post('/cart/add/diamond-ring');
        $form = ['email' => 'b@example.com', 'phone' => '+971501234567', 'name' => 'Buyer', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => 'card'];
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');
        $this->post('/checkout', $form);

        return Order::latest('id')->firstOrFail();
    }

    public function test_by_gram_commission_adds_a_percent_of_the_stone_value(): void
    {
        $order = $this->order();
        // 4 g × AED 5 = 20 + 2% of AED 2,400 stone = 48 → 68
        $this->assertSame('68.00', AffiliateCommission::where('order_id', $order->id)->value('commission_amount'));
    }

    public function test_affiliate_sees_what_each_piece_earns_including_white_page_markup(): void
    {
        $this->actingAs($this->affiliate->user)->get('/affiliate/share')->assertOk()
            ->assertSee('Your commission')->assertSee('68.00')   // 20 by gram + 48 stone
            ->assertSee('Your white-page mark-up')->assertSee('24.00');   // 20% of AED 120 making
    }

    public function test_admin_corrects_or_removes_the_affiliate_commission_of_an_order(): void
    {
        $order = $this->order();
        auth()->logout();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        $line = AffiliateCommission::where('order_id', $order->id)->firstOrFail();

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->mountAction('affiliateCommission')
            ->setActionData(['lines' => [['id' => $line->id, 'label' => 'x', 'locked' => false, 'commission_amount' => 50, 'markup_amount' => 0, 'bonus_amount' => 0]],
                'affiliate_id' => $this->affiliate->id, 'reason' => 'Wrong rate'])
            ->callMountedAction()->assertHasNoActionErrors();
        $this->assertSame('50.00', $line->fresh()->amount);
        $this->assertStringContainsString('Wrong rate', $order->statusHistory()->latest('id')->value('note'));

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->mountAction('affiliateCommission')
            ->setActionData(['affiliate_id' => null, 'reason' => 'Customer complaint'])
            ->callMountedAction()->assertHasNoActionErrors();
        $this->assertNull($order->fresh()->affiliate_id);
        $this->assertSame('void', $line->fresh()->status);
    }

    public function test_last_login_is_shown_for_affiliates(): void
    {
        $this->post('/login', ['email' => 'huda@partner.ae', 'password' => 'password']);
        $this->assertNotNull($this->affiliate->user->fresh()->last_login_at);
        auth()->logout();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/affiliates')->assertOk()->assertSee('Last login');
    }
}
