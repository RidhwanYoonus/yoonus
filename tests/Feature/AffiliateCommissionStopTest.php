<?php

namespace Tests\Feature;

use App\Filament\Resources\AffiliateResource\Pages\ListAffiliates;
use App\Filament\Resources\UserResource\Pages\EditUser;
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
use Livewire\Livewire;
use Tests\TestCase;

class AffiliateCommissionStopTest extends TestCase
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
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 9, 'is_active' => true]);
        $partner = User::factory()->create(['email' => 'huda@partner.ae']);
        $this->affiliate = Affiliate::create(['user_id' => $partner->id, 'code' => 'HUDA10', 'making_commission_percent' => 10, 'fixed_commission_percent' => 3]);
    }

    private function buy(User $customer, bool $viaLink = false): Order
    {
        $this->actingAs($customer);
        if ($viaLink) {
            $this->get('/?aff=HUDA10');
        }
        $branch = Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        $this->post('/cart/add/band');
        $form = ['email' => $customer->email, 'phone' => '+971501234567', 'name' => 'C', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => 'card'];
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');
        $this->post('/checkout', $form);
        \App\Models\Cart::whereNotNull('order_id')->update(['converted_at' => now()]);

        return Order::latest('id')->firstOrFail();
    }

    public function test_customer_with_commission_off_earns_nothing_even_through_an_affiliate_link(): void
    {
        $customer = User::factory()->create(['affiliate_commission_off' => true]);
        $order = $this->buy($customer, viaLink: true);
        $this->assertNull($order->affiliate_id);
        $this->assertSame(0, AffiliateCommission::count());
    }

    public function test_remove_link_can_also_stop_and_cancel_pending_commission_and_it_stays_cancelled(): void
    {
        $customer = User::factory()->create(['signup_affiliate_id' => $this->affiliate->id]);
        $order = $this->buy($customer);
        $this->assertSame('pending', AffiliateCommission::where('order_id', $order->id)->value('status'));
        auth()->logout();

        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Livewire::test(EditUser::class, ['record' => $customer->getRouteKey()])
            ->callAction('removeAffiliateLink', ['stop' => true, 'void_pending' => true]);

        $this->assertTrue($customer->fresh()->affiliate_commission_off);
        $this->assertSame('void', AffiliateCommission::where('order_id', $order->id)->value('status'));
        // Delivered later: stays cancelled.
        $svc = app(OrderService::class);
        $svc->markPaid($order->fresh(), 'stripe', 'pi_x');
        app(\App\Affiliates\AffiliateService::class)->sync($order->fresh()->forceFill(['status' => 'delivered']));
        $this->assertSame('void', AffiliateCommission::where('order_id', $order->id)->value('status'));
    }

    public function test_disabling_an_affiliate_can_cancel_their_pending_commission(): void
    {
        $order = $this->buy(User::factory()->create(), viaLink: true);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Livewire::test(ListAffiliates::class)->callTableAction('toggleActive', $this->affiliate, ['void_pending' => true]);

        $this->assertFalse($this->affiliate->fresh()->is_active);
        $this->assertSame('void', AffiliateCommission::where('order_id', $order->id)->value('status'));
    }
}
