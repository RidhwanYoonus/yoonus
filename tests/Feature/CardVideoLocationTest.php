<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GoldCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CardVideoLocationTest extends TestCase
{
    use RefreshDatabase;

    private array $stripeCalls = [];

    private string $piStatus = 'requires_payment_method';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Settings::set(['id_verification' => 'off', 'handover_otp' => false]);
        Product::create(['name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 3, 'is_active' => true]);
    }

    /** A small Stripe stand-in: customers, payment intents, saved cards. */
    private function fakeStripe(): void
    {
        Settings::set(['card_gateway' => 'stripe', 'stripe_secret_key' => 'sk_test_X', 'stripe_publishable_key' => 'pk_test_Y']);
        Http::fake(function (Request $r) {
            $path = parse_url($r->url(), PHP_URL_PATH);
            $this->stripeCalls[] = $r->method().' '.$path;
            $amount = (int) round((float) (Order::latest('id')->value('amount_due') ?? 0) * 100);
            $number = Order::latest('id')->value('number');
            $pi = ['id' => 'pi_1', 'client_secret' => 'pi_1_secret_2', 'amount' => $amount, 'amount_received' => $amount, 'status' => $this->piStatus, 'metadata' => ['order_number' => $number]];

            return match (true) {
                $path === '/v1/customers' => Http::response(['id' => 'cus_1']),
                str_ends_with($path, '/payment_methods') => Http::response(['data' => [['id' => 'pm_visa', 'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 4, 'exp_year' => 2030]]]]),
                str_ends_with($path, '/detach') => Http::response(['id' => 'pm_visa']),
                str_ends_with($path, '/confirm') => Http::response(['status' => 'succeeded'] + $pi),
                str_starts_with($path, '/v1/payment_intents') => Http::response($pi),
                default => Http::response([], 404),
            };
        });
    }

    private function cardOrder(): Order
    {
        $branch = Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        $this->post('/cart/add/band');
        $form = ['email' => 'm@example.com', 'phone' => '+971501234567', 'name' => 'Mariam', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => 'card'];
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');
        $this->post('/checkout', $form)->assertRedirect(route('payments.card', Order::latest('id')->value('number')));

        return Order::latest('id')->first();
    }

    public function test_card_is_typed_on_our_own_page_and_the_return_marks_it_paid(): void
    {
        $this->fakeStripe();
        $order = $this->cardOrder();
        $this->get(route('payments.card', $order->number))->assertOk()
            ->assertSee('js.stripe.com/v3', false)->assertSee('pk_test_Y')->assertSee('pi_1_secret_2')->assertSee('never see or keep your card number');
        $this->assertSame('pi_1', $order->fresh()->payment_reference);

        $this->piStatus = 'succeeded';
        $this->get("/payments/stripe/return/{$order->number}?payment_intent=pi_1")->assertRedirect(route('checkout.complete', $order->number));
        $this->assertTrue($order->fresh()->isPaid());
    }

    public function test_signed_in_customer_saves_a_card_pays_with_it_and_removes_it(): void
    {
        $this->fakeStripe();
        $user = User::factory()->create(['phone' => '+971501234567', 'phone_verified_at' => now()]);
        $this->actingAs($user);
        $order = $this->cardOrder();
        $this->get(route('payments.card', $order->number))->assertSee('Save this card for my next purchase');
        $this->assertSame('cus_1', $user->fresh()->stripe_customer_id);
        $this->postJson(route('payments.card.prepare', $order->number), ['save' => true])->assertOk();
        $this->assertContains('POST /v1/payment_intents/pi_1', $this->stripeCalls);

        // Next time: the saved card is offered and pays in one tap.
        $this->get(route('payments.card', $order->number))->assertSee('Visa •••• 4242');
        $this->postJson(route('payments.card.saved', $order->number), ['payment_method' => 'pm_visa'])->assertJson(['done' => route('checkout.complete', $order->number)]);
        $this->assertTrue($order->fresh()->isPaid());
        $this->postJson(route('payments.card.saved', $order->number), ['payment_method' => 'pm_someone_else'])->assertForbidden();

        $this->get('/account')->assertSee('Saved cards')->assertSee('Visa •••• 4242');
        $this->delete(route('account.cards.remove', 'pm_visa'))->assertSessionHas('status', 'Card removed.');
        $this->assertContains('POST /v1/payment_methods/pm_visa/detach', $this->stripeCalls);
    }

    public function test_without_the_publishable_key_customers_still_go_to_stripes_page(): void
    {
        Settings::set(['card_gateway' => 'stripe', 'stripe_secret_key' => 'sk_test_X']);
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_1'])]);
        $branch = Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        $this->post('/cart/add/band');
        $form = ['email' => 'm@example.com', 'phone' => '+971501234567', 'name' => 'Mariam', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => 'card'];
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');
        $this->post('/checkout', $form)->assertRedirect('https://checkout.stripe.com/c/pay/cs_1');
    }

    public function test_product_video_upload_or_youtube_link_shows_on_the_product_page(): void
    {
        $p = Product::sole();
        $p->update(['video_url' => 'https://youtu.be/dQw4w9WgXcQ']);
        $this->get('/product/band')->assertSee('Watch video')->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);

        $p->update(['video_path' => 'products/videos/ring.mp4']);
        $this->get('/product/band')->assertSee('products/videos/ring.mp4', false)->assertSee('preload="none"', false);

        $p->update(['video_path' => null, 'video_url' => 'https://www.instagram.com/reel/abc/']);
        $this->get('/product/band')->assertSee('https://www.instagram.com/reel/abc/', false);
    }

    public function test_delivery_order_keeps_the_customers_map_location(): void
    {
        $this->post('/cart/add/band');
        $this->get('/checkout')->assertSee('Use my current location')->assertSee('data-live-validate', false);
        $form = ['email' => 'g@example.com', 'phone' => '+971501234567', 'name' => 'Guest', 'fulfilment' => 'delivery',
            'zone_id' => ShippingZone::where('code', 'dubai')->value('id'), 'line1' => 'Tower 1', 'city' => 'Dubai', 'payment_method' => 'cod',
            'delivery_lat' => '25.2048493', 'delivery_lng' => '55.2707828'];
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');
        $this->post('/checkout', $form)->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertSame('https://www.google.com/maps/search/?api=1&query=25.2048493,55.2707828', $order->deliveryMapUrl());

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/orders/'.$order->id)->assertSee('Open in Google Maps');
    }

    public function test_location_can_be_required(): void
    {
        Settings::set(['delivery_location' => 'required']);
        $this->post('/cart/add/band');
        $form = ['email' => 'g@example.com', 'phone' => '+971501234567', 'name' => 'Guest', 'fulfilment' => 'delivery',
            'zone_id' => ShippingZone::where('code', 'dubai')->value('id'), 'line1' => 'Tower 1', 'city' => 'Dubai', 'payment_method' => 'cod'];
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');
        $this->post('/checkout', $form)->assertSessionHasErrors('delivery_lat');
        $this->post('/checkout', $form + ['map_link' => 'https://maps.app.goo.gl/abc123'])->assertSessionHasNoErrors();
        $this->assertSame('https://maps.app.goo.gl/abc123', Order::sole()->deliveryMapUrl());
    }
}
