<?php

namespace Tests\Feature;

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

class OrderUndoStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Settings::set(['id_verification' => 'off', 'handover_otp' => false]);
        Product::create(['name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 1]);
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    private function paidOrder(): Order
    {
        $branch = Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        $this->post('/cart/add/band');
        $form = ['email' => 'b@example.com', 'phone' => '+971501234567', 'name' => 'B', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => 'card'];
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');
        $this->post('/checkout', $form);
        $order = Order::sole();
        app(OrderService::class)->markPaid($order, 'stripe', 'pi_1');

        return $order->fresh();
    }

    public function test_undo_delivered_step_by_step_and_restores_side_effects(): void
    {
        $svc = app(OrderService::class);
        $order = $this->paidOrder();
        foreach (['packed', 'ready', 'delivered'] as $to) {
            $svc->transition($order->fresh(), $to, $this->admin);
        }
        $this->assertTrue((bool) Product::first()->hidden_after_sale);   // last piece handed over

        $svc->revertStatus($order->fresh(), $this->admin, 'clicked by mistake');
        $order->refresh();
        $this->assertSame('ready', $order->status);
        $this->assertNull($order->handover_verified_at);
        $this->assertFalse((bool) Product::first()->hidden_after_sale);
        $this->assertStringContainsString('clicked by mistake', $order->statusHistory()->first()->note);

        $this->assertSame('packed', $svc->revertTarget($order));   // can go back one more step
        $svc->revertStatus($order, $this->admin, 'again');
        $this->assertSame('packed', $order->fresh()->status);
        $this->assertSame('paid', $svc->revertTarget($order->fresh()));
        $svc->revertStatus($order->fresh(), $this->admin, 'again');
        $this->assertNull($svc->revertTarget($order->fresh()));   // never back to unpaid
    }

    public function test_undo_cancel_takes_stock_again_or_refuses(): void
    {
        $svc = app(OrderService::class);
        $order = $this->paidOrder();
        $svc->transition($order, 'cancelled', $this->admin);
        $this->assertSame(1, Product::first()->stock_qty);

        $svc->revertStatus($order->fresh(), $this->admin, 'wrong order');
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(0, Product::first()->stock_qty);

        $svc->transition($order->fresh(), 'cancelled', $this->admin);
        Product::first()->update(['stock_qty' => 0]);   // sold elsewhere meanwhile
        $this->expectExceptionMessage('Not enough stock');
        $svc->revertStatus($order->fresh(), $this->admin, 'oops');
    }

    public function test_only_admins_see_the_undo_button(): void
    {
        $svc = app(OrderService::class);
        $order = $this->paidOrder();
        $svc->transition($order, 'packed', $this->admin);
        $this->actingAs($this->admin)->get('/admin/orders/'.$order->id)->assertSee('Undo last status');
        $staff = User::factory()->create(['is_staff' => true]);
        $staff->forceFill(['permissions' => ['orders' => ['view', 'edit']]])->save();
        $this->actingAs($staff)->get('/admin/orders/'.$order->id)->assertDontSee('Undo last status');
    }
}
