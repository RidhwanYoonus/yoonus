<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GoldCategory;
use App\Models\Product;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignupTouristIdTest extends TestCase
{
    use RefreshDatabase;

    private array $base = ['name' => 'Anna', 'email' => 'anna@example.com', 'password' => 'Secret123', 'password_confirmation' => 'Secret123'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(ReferenceDataSeeder::class);
    }

    public function test_sign_up_page_offers_the_optional_tourist_and_id_step(): void
    {
        $this->get('/register')->assertOk()->assertSee('Speed up your first order (optional)')
            ->assertSee('Are you visiting the UAE as a tourist?')->assertSee('required to place an order');
    }

    public function test_sign_up_without_id_still_works(): void
    {
        $this->post('/register', $this->base + ['is_tourist' => '0'])->assertSessionHasNoErrors();
        $user = User::where('email', 'anna@example.com')->sole();
        $this->assertFalse($user->is_tourist);
        $this->assertNull($user->id_saved_at);
    }

    public function test_tourist_saves_passport_at_sign_up_and_checkout_starts_tax_free(): void
    {
        $this->post('/register', $this->base + ['is_tourist' => '1', 'id_type' => 'passport', 'id_number' => 'X1234567',
            'id_expiry' => now()->addYears(3)->toDateString(), 'id_country' => 'GB', 'id_front' => UploadedFile::fake()->image('p.jpg')])
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'anna@example.com')->sole();
        $this->assertTrue($user->is_tourist);
        $this->assertSame('passport', $user->id_type);
        $this->assertSame('GB', $user->id_country);
        $this->assertNotNull($user->id_front_path);

        Product::create(['name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 3]);
        $this->setGoldRates(['22K' => '200.00']);
        Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        $this->post('/cart/add/band');
        $this->get('/checkout')->assertOk()->assertSee('tax_free\\u0022:true', false);   // tax-free already chosen
    }

    public function test_tourist_must_use_a_passport(): void
    {
        $this->post('/register', $this->base + ['is_tourist' => '1', 'id_type' => 'emirates_id', 'id_number' => '784-1990-1234567-1',
            'id_expiry' => now()->addYear()->toDateString()])->assertSessionHasErrors('id_type');
        $this->assertFalse(User::where('email', 'anna@example.com')->exists());
    }

    public function test_tourist_choice_can_be_changed_in_my_account(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/account/id', ['is_tourist' => '1', 'id_type' => 'passport', 'id_number' => 'Y7654321',
            'id_expiry' => now()->addYears(2)->toDateString(), 'id_country' => 'IN'])->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->is_tourist);
        $this->get('/account')->assertSee('Yes, I’m a tourist');
    }
}
