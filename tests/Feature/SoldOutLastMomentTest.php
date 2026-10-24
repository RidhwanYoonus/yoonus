<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GoldCategory;
use App\Models\Order;
use App\Models\Product;
use App\Shop\OrderService;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SoldOutLastMomentTest extends TestCase
{
    use RefreshDatabase;

    private Product $ring;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Settings::set(['id_verification' => 'off', 'handover_otp' => false]);
        $this->ring = Product::create(['name' => 'One Ring', 'sku' => 'R-1', 'slug' => 'one-ring', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 1, 'is_active' => true]);
    }

    private function form(string $method = 'cod'): array
    {
        $branch = Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        $form = ['email' => 'a@example.com', 'phone' => '+971501234567', 'name' => 'A', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => $method];
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');

        return $form;
    }

    public function test_piece_sold_while_on_checkout_page_is_caught_at_place_order(): void
    {
        Settings::set(['cod_enabled' => true]);
        $this->post('/cart/add/one-ring');
        $form = $this->form('card');
        $this->ring->update(['stock_qty' => 0]);   // someone else bought it a moment ago

        $this->post('/checkout', $form)->assertRedirect(route('cart.show'))->assertSessionHasErrors('cart');
        $this->assertSame(0, Order::count());
        $this->assertSame(0, $this->ring->fresh()->stock_qty);
        $this->get('/cart')->assertSee('has just been sold and was taken out of your bag');
        $this->assertSame([], \App\Models\Cart::latest('id')->value('items'));
    }

    public function test_the_checkout_page_and_its_minute_check_say_when_a_piece_sold(): void
    {
        Product::create(['name' => 'Chain', 'sku' => 'C-1', 'slug' => 'chain', 'gold_category_id' => $this->ring->gold_category_id,
            'weight_grams' => 3, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 2, 'is_active' => true]);
        $this->post('/cart/add/one-ring');
        $this->post('/cart/add/chain', ['qty' => 2]);
        $this->get('/checkout')->assertOk();

        $this->ring->update(['stock_qty' => 0]);
        Product::where('slug', 'chain')->update(['stock_qty' => 1]);
        $json = $this->postJson('/checkout/quote', ['fulfilment' => 'pickup', 'payment_method' => 'cod'])->json();
        $this->assertContains('Sorry, One Ring has just been sold and was taken out of your bag.', $json['stock_notes']);
        $this->assertContains('Only 1 of Chain left — your bag was updated.', $json['stock_notes']);

        Product::where('slug', 'chain')->update(['stock_qty' => 0]);
        $this->postJson('/checkout/quote', ['fulfilment' => 'pickup'])->assertJson(['empty' => true, 'cart' => route('cart.show')]);
    }

    public function test_the_second_customer_cannot_buy_a_piece_reserved_by_a_card_payment(): void
    {
        Settings::set(['card_gateway' => 'mock']);
        $this->post('/cart/add/one-ring');
        $this->post('/checkout', $this->form('card'));
        $this->assertSame(0, $this->ring->fresh()->stock_qty);   // held while the first customer pays

        // Another browser.
        $this->flushSession();
        $this->post('/cart/add/one-ring')->assertSessionHasErrors('cart');
        $this->assertSame(1, Order::count());
    }

    public function test_late_payment_for_a_piece_already_sold_is_never_sold_twice(): void
    {
        Settings::set(['card_gateway' => 'mock', 'staff_order_emails' => 'sales@shop.ae']);
        $this->post('/cart/add/one-ring');
        $this->post('/checkout', $this->form('card'));
        $late = Order::sole();
        $svc = app(OrderService::class);
        $svc->expireUnpaid($late->fresh(), 'Time ran out');   // stock comes back …
        $this->assertSame(1, $this->ring->fresh()->stock_qty);
        $this->ring->update(['stock_qty' => 0]);              // … and someone else buys it

        $order = $svc->markPaid($late->fresh(), 'stripe', 'pi_late');
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(0, $this->ring->fresh()->stock_qty);   // not -1
        $this->assertStringContainsString('REFUND NEEDED', $order->statusHistory()->latest('id')->value('note'));
        Mail::assertSent(\App\Mail\ShopMessage::class, fn ($m) => $m->hasTo('sales@shop.ae') && str_contains($m->subjectLine, 'Refund needed'));
    }
}
