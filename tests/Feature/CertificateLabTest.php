<?php

namespace Tests\Feature;

use App\Models\CertificateLab;
use App\Models\GoldCategory;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateLabTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $over = []): Product
    {
        return Product::create($over + ['name' => 'Solitaire', 'sku' => 'D-1', 'slug' => 'solitaire', 'gold_category_id' => GoldCategory::where('name', '18K')->value('id'),
            'weight_grams' => 3, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 1, 'stone_type' => 'Diamond', 'stone_value' => 4000]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['18K' => '160.00']);
    }

    public function test_common_labs_come_ready_and_build_the_report_check_link(): void
    {
        $gia = CertificateLab::where('short_name', 'GIA')->sole();
        $this->assertSame('https://www.gia.edu/report-check?reportno=2141438171', $gia->verifyUrl('2141438171'));
        $this->assertNull(CertificateLab::where('short_name', 'HRD')->sole()->verifyUrl('123'));
    }

    public function test_product_page_shows_the_certificate_with_the_lab_logo(): void
    {
        $lab = CertificateLab::where('short_name', 'GIA')->sole();
        $lab->update(['logo' => 'labs/gia.png']);
        $this->product(['certificate_lab_id' => $lab->id, 'certificate_number' => '2141438171', 'diamond_carat' => 0.505, 'diamond_color' => 'F', 'diamond_clarity' => 'VS1', 'diamond_cut' => 'Excellent', 'diamond_origin' => 'lab_grown']);

        $this->get('/product/solitaire')->assertOk()
            ->assertSee('Diamond certificate')->assertSee('labs/gia.png')->assertSee('2141438171')
            ->assertSee('0.505 ct')->assertSee('VS1')->assertSee('Lab-grown diamond')->assertSee('reportno=2141438171', false);
        $this->get('/product/solitaire?lang=ar')->assertOk()->assertSee('شهادة الألماس')->assertSee('ألماس مصنّع في المختبر');
        $this->get('/')->assertOk();
    }

    public function test_no_certificate_box_without_details(): void
    {
        $this->product();
        $this->get('/product/solitaire')->assertOk()->assertDontSee('Diamond certificate');
    }

    public function test_admin_manages_labs_and_product_form_offers_them(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/certificate-labs')->assertOk()->assertSee('IGI');
        $p = $this->product();
        $this->actingAs($admin)->get(\App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $p]))->assertOk()->assertSee('Diamond certificate')->assertSee('the pencil edits the chosen lab')->assertSee('Lab-grown diamond');
    }

    public function test_lab_can_be_edited_from_the_product_form(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        $lab = CertificateLab::where('short_name', 'IGI')->sole();
        $p = $this->product(['certificate_lab_id' => $lab->id]);

        \Livewire\Livewire::test(\App\Filament\Resources\ProductResource\Pages\EditProduct::class, ['record' => $p->getRouteKey()])
            ->mountFormComponentAction('certificate_lab_id', 'editOption')
            ->setFormComponentActionData(['name' => 'IGI Worldwide', 'short_name' => 'IGI', 'verify_url' => 'https://igi.example/{number}', 'is_active' => true, 'sort_order' => 2])
            ->callMountedFormComponentAction()->assertHasNoFormComponentActionErrors();
        $this->assertSame('IGI Worldwide', $lab->fresh()->name);
    }
}
