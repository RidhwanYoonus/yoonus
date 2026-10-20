<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings as SettingsPage;
use App\Models\Affiliate;
use App\Models\Branch;
use App\Models\GoldCategory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\DataCleanup;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DataCleanupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
        Storage::fake('local');
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Settings::set(['id_verification' => 'off', 'handover_otp' => false]);
        Product::create(['name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 3, 'images' => ['products/used.webp']]);
        $this->admin = User::factory()->create(['is_admin' => true, 'password' => bcrypt('AdminPass1')]);
    }

    private function placeOrder(): Order
    {
        $branch = Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        $this->post('/cart/add/band');
        $form = ['email' => 'b@example.com', 'phone' => '+971501234567', 'name' => 'B', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => 'card'];
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');
        $this->post('/checkout', $form);

        return Order::latest('id')->firstOrFail();
    }

    public function test_deletes_only_the_chosen_groups_and_keeps_products_and_staff(): void
    {
        $order = $this->placeOrder();
        Storage::disk('local')->put('ids/2026/10/front.jpg', 'x');
        $order->forceFill(['id_front_path' => 'ids/2026/10/front.jpg'])->saveQuietly();
        $customer = User::factory()->create(['email' => 'cust@example.com']);
        $staff = User::factory()->create(['is_staff' => true]);
        $affUser = User::factory()->create();
        Affiliate::create(['user_id' => $affUser->id, 'code' => 'AFF1', 'making_commission_percent' => 5, 'fixed_commission_percent' => 1, 'coupon_discount_percent' => 10]);
        Storage::disk('public')->put('products/used.webp', 'a');
        Storage::disk('public')->put('products/old-deleted.webp', 'b');

        $this->assertSame(1, DataCleanup::counts()['images']);
        $done = DataCleanup::run(['orders', 'images'], $this->admin);

        $this->assertSame(0, Order::count());
        $this->assertSame(0, DB::table('order_items')->count());
        Storage::disk('local')->assertMissing('ids/2026/10/front.jpg');
        Storage::disk('public')->assertExists('products/used.webp');
        Storage::disk('public')->assertMissing('products/old-deleted.webp');
        $this->assertSame(1, $done['Unused image files']);
        $this->assertSame(1, Product::count(), 'products are never deleted');
        $this->assertSame(1, Affiliate::count(), 'affiliates were not chosen');
        $this->assertTrue(User::whereKey($customer->id)->exists(), 'customers were not chosen');
        $this->assertTrue(DB::table('security_events')->where('event', 'data_deleted')->exists());

        DataCleanup::run(['affiliates', 'customers']);
        $this->assertSame(0, Affiliate::count());
        $this->assertFalse(DB::table('coupons')->where('code', 'AFF1')->exists());
        $this->assertFalse(User::whereKey($customer->id)->exists());
        $this->assertTrue(User::whereKey($this->admin->id)->exists() && User::whereKey($staff->id)->exists());
    }

    public function test_settings_button_needs_admin_password_and_delete_word(): void
    {
        $this->placeOrder();
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SettingsPage::class)
            ->callAction('deleteData', ['groups' => ['orders'], 'confirm' => 'delete', 'password' => 'wrong'])
            ->assertHasActionErrors(['confirm', 'password']);
        $this->assertSame(1, Order::count());

        Livewire::test(SettingsPage::class)
            ->callAction('deleteData', ['groups' => ['orders'], 'confirm' => 'DELETE', 'password' => 'AdminPass1'])
            ->assertHasNoActionErrors()->assertNotified();
        $this->assertSame(0, Order::count());
    }

    public function test_staff_never_see_the_button(): void
    {
        $staff = User::factory()->create(['is_staff' => true, 'permissions' => ['settings' => ['view', 'edit']]]);
        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        if (SettingsPage::canAccess()) {
            Livewire::test(SettingsPage::class)->assertActionHidden('deleteData');
        } else {
            $this->get(SettingsPage::getUrl())->assertForbidden();
        }
    }
}
