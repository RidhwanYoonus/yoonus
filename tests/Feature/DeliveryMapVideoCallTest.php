<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GoldCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\VideoCallRequest;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DeliveryMapVideoCallTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Product::create(['name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 3]);
        $this->branch = Branch::create(['name' => 'Gold Souk', 'emirate' => 'Dubai', 'address' => 'Deira', 'latitude' => 25.2697, 'longitude' => 55.2972,
            'map_url' => 'https://maps.app.goo.gl/abc123']);
    }

    public function test_delivery_can_be_paused_leaving_only_pickup(): void
    {
        Settings::set(['delivery_enabled' => false]);
        $this->post('/cart/add/band');
        $this->get('/checkout')->assertOk()->assertSee('Home delivery is paused')->assertDontSee('name="line1"', false);

        $delivery = ['email' => 'g@example.com', 'phone' => '+971501234567', 'name' => 'G', 'fulfilment' => 'delivery',
            'line1' => 'Tower', 'city' => 'Dubai', 'payment_method' => 'cod', 'expected_total' => '1191.75'];
        $this->post('/checkout', $delivery)->assertSessionHasErrors('fulfilment');
        $this->assertSame(0, Order::count());

        $pickup = ['email' => 'g@example.com', 'phone' => '+971501234567', 'name' => 'G', 'fulfilment' => 'pickup', 'pickup_branch_id' => $this->branch->id, 'payment_method' => 'card'];
        $pickup['expected_total'] = $this->postJson('/checkout/quote', $pickup)->json('grand_total_raw');
        $this->post('/checkout', $pickup)->assertSessionHasNoErrors();
        $this->assertTrue(Order::sole()->isPickup());
    }

    public function test_product_page_shows_store_map_with_directions(): void
    {
        $this->get('/product/band')->assertOk()
            ->assertSee('maps.google.com/maps?q=25.2697%2C55.2972', false)
            ->assertSee('https://maps.app.goo.gl/abc123', false)
            ->assertSee('Get directions');

        Settings::set(['product_store_map' => false]);
        $this->get('/product/band')->assertDontSee('maps.google.com/maps', false);
    }

    public function test_customer_requests_video_call_and_staff_join_from_admin(): void
    {
        $this->get('/product/band')->assertSee('Live video call with our staff');

        $this->post('/product/band/video-call', ['name' => 'Aisha', 'phone' => '+971501112222'])->assertRedirect();
        $call = VideoCallRequest::sole();
        $this->assertSame('waiting', $call->status);
        $this->assertStringStartsWith('https://meet.jit.si/', $call->roomUrl());
        $this->get(route('video-call.show', $call))->assertOk()->assertSee($call->room)->assertSee('Join the video call');

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/video-call-requests')->assertOk()->assertSee('Aisha');
        $this->actingAs($admin)->get(route('admin.video-call.join', $call->id))->assertRedirectContains($call->room);
        $this->assertSame('answered', $call->fresh()->status);
        $this->getJson(route('video-call.status', $call))->assertJson(['status' => 'answered']);
    }

    public function test_video_call_can_be_switched_off_per_product_and_for_the_shop(): void
    {
        Product::where('slug', 'band')->update(['video_call_enabled' => false]);
        $this->get('/product/band')->assertDontSee('Live video call with our staff');
        $this->post('/product/band/video-call', ['name' => 'A', 'phone' => '+971501112222'])->assertNotFound();

        Product::where('slug', 'band')->update(['video_call_enabled' => true]);
        Settings::set(['video_call_enabled' => false]);
        $this->get('/product/band')->assertDontSee('Live video call with our staff');
    }

    public function test_customers_cannot_join_as_staff(): void
    {
        $call = VideoCallRequest::create(['name' => 'A', 'phone' => '1234567']);
        $this->actingAs(User::factory()->create())->get(route('admin.video-call.join', $call->id))->assertForbidden();
    }
}
