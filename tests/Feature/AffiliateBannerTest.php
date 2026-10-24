<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\GoldCategory;
use App\Models\Product;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateBannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Product::create(['name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 5, 'is_active' => true]);
        Affiliate::create(['user_id' => User::factory()->create(['name' => 'Reshma'])->id, 'code' => 'RESHMA', 'making_commission_percent' => 10,
            'white_page_enabled' => true, 'markup_mode' => 'percent', 'making_markup_percent' => 20, 'max_making_markup_percent' => 30]);
    }

    public function test_aff_none_clears_the_affiliate_on_this_phone(): void
    {
        $this->get('/w/RESHMA')->assertOk();
        $this->get('/product/band')->assertSee('You are shopping with')->assertSee('1,120.00');
        $this->get('/?aff=none')->assertOk();
        $this->get('/product/band')->assertDontSee('You are shopping with')->assertSee('1,100.00');
    }

    public function test_admin_and_staff_are_never_linked_and_the_bar_can_be_hidden(): void
    {
        $this->actingAs(User::factory()->create(['is_staff' => true]))->get('/w/RESHMA');
        $this->get('/product/band')->assertDontSee('You are shopping with')->assertSee('1,100.00');
        auth()->logout();

        Settings::set(['affiliate_banner' => false]);
        $this->get('/w/RESHMA');
        $this->get('/product/band')->assertDontSee('You are shopping with')->assertSee('1,120.00');   // still her prices
    }
}
