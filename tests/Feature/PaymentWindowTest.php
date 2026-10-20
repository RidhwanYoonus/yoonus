<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Coupon;
use App\Models\GoldCategory;
use App\Models\Order;
use App\Models\Product;
use App\Shop\OrderService;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Settings::set(['id_verification' => 'off', 'payment_window_minutes' => 30, 'cod_enabled' => true]);
        Product::create(['name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 3]);
    }

    private function place(string $method = 'card', array $extra = []): Order
    {
        $branch = Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        $this->post('/cart/add/band');
        $form = ['email' => 'm@example.com', 'phone' => '+971501234567', 'name' => 'Mariam', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => $method] + $extra;
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');
        $this->post('/checkout', $form);

        return Order::latest('id')->first();
    }

    public function test_online_order_holds_price_only_for_the_window_then_is_cancelled_and_stock_released(): void
    {
        $order = $this->place();
        $this->assertSame(2, Product::first()->stock_qty);
        $this->assertEqualsWithDelta(now()->addMinutes(30)->timestamp, $order->payment_expires_at->timestamp, 5);
        $this->get("/orders/{$order->number}")->assertOk()->assertSee('Price held for');

        $this->travel(31)->minutes();
        $this->artisan('shop:expire-unpaid')->assertSuccessful();

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('expired', $order->payment_status);
        $this->assertNull($order->invoice_number);
        $this->assertSame(3, Product::first()->stock_qty);
    }

    public function test_pay_now_after_the_window_refuses_old_price_and_refills_the_bag(): void
    {
        $order = $this->place();
        $this->travel(31)->minutes();
        $this->setGoldRates(['22K' => '250.00']);   // gold went up meanwhile

        $this->post("/orders/{$order->number}/pay")->assertRedirect(route('cart.show'));
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->get('/cart')->assertSee('Band');   // back in the bag, priced at today's rate
    }

    public function test_coupon_use_is_given_back_when_unpaid_order_expires(): void
    {
        Coupon::create(['code' => 'TEN', 'name' => 'Ten', 'type' => 'percentage', 'value' => 10, 'scope' => 'cart']);
        $order = $this->place('card', ['coupon' => 'TEN']);
        $this->assertSame(1, Coupon::first()->times_used);
        $this->travel(31)->minutes();
        app(OrderService::class)->expireUnpaid($order);
        $this->assertSame(0, Coupon::first()->times_used);
    }

    public function test_payment_arriving_just_after_expiry_is_still_accepted_and_flagged(): void
    {
        $order = $this->place();
        $this->travel(31)->minutes();
        app(OrderService::class)->expireUnpaid($order);

        app(OrderService::class)->markPaid($order->fresh(), 'stripe', 'pi_late');
        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertSame(2, Product::first()->stock_qty);
        $this->assertStringContainsString('after the payment time had run out', $order->statusHistory()->latest('id')->value('note'));
    }

    public function test_cash_on_delivery_and_paid_orders_are_never_expired(): void
    {
        $cod = $this->place();   // cash on delivery needs a delivery address; turn a pickup order into one
        $cod->forceFill(['payment_method' => 'cod', 'payment_status' => 'cod', 'payment_expires_at' => null])->save();
        $card = $this->place();
        app(OrderService::class)->markPaid($card, 'stripe', 'pi_1');
        $this->travel(2)->hours();
        $this->artisan('shop:expire-unpaid');
        $this->assertSame('pending', $cod->fresh()->status);
        $this->assertSame('paid', $card->fresh()->status);
    }

    public function test_admin_sets_the_time_to_pay_in_settings(): void
    {
        $admin = \App\Models\User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('Gold price hold — time to pay')->assertSee('Customer must pay within');

        \Livewire\Livewire::actingAs($admin)->test(\App\Filament\Pages\Settings::class)
            ->set('data.payment_window_minutes', 15)->call('save')->assertHasNoErrors();
        $this->assertSame(15, OrderService::paymentWindow());

        $this->actingAs($admin);   // the next order is held for 15 minutes
        $order = $this->place();
        $this->assertEqualsWithDelta(now()->addMinutes(15)->timestamp, $order->payment_expires_at->timestamp, 5);
    }
}
