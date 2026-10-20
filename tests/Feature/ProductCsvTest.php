<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\CertificateLab;
use App\Models\Product;
use App\Models\User;
use App\Support\ProductCsv;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCsvTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00', '18K' => '160.00']);
    }

    private function capture(\Closure $writer): string
    {
        ob_start();
        $writer();

        return ob_get_clean();
    }

    private function file(string $csv): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $csv);

        return $path;
    }

    public function test_sample_file_has_every_column_and_imports_cleanly(): void
    {
        $sample = $this->capture(ProductCsv::sample());
        $rows = array_map('str_getcsv', array_filter(explode("\n", preg_replace('/^\xEF\xBB\xBF/', '', $sample))));
        $this->assertSame(ProductCsv::COLUMNS, $rows[0]);
        foreach (array_slice($rows, 1) as $row) {
            $this->assertCount(count(ProductCsv::COLUMNS), $row);
        }

        $result = ProductCsv::import($this->file($sample));
        $this->assertSame(['created' => 3, 'updated' => 0, 'errors' => []], $result);

        $natural = Product::where('sku', 'DR-1001')->sole();
        $this->assertSame('natural', $natural->diamond_origin);
        $this->assertSame('GIA', $natural->certificateLab->short_name);
        $this->assertSame(['2141438171', '0.505', 'Round', 'F', 'VS1', 'Excellent'],
            [$natural->certificate_number, $natural->diamond_carat, $natural->diamond_shape, $natural->diamond_color, $natural->diamond_clarity, $natural->diamond_cut]);
        $lab = Product::where('sku', 'LG-2001')->sole();
        $this->assertSame(['lab_grown', 'IGI', 'FIXED'], [$lab->diamond_origin, $lab->certificateLab->short_name, $lab->pricing_type]);
        $this->assertNull(Product::where('sku', 'RING-001')->sole()->diamond_origin);
    }

    public function test_export_has_the_new_columns_and_reimports_the_same(): void
    {
        ProductCsv::import($this->file($this->capture(ProductCsv::sample())));
        $export = $this->capture(ProductCsv::export());
        $this->assertStringContainsString('diamond_type,certificate_lab,certificate_number', $export);
        $this->assertStringContainsString('natural,GIA,2141438171', $export);
        $this->assertStringContainsString('lab_grown,IGI,LG612345678', $export);

        $this->assertSame(['created' => 0, 'updated' => 3, 'errors' => []], ProductCsv::import($this->file($export)));
        $this->assertSame('lab_grown', Product::where('sku', 'LG-2001')->value('diamond_origin'));
    }

    public function test_friendly_values_bad_type_and_new_lab(): void
    {
        $csv = "sku,name,karat,weight_grams,diamond_type,certificate_lab,diamond_clarity,diamond_cut\n"
            ."A-1,Ring A,22K,4,Lab-grown,HRD Antwerp,vs2,very good\n"
            ."A-2,Ring B,22K,4,synthetic,,,\n"
            ."A-3,Ring C,22K,4,natural,AGL,,\n";
        $result = ProductCsv::import($this->file($csv));

        $this->assertSame(2, $result['created']);
        $this->assertStringContainsString("Row 3: diamond_type must be natural or lab_grown", $result['errors'][0]);
        $a = Product::where('sku', 'A-1')->sole();
        $this->assertSame(['lab_grown', 'HRD', 'VS2', 'Very Good'], [$a->diamond_origin, $a->certificateLab->short_name, $a->diamond_clarity, $a->diamond_cut]);
        $this->assertTrue(CertificateLab::where('short_name', 'AGL')->exists(), 'an unknown lab is added to the list');

        // A file with only some columns leaves the others alone.
        $a->update(['stock_qty' => 5, 'manufacturer_charge_value' => 15]);
        ProductCsv::import($this->file("sku,name,karat,weight_grams,diamond_color\nA-1,Ring A,22K,4,g\n"));
        $a->refresh();
        $this->assertSame([5, '15.00', 'G', 'lab_grown'], [$a->stock_qty, (string) $a->manufacturer_charge_value, $a->diamond_color, $a->diamond_origin]);
    }

    public function test_import_window_offers_the_sample_file(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListProducts::class)->assertSee('Download sample CSV')->callAction('sample')->assertFileDownloaded('products-import-sample.csv');
        Livewire::test(ListProducts::class)->mountAction('import')->assertSee('diamond_type')->assertSee('Download sample CSV');
    }
}
