<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\GoldCategory;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        foreach (['Ring' => 'R-1', 'خاتم' => 'R-2'] as $name => $sku) {
            Product::create(['name' => $name, 'sku' => $sku, 'slug' => strtolower($sku), 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
                'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 1]);
        }
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_every_admin_list_renders_with_print_export_and_column_menu(): void
    {
        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            if (! $resource::hasPage('index')) {
                continue;
            }
            $this->get($resource::getUrl('index'))->assertOk()->assertSee('Print / Export');
        }
    }

    public function test_export_uses_the_ticked_columns_and_current_rows(): void
    {
        $page = Livewire::test(ListProducts::class)->searchTable('R-2');
        [$headings, $rows] = $page->instance()->tableExportData();
        $rows = iterator_to_array($rows(), false);
        $this->assertCount(1, $rows);
        $this->assertSame('خاتم · R-2', $rows[0][0]);

        [$all] = $page->instance()->tableExportData();
        $page->set('toggledTableColumns.weight_grams', false);
        [$headings] = $page->instance()->tableExportData();
        $this->assertCount(count($all) - 1, $headings, 'a column switched off in the menu is left out');
        $this->assertNotContains('Weight', $headings);
        $this->assertStringContainsString('R-2', $rows[0][0], 'the line under a cell (here the SKU) is kept');

        Livewire::test(ListOrders::class)->callTableAction('exportTable')->assertFileDownloaded();
    }

    public function test_sales_report_page_prints_and_exports(): void
    {
        $page = Livewire::test(\App\Filament\Pages\Reports::class)->assertSee('Print / Export');
        $this->assertSame(['Summary — last 30 days', 'Top products', 'Sales by category', 'Sales by karat'], array_column($page->instance()->reportTables(), 'title'));
        $page->callAction('export')->assertFileDownloaded();
        $page->callAction('print')->assertNotified();
    }

    public function test_print_page_is_only_for_the_staff_member_who_made_it(): void
    {
        Livewire::test(ListProducts::class)->callTableAction('printTable')->assertNotified();

        Cache::put('print:'.str_repeat('a', 40), ['user_id' => $this->admin->id, 'title' => 'Products', 'headings' => ['Name'], 'rows' => [['Ring']]], 60);
        $this->get('/admin/print/'.str_repeat('a', 40))->assertOk()->assertSee('Ring')->assertSee('window.print', false);
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin/print/'.str_repeat('a', 40))->assertNotFound();
    }
}
