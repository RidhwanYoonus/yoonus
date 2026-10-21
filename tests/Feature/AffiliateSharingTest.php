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

class AffiliateSharingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        foreach (range(1, 9) as $i) {
            Product::create(['name' => "Piece {$i}", 'sku' => "S-{$i}", 'slug' => "piece-{$i}", 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
                'weight_grams' => 3, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => $i === 9 ? 0 : 1]);
        }
        $this->user = User::factory()->create();
    }

    private function affiliate(bool $white): Affiliate
    {
        return Affiliate::create(['user_id' => $this->user->id, 'code' => 'HUDA', 'making_commission_percent' => 10, 'fixed_commission_percent' => 3, 'white_page_enabled' => $white]);
    }

    public function test_all_pieces_page_lists_stock_with_search(): void
    {
        $this->affiliate(false);
        $this->actingAs($this->user)->get('/affiliate')->assertOk()->assertSee('See all 8 pieces to share')->assertSee('Gold-rate picture');
        $page = $this->get('/affiliate/share')->assertOk()->assertSee('Piece 1')->assertSee('Piece 8')->assertDontSee('Piece 9');
        $this->get('/affiliate/share?q=Piece 3')->assertSee('Piece 3')->assertDontSee('Piece 4');
        $this->assertStringContainsString('?aff=HUDA', html_entity_decode($page->getContent()));
    }

    public function test_white_page_affiliates_get_both_links_and_the_choice(): void
    {
        $this->affiliate(true);
        $html = html_entity_decode($this->actingAs($this->user)->get('/affiliate/share')->assertOk()->getContent());
        $this->assertMatchesRegularExpression('/aff=HUDA(&|\\\\+u0026)w=1/', $html);
        $this->assertStringContainsString('Which link should this post use?', $html);
        $this->assertStringContainsString('affShare(true)', $html);
    }

    public function test_normal_affiliates_are_not_asked(): void
    {
        $this->affiliate(false);
        $html = html_entity_decode($this->actingAs($this->user)->get('/affiliate')->getContent());
        $this->assertStringContainsString('affShare(false)', $html);
        $this->assertStringNotContainsString('&w=1', str_replace('\/', '/', $html));
    }

    public function test_rate_picture_uses_backgrounds_from_settings(): void
    {
        Settings::set(['affiliate_rate_backgrounds' => ['rate-backgrounds/shop.jpg']]);
        $this->affiliate(false);
        $this->actingAs($this->user)->get('/affiliate')->assertOk()->assertSee('shop.jpg', false)->assertSee('AED per gram');
    }

    public function test_customers_cannot_open_the_share_page(): void
    {
        $this->actingAs(User::factory()->create())->get('/affiliate/share')->assertNotFound();
    }
}
