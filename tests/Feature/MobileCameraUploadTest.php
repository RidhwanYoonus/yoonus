<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\GoldCategory;
use App\Models\Product;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MobileCameraUploadTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        $this->product = Product::create(['name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 1]);
    }

    public function test_camera_photos_are_added_after_the_existing_images(): void
    {
        Storage::disk('public')->put('products/old.webp', 'x');
        $this->product->update(['images' => ['products/old.webp']]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditProduct::class, ['record' => $this->product->getRouteKey()])
            ->assertSee('capture="environment"', false)
            ->set('data.camera_photos', [UploadedFile::fake()->image('camera.jpg', 1200, 900)])
            ->call('save')->assertHasNoFormErrors();

        $images = $this->product->fresh()->images;
        $this->assertCount(2, $images);
        $this->assertSame('products/old.webp', $images[0]);
        $this->assertStringStartsWith('products/', $images[1]);
        Storage::disk('public')->assertExists($images[1]);
    }

    public function test_checkout_offers_take_photo_for_the_id(): void
    {
        Settings::set(['id_verification' => 'always', 'id_photos' => 'optional']);
        $this->post('/cart/add/band');
        $this->get('/checkout')->assertOk()->assertSee('Take photo')->assertSee('Choose file')->assertSee("setAttribute('capture', 'environment')", false);
    }
}
