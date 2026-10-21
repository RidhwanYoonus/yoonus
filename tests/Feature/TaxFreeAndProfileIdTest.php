<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Branch;
use App\Models\GoldCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TaxFreeAndProfileIdTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Settings::set(['id_verification' => 'off', 'handover_otp' => false]);
        $this->product = Product::create(['name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 3, 'is_active' => true]);
    }

    private function checkout(array $extra): \Illuminate\Testing\TestResponse
    {
        $branch = Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        $this->post('/cart/add/band');
        $form = ['email' => 't@example.com', 'phone' => '+971501234567', 'name' => 'Tourist', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => 'card'];
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');

        return $this->from('/checkout')->post('/checkout', $extra + $form);
    }

    public function test_tourist_tax_free_needs_a_passport_even_when_id_is_off(): void
    {
        $this->get('/checkout')->assertRedirect();   // empty bag
        $this->post('/cart/add/band');
        $this->get('/checkout')->assertOk()->assertSee('Tax-free shopping for tourists')->assertSee('Passport nationality');

        $this->checkout(['tax_free' => '1'])->assertSessionHasErrors(['id_type', 'id_number', 'id_country']);
        $this->checkout(['tax_free' => '1', 'id_type' => 'emirates_id', 'id_number' => '784-1990-1234567-1', 'id_expiry' => now()->addYear()->toDateString(), 'id_country' => 'IN'])
            ->assertSessionHasErrors('id_type');
        $this->assertSame(0, Order::count());

        $this->checkout(['tax_free' => '1', 'id_type' => 'passport', 'id_number' => 'K1234567', 'id_expiry' => now()->addYear()->toDateString(), 'id_country' => 'IN'])
            ->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertTrue($order->tax_free_requested);
        $this->assertSame(['passport', 'IN'], [$order->id_type, $order->id_country]);
        $this->assertStringContainsString('TAX FREE FOR TOURISTS', view('invoices.tax-invoice', ['order' => $order->load('items'), 'address' => null, 'ar' => fn ($t) => $t])->render());
    }

    public function test_no_id_asked_when_the_customer_is_not_a_tourist(): void
    {
        $this->checkout(['tax_free' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse(Order::sole()->tax_free_requested);
    }

    public function test_tax_free_minimum_purchase(): void
    {
        Settings::set(['tax_free_min' => 100000]);
        $this->checkout(['tax_free' => '1', 'id_type' => 'passport', 'id_number' => 'K1234567', 'id_expiry' => now()->addYear()->toDateString(), 'id_country' => 'GB'])
            ->assertSessionHasErrors('tax_free');
    }

    public function test_customer_saves_passport_in_profile_and_checkout_reuses_it(): void
    {
        $user = User::factory()->create(['phone' => '+971501234567']);
        $this->actingAs($user)->get('/account')->assertOk()->assertSee('Verification')->assertSee('Add your ID')->assertSee('Verify now');

        $this->post('/account/id', ['id_type' => 'passport', 'id_number' => 'k 1234567', 'id_expiry' => now()->addYears(3)->toDateString()])
            ->assertSessionHasErrors('id_country');
        $this->post('/account/id', ['id_type' => 'passport', 'id_number' => 'k 1234567', 'id_expiry' => now()->addYears(3)->toDateString(), 'id_country' => 'GB',
            'id_front' => UploadedFile::fake()->image('passport.jpg', 1200, 800)])->assertRedirect();
        $user->refresh();
        $this->assertSame(['passport', 'K1234567', 'GB'], [$user->id_type, $user->id_number, $user->id_country]);
        $this->assertNotNull($user->id_front_path);
        $this->get('/account')->assertSee('Passport nationality')->assertSee('United Kingdom');

        $this->checkout(['tax_free' => '1', 'use_saved_id' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('GB', Order::sole()->id_country);
    }

    public function test_new_products_start_hidden_and_list_switches_them(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(CreateProduct::class)->assertFormSet(['is_active' => false]);

        $hidden = Product::create(['name' => 'Hidden', 'sku' => 'H-1', 'slug' => 'hidden', 'gold_category_id' => $this->product->gold_category_id,
            'weight_grams' => 2, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 1, 'is_active' => false]);
        Livewire::test(ListProducts::class)
            ->set('tableFilters.is_active.value', '0')->assertCanSeeTableRecords([$hidden])->assertCanNotSeeTableRecords([$this->product])
            ->callTableColumnAction('is_active', $hidden)
            ->callTableBulkAction('show', [$hidden]);
        $this->assertTrue($hidden->fresh()->is_active);

        $this->product->forceFill(['created_at' => now()->subMonth()])->saveQuietly();
        Livewire::test(ListProducts::class)->set('tableFilters.created.created_from', now()->subDay()->toDateString())
            ->assertCanSeeTableRecords([$hidden])->assertCanNotSeeTableRecords([$this->product]);
    }

    public function test_saving_returns_to_the_page_staff_came_from(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $list = \App\Filament\Resources\ProductResource::getUrl('index').'?tableFilters[is_active][value]=0';

        Livewire::test(EditProduct::class, ['record' => $this->product->getRouteKey()])
            ->set('returnTo', $list)->call('save')->assertHasNoFormErrors()->assertRedirect($list);
        // Opening the form from the filtered list remembers that list.
        $html = $this->get(\App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $this->product]), ['referer' => $list])->assertOk()->getContent();
        $this->assertStringContainsString('tableFilters', html_entity_decode($html));
        session()->forget('_previous');   // opened straight from a bookmark: back to the list
        Livewire::test(EditProduct::class, ['record' => $this->product->getRouteKey()])
            ->call('save')->assertRedirect(\App\Filament\Resources\ProductResource::getUrl('index'));
    }

    public function test_products_remember_who_created_and_edited_them_and_filter_by_it(): void
    {
        $sara = User::factory()->create(['name' => 'Sara', 'is_staff' => true, 'permissions' => ['products' => ['view', 'create', 'edit']]]);
        $admin = User::factory()->create(['name' => 'Owner', 'is_admin' => true]);
        $this->actingAs($sara);
        $ring = Product::create(['name' => 'Sara Ring', 'sku' => 'SR-1', 'slug' => 'sara-ring', 'gold_category_id' => $this->product->gold_category_id,
            'weight_grams' => 2, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 1]);
        $this->assertSame([$sara->id, $sara->id], [$ring->created_by, $ring->updated_by]);

        $this->actingAs($admin);
        $ring->update(['stock_qty' => 2]);
        $this->assertSame([$sara->id, $admin->id], [$ring->fresh()->created_by, $ring->fresh()->updated_by]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListProducts::class)
            ->set('tableFilters.created_by.values', [$sara->id])->assertCanSeeTableRecords([$ring])->assertCanNotSeeTableRecords([$this->product])
            ->set('tableFilters.created_by.values', [])->set('tableFilters.updated_by.values', [$admin->id])->assertCanSeeTableRecords([$ring]);

        // Customers buying (stock going down) do not count as editing.
        $this->actingAs(User::factory()->create());
        $ring->update(['stock_qty' => 1]);
        $this->assertSame($admin->id, $ring->fresh()->updated_by);
    }
}
