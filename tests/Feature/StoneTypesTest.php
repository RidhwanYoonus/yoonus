<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\StoneTypeResource\Pages\ManageStoneTypes;
use App\Models\GoldCategory;
use App\Models\Product;
use App\Models\StoneType;
use App\Models\User;
use App\Support\ProductCsv;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StoneTypesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['18K' => '300.00']);
    }

    private function piece(string $sku, ?string $stones): Product
    {
        return Product::create(['name' => "Piece {$sku}", 'sku' => $sku, 'slug' => strtolower($sku), 'gold_category_id' => GoldCategory::where('name', '18K')->value('id'),
            'weight_grams' => 3, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stone_value' => 500, 'stone_type' => $stones, 'stock_qty' => 1, 'is_active' => true]);
    }

    public function test_master_list_is_seeded_with_arabic_names(): void
    {
        $this->assertSame('زمرد', StoneType::where('name', 'Emerald')->value('name_ar'));
        $this->assertArrayHasKey('Lab-grown diamond', StoneType::options());
    }

    public function test_product_form_saves_several_stones(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);
        $product = $this->piece('ST-1', 'Diamond');

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertFormSet(['stone_type' => ['Diamond']])
            ->fillForm(['stone_type' => ['Diamond', 'Emerald']])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame('Diamond, Emerald', $product->fresh()->stone_type);
        $this->assertSame(['Diamond', 'Emerald'], $product->fresh()->stones());
        $this->get('/admin/stone-types')->assertOk()->assertSee('Emerald');
        Livewire::test(ManageStoneTypes::class)->assertCanSeeTableRecords(StoneType::where('name', 'Emerald')->get());
    }

    public function test_shop_filter_finds_a_stone_among_several_and_shows_arabic(): void
    {
        $both = $this->piece('ST-2', 'Diamond, Emerald');
        $ruby = $this->piece('ST-3', 'Ruby');
        $lab = $this->piece('ST-4', 'Lab-grown diamond');

        $this->get('/shop?stone=Emerald')->assertSee($both->name)->assertDontSee($ruby->name);
        $this->get('/shop?stone=Diamond')->assertSee($both->name)->assertDontSee($lab->name)->assertDontSee($ruby->name);
        $this->get('/shop')->assertSee('<option value="Emerald"', false);
        $this->get('/product/'.$both->slug)->assertSee('Diamond · Emerald');
        app()->setLocale('ar');
        $this->assertSame('ألماس · زمرد', $both->stoneLabel());
    }

    public function test_csv_import_adds_new_stones_to_the_list(): void
    {
        $csv = tempnam(sys_get_temp_dir(), 'csv');
        $f = fopen($csv, 'w');
        fputcsv($f, ProductCsv::COLUMNS);
        $row = array_fill_keys(ProductCsv::COLUMNS, '');
        $row = ['sku' => 'CSV-ST', 'name' => 'Stone ring', 'karat' => '18K', 'weight_grams' => '2', 'stone_type' => 'emerald, Tanzanite', 'stock_qty' => '1'] + $row;
        fputcsv($f, array_map(fn ($c) => $row[$c], ProductCsv::COLUMNS));
        fclose($f);

        ProductCsv::import($csv);

        $this->assertSame('Emerald, Tanzanite', Product::where('sku', 'CSV-ST')->value('stone_type'));
        $this->assertTrue(StoneType::where('name', 'Tanzanite')->exists());
    }
}
