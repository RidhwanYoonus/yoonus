<?php

namespace Tests\Feature;

use App\Mail\ShopMessage;
use App\Models\Branch;
use App\Models\GoldCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use App\Shop\OrderService;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class IdVerificationAndHandoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Product::create(['name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 5]);
    }

    private function delivery(array $over = []): array
    {
        return $over + ['email' => 'a@example.com', 'phone' => '+971501234567', 'name' => 'Aisha', 'fulfilment' => 'delivery',
            'zone_id' => ShippingZone::where('code', 'other_emirates')->value('id'), 'line1' => 'Tower 1', 'city' => 'Al Reem',
            'payment_method' => 'cod', 'expected_total' => '1191.75'];
    }

    private function placeDeliveryOrder(): Order
    {
        $this->post('/cart/add/band');
        $this->post('/checkout', $this->delivery($this->idFields()))->assertSessionHasNoErrors();

        return Order::latest('id')->first();
    }

    public function test_delivery_order_needs_valid_id_before_payment(): void
    {
        $this->post('/cart/add/band');
        $this->get('/checkout')->assertSee('Identity verification')->assertSee('enctype="multipart/form-data"', false);

        $this->post('/checkout', $this->delivery())->assertSessionHasErrors(['id_number', 'id_front', 'id_expiry']);
        $this->post('/checkout', $this->delivery($this->idFields(['id_number' => '123-456'])))->assertSessionHasErrors('id_number');
        $this->post('/checkout', $this->delivery($this->idFields(['id_expiry' => now()->subDay()->toDateString()])))->assertSessionHasErrors('id_expiry');
        $this->assertSame(0, Order::count());

        $this->post('/checkout', $this->delivery($this->idFields()))->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertSame('784199012345671', $order->id_number);
        $this->assertSame('5671', $order->id_last4);
        $this->assertStringNotContainsString('784199012345671', DB::table('orders')->value('id_number'));   // encrypted at rest
        Storage::disk('local')->assertExists($order->id_front_path);
        $this->assertStringNotContainsString('id_number', json_encode($order->toArray()));
    }

    public function test_logged_in_customer_can_save_id_and_reuse_it(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->post('/cart/add/band');
        $this->post('/checkout', $this->delivery($this->idFields(['save_id' => '1'])))->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->hasValidSavedId());

        $this->post('/cart/add/band');
        $this->get('/checkout')->assertSee('Use my saved ID')->assertSee('•••• 5671');
        $this->post('/checkout', $this->delivery(['use_saved_id' => '1']))->assertSessionHasNoErrors();
        $this->assertSame(2, Order::count());
        $this->assertSame('5671', Order::latest('id')->first()->id_last4);

        $this->get('/account')->assertSee('Remove saved ID');
        $this->delete('/account/id')->assertRedirect();
        $this->assertFalse($user->fresh()->hasValidSavedId());
        $this->assertSame('5671', Order::latest('id')->first()->id_last4);   // past orders keep their copy
    }

    public function test_pickup_skips_id_unless_required_for_all_orders(): void
    {
        $branch = Branch::create(['name' => 'Souk', 'emirate' => 'Dubai', 'address' => 'Deira']);
        $this->post('/cart/add/band');
        $pickup = ['email' => 'p@example.com', 'phone' => '+971501234567', 'name' => 'P', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => 'card'];
        $pickup['expected_total'] = $this->postJson('/checkout/quote', $pickup)->json('grand_total_raw');

        Settings::set(['id_verification' => 'all']);
        $this->post('/checkout', $pickup)->assertSessionHasErrors('id_front');
        Settings::set(['id_verification' => 'delivery']);
        $this->post('/checkout', $pickup)->assertSessionHasNoErrors();
    }

    public function test_staff_only_can_view_id_photos(): void
    {
        $order = $this->placeDeliveryOrder();
        $this->actingAs(User::factory()->create())->get(route('admin.id-document', ['order', $order->id, 'front']))->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('admin.id-document', ['order', $order->id, 'front']))->assertOk();
        $this->get('/admin/orders/'.$order->id)->assertOk()->assertSee('784199012345671')->assertSee('Identity &amp; handover', false);
    }

    public function test_handover_code_is_sent_when_shipped_and_required_to_deliver(): void
    {
        $order = $this->placeDeliveryOrder();
        $svc = app(OrderService::class);
        $svc->transition($order, 'packed');
        $svc->transition($order->refresh(), 'shipped');

        $code = null;
        Mail::assertSent(ShopMessage::class, function (ShopMessage $m) use (&$code) {
            return preg_match('/delivery code .*: (\d{6})$/', $m->subjectLine, $x) && ($code = $x[1]);
        });
        $this->assertNotNull($code);

        try {
            $svc->transition($order->refresh(), 'delivered');
            $this->fail('Delivered without the handover code');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('handover code', $e->getMessage());
        }

        $otp = app(\App\Shop\HandoverOtp::class);
        try {
            $otp->verify($order->refresh(), $code === '111111' ? '222222' : '111111');
            $this->fail('Wrong code accepted');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('4 attempt(s) left', $e->getMessage());
        }
        $otp->verify($order->refresh(), $code);
        $svc->transition($order->refresh(), 'delivered');
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame('otp', $order->fresh()->handover_verified_via);
        $this->assertSame('paid', $order->fresh()->payment_status);   // COD collected at handover
    }

    public function test_manager_can_override_with_reason_or_switch_codes_off(): void
    {
        $order = $this->placeDeliveryOrder();
        $svc = app(OrderService::class);
        $svc->transition($order, 'packed');
        $svc->transition($order->refresh(), 'shipped');
        app(\App\Shop\HandoverOtp::class)->override($order->refresh(), null, 'Customer phone lost, ID checked');
        $svc->transition($order->refresh(), 'delivered');
        $this->assertStringContainsString('Customer phone lost', $order->statusHistory()->pluck('note')->implode(' '));

        Settings::set(['handover_otp' => false]);
        $second = $this->placeDeliveryOrder();
        $svc->transition($second, 'packed');
        $svc->transition($second->refresh(), 'shipped');
        $svc->transition($second->refresh(), 'delivered');
        $this->assertNull($second->fresh()->handover_otp_hash);
    }
}
