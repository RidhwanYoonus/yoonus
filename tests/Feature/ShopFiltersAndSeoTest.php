<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CertificateLab;
use App\Models\GoldCategory;
use App\Models\Product;
use App\Models\User;
use App\Support\SeoText;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShopFiltersAndSeoTest extends TestCase
{
    use RefreshDatabase;

    private Category $rings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00', '18K' => '160.00']);
        Settings::set(['seo_auto_fill' => false]);
        $this->rings = Category::create(['name' => 'Rings', 'name_ar' => 'خواتم', 'slug' => 'rings', 'is_active' => true]);
        $k = fn ($n) => GoldCategory::where('name', $n)->value('id');
        $base = ['category_id' => $this->rings->id, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10];
        Product::create($base + ['name' => 'Plain Band', 'sku' => 'P-1', 'slug' => 'plain', 'gold_category_id' => $k('22K'), 'weight_grams' => 5, 'stock_qty' => 2]);
        Product::create($base + ['name' => 'Sold Band', 'sku' => 'P-2', 'slug' => 'sold', 'gold_category_id' => $k('22K'), 'weight_grams' => 4, 'stock_qty' => 0]);
        Product::create($base + ['name' => 'Natural Solitaire', 'sku' => 'D-1', 'slug' => 'natural', 'gold_category_id' => $k('18K'), 'weight_grams' => 3, 'stock_qty' => 1,
            'stone_type' => 'Diamond', 'diamond_origin' => 'natural', 'diamond_carat' => 0.5, 'diamond_color' => 'F', 'diamond_clarity' => 'VS1', 'diamond_shape' => 'Round', 'diamond_cut' => 'Excellent',
            'certificate_lab_id' => CertificateLab::where('short_name', 'GIA')->value('id'), 'description' => 'A classic four-claw solitaire. Made in Dubai.']);
        Product::create($base + ['name' => 'Lab Heart', 'sku' => 'D-2', 'slug' => 'lab', 'gold_category_id' => $k('18K'), 'weight_grams' => 2, 'stock_qty' => 1,
            'diamond_origin' => 'lab_grown', 'diamond_carat' => 1.2, 'diamond_color' => 'E', 'diamond_clarity' => 'VVS2', 'diamond_shape' => 'Heart']);
    }

    private function names(string $query): array
    {
        $html = $this->get('/category/rings?'.$query)->assertOk()->getContent();

        return collect(['Plain Band', 'Sold Band', 'Natural Solitaire', 'Lab Heart'])->filter(fn ($n) => str_contains($html, '>'.$n.'<'))->values()->all();
    }

    public function test_available_only_and_diamond_filters(): void
    {
        $this->assertCount(4, $this->names(''));
        $this->assertSame(['Plain Band', 'Natural Solitaire', 'Lab Heart'], $this->names('available=1'));
        $this->assertSame(['Natural Solitaire'], $this->names('diamond=natural'));
        $this->assertSame(['Lab Heart'], $this->names('diamond=lab_grown'));
        $this->assertSame(['Natural Solitaire', 'Lab Heart'], $this->names('diamond=any'));
        $this->assertSame(['Plain Band', 'Sold Band'], $this->names('diamond=none'));
        $this->assertSame(['Lab Heart'], $this->names('clarity[]=VVS2'));
        $this->assertSame(['Natural Solitaire'], $this->names('color[]=F&shape[]=Round'));
        $this->assertSame(['Lab Heart'], $this->names('min_carat=1'));

        $this->get('/category/rings')->assertSee('Available only')->assertSee('Lab-grown diamond')->assertSee('Diamond clarity')->assertSee('VVS2');
    }

    public function test_setting_hides_sold_pieces_by_default_but_customer_can_show_them(): void
    {
        Settings::set(['shop_hide_sold' => true]);
        $this->assertNotContains('Sold Band', $this->names(''));
        $this->assertContains('Sold Band', $this->names('available=0'));
    }

    public function test_seo_text_is_written_from_product_details_in_english_and_arabic(): void
    {
        $p = Product::where('sku', 'D-1')->sole();
        $t = SeoText::forProduct($p);
        $this->assertSame('Natural Solitaire – 18K Gold – 0.5 ct Natural Diamond', $t['meta_title']);
        $this->assertStringContainsString('Natural Solitaire in 18K gold, 3 g, with a 0.5 ct natural diamond, F colour, VS1 clarity, excellent cut, GIA certified.', $t['meta_description']);
        $this->assertLessThanOrEqual(158, mb_strlen($t['meta_description']));
        $this->assertStringContainsString('ذهب 18K', $t['meta_title_ar']);
        $this->assertStringContainsString('بشهادة GIA', $t['meta_description_ar']);
        $this->assertStringNotContainsString('AED', $t['meta_description'], 'no prices: they go out of date');

        $c = SeoText::forCategory($this->rings);
        $this->assertSame('Rings – 22K, 18K Gold Jewellery UAE', $c['meta_title']);
        $this->assertStringContainsString('with natural and lab-grown diamonds', $c['meta_description']);
    }

    public function test_empty_boxes_fill_on_save_and_typed_text_is_kept(): void
    {
        Settings::set(['seo_auto_fill' => true]);
        $p = Product::where('sku', 'P-1')->sole();
        $p->update(['meta_title' => 'My own title']);
        $p->refresh();
        $this->assertSame('My own title', $p->meta_title);
        $this->assertStringStartsWith('Plain Band in 22K gold, 5 g.', $p->meta_description);
        $this->assertNotEmpty($p->meta_title_ar);

        $this->get('/product/plain?lang=ar')->assertSee('<title>'.e($p->meta_title_ar), false);
    }

    public function test_fill_all_button_and_form_buttons(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(\App\Filament\Resources\ProductResource\Pages\ListProducts::class)->callAction('fillAllSeo')->assertNotified();
        $this->assertSame(0, Product::whereNull('meta_title')->orWhereNull('meta_description_ar')->count());
        $this->assertNotNull($this->rings->fresh()->meta_description_ar);

        $p = Product::where('sku', 'D-2')->sole();
        $p->updateQuietly(['meta_description' => null]);
        Livewire::test(\App\Filament\Resources\ProductResource\Pages\EditProduct::class, ['record' => $p->getRouteKey()])
            ->callFormComponentAction('meta_description', 'write_meta_description')
            ->assertFormSet(fn ($state) => str_contains((string) $state['meta_description'], '1.2 ct lab-grown diamond') ? [] : ['meta_description' => 'x']);
    }
}
