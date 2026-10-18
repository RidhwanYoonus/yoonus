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

class StockAndStaffAccessTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Settings::set(['handover_otp' => false, 'id_verification' => 'off']);
        $this->product = Product::create(['name' => 'Last Ring', 'sku' => 'L-1', 'slug' => 'last-ring', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 1]);
    }

    private function buyLastPiece(): Order
    {
        $branch = Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        $this->post('/cart/add/last-ring');
        $form = ['email' => 'a@example.com', 'phone' => '+971501234567', 'name' => 'A', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => 'card'];
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');
        $this->post('/checkout', $form)->assertSessionHasNoErrors();

        return Order::latest('id')->first();
    }

    public function test_last_piece_shows_sold_then_hides_after_handover_and_returns_on_restock(): void
    {
        $order = $this->buyLastPiece();
        $this->assertSame(0, $this->product->fresh()->stock_qty);

        // Sold: still listed, but cannot be ordered again.
        $this->get('/shop')->assertSee('Last Ring')->assertSee('Sold');
        $this->get('/product/last-ring')->assertOk()->assertDontSee('id="add-to-bag"', false);
        $this->post('/cart/add/last-ring')->assertSessionHasErrors('cart');

        $svc = app(OrderService::class);
        foreach (['paid', 'packed', 'ready', 'delivered'] as $s) {
            $svc->transition($order->refresh(), $s);
        }
        $this->assertTrue($this->product->fresh()->hidden_after_sale);
        $this->get('/shop')->assertDontSee('Last Ring');
        $this->get('/product/last-ring')->assertNotFound();

        $this->product->fresh()->update(['stock_qty' => 2]);
        $this->assertFalse($this->product->fresh()->hidden_after_sale);
        $this->get('/shop')->assertSee('Last Ring');
    }

    public function test_piece_with_stock_left_stays_listed_after_delivery(): void
    {
        $this->product->update(['stock_qty' => 2]);
        $order = $this->buyLastPiece();
        foreach (['paid', 'packed', 'ready', 'delivered'] as $s) {
            app(OrderService::class)->transition($order->refresh(), $s);
        }
        $this->assertFalse($this->product->fresh()->hidden_after_sale);
        $this->get('/shop')->assertSee('Last Ring');
    }

    public function test_staff_see_only_their_areas_and_actions(): void
    {
        $staff = User::factory()->create();
        $staff->forceFill(['is_staff' => true, 'permissions' => ['orders' => ['view'], 'products' => ['view', 'edit']]])->save();
        $this->actingAs($staff);

        $this->get('/admin/orders')->assertOk();
        $this->get('/admin/products')->assertOk();
        $this->get('/admin/products/create')->assertForbidden();          // no "add" right
        $this->get('/admin/products/last-ring/edit')->assertOk();
        $this->get('/admin/coupons')->assertForbidden();
        $this->get('/admin/settings')->assertForbidden();
        $this->get('/admin/staff')->assertForbidden();                    // admins only
        $this->get('/admin/users')->assertForbidden();
    }

    public function test_customers_cannot_open_admin_and_customer_screen_has_no_admin_switch(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();

        $admin = User::factory()->create(['is_admin' => true]);
        $customer = User::factory()->create(['name' => 'Fatima Customer']);
        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('Fatima Customer')->assertDontSee($admin->email);
        $this->actingAs($admin)->get('/admin/users/'.$customer->id.'/edit')->assertOk()->assertDontSee('Admin panel access');
        $this->actingAs($admin)->get('/admin/staff')->assertOk()->assertSee($admin->email)->assertDontSee('Fatima Customer');
        $this->actingAs($admin)->get('/admin/staff/create')->assertOk()->assertSee('Start from a job role');
    }
}
