<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GoldCategory;
use App\Models\Order;
use App\Models\Product;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WalletPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_apple_pay_and_google_pay_are_offered_charge_the_card_fee_and_use_the_card_gateway(): void
    {
        Mail::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Settings::set(['id_verification' => 'off', 'card_fee_percent' => 2.5]);
        Product::create(['name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 3]);
        $branch = Branch::create(['name' => 'Souk', 'emirate' => 'Dubai', 'address' => 'Deira']);

        $this->post('/cart/add/band');
        $this->get('/checkout')->assertSee('Apple Pay')->assertSee('Google Pay');

        $form = ['email' => 'w@example.com', 'phone' => '+971501234567', 'name' => 'W', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id];
        $card = $this->postJson('/checkout/quote', $form + ['payment_method' => 'card'])->json();
        $apple = $this->postJson('/checkout/quote', $form + ['payment_method' => 'applepay'])->json();
        $this->assertSame($card['grand_total_raw'], $apple['grand_total_raw']);   // same 2.5% fee as card
        $this->assertNotNull($apple['payment_fee']);

        $res = $this->post('/checkout', $form + ['payment_method' => 'googlepay', 'expected_total' => $apple['grand_total_raw']]);
        $order = Order::sole();
        $this->assertSame('googlepay', $order->payment_method);
        $res->assertRedirectContains('/payments/mock/');                       // card gateway's secure page

        Settings::set(['applepay_enabled' => false]);
        $this->get('/checkout')->assertDontSee('Pay with Face ID');
    }
}
