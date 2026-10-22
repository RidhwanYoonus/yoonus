<?php

namespace Tests\Feature;

use App\Models\GoldCategory;
use App\Models\Product;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartBagExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Settings::set(['id_verification' => 'delivery']);
        Product::create(['name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 3, 'is_active' => true]);
    }

    public function test_add_to_bag_opens_the_bag_with_a_red_count_and_fly_animation(): void
    {
        $this->get('/product/band')->assertSee('data-add-to-bag', false)->assertSee('data-fly-scope', false)->assertSee('data-cart-icon', false);
        $this->post('/cart/add/band')->assertRedirect(route('cart.show'));
        $this->get('/cart')->assertSee('Band')->assertSee('bg-red-600', false)->assertSee('data-cart-count', false);
    }

    public function test_customer_adds_id_in_the_bag_without_expiry_date(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/cart/add/band');
        $this->get('/cart')->assertSee('Your ID for this order')->assertSee('optional');

        $this->post('/account/id', ['back' => 'cart', 'id_type' => 'emirates_id', 'id_number' => '784-1990-1234567-1'])
            ->assertRedirect(route('cart.show').'#cart-id');
        $this->assertNull($user->fresh()->id_expiry);
        $this->assertTrue($user->fresh()->hasValidSavedId());
        $this->get('/cart')->assertSee('checkout uses it automatically');
    }

    public function test_guest_sees_sign_in_prompt_in_the_bag(): void
    {
        $this->post('/cart/add/band');
        $this->get('/cart')->assertSee('Sign in or create an account to save your ID now');
    }
}
