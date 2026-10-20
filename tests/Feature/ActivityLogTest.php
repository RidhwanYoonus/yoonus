<?php

namespace Tests\Feature;

use App\Filament\Pages\LiveVisitors;
use App\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\GoldCategory;
use App\Models\Product;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        Settings::set(['id_verification' => 'off', 'handover_otp' => false]);
        Product::create(['name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 3]);
    }

    public function test_records_a_guests_steps_and_joins_them_to_the_account_after_sign_in(): void
    {
        $this->withCookie('vid', 'browser-one');
        $this->get('/product/band')->assertOk();
        $this->get('/shop?q=ring');
        $this->post('/cart/add/band', ['qty' => 1]);
        $this->postJson('/checkout/quote', []);           // background price refresh: not logged
        $this->get('/api/rates');

        $this->assertSame(['product_view', 'search', 'add_to_bag'], ActivityLog::orderBy('id')->pluck('event')->all());
        $this->assertSame('Band', ActivityLog::where('event', 'product_view')->value('subject'));
        $this->assertSame('ring', ActivityLog::where('event', 'search')->value('subject'));
        $this->assertNull(ActivityLog::first()->user_id);

        $user = User::factory()->create(['email' => 'mariam@example.com', 'password' => bcrypt('Secret123')]);
        $this->post('/login', ['email' => 'mariam@example.com', 'password' => 'wrong']);
        $this->post('/login', ['email' => 'mariam@example.com', 'password' => 'Secret123']);
        $this->assertSame(['login_failed', 'login'], ActivityLog::whereIn('event', ['login', 'login_failed'])->orderBy('id')->pluck('event')->all());
        $this->assertSame(0, ActivityLog::where('visitor_id', 'browser-one')->whereNull('user_id')->whereNot('event', 'login_failed')->count(),
            'steps before sign-in now belong to the customer');

        $this->post('/logout');
        $this->assertSame($user->id, ActivityLog::where('event', 'logout')->value('user_id'));
    }

    public function test_order_placed_and_failed_checkout_are_logged_with_the_reason(): void
    {
        $branch = Branch::firstOrCreate(['name' => 'Souk'], ['emirate' => 'Dubai', 'address' => 'Deira']);
        $this->post('/cart/add/band');
        $form = ['email' => 'guest@example.com', 'phone' => '+971501234567', 'name' => 'G', 'fulfilment' => 'pickup', 'pickup_branch_id' => $branch->id, 'payment_method' => 'card'];
        $form['expected_total'] = $this->postJson('/checkout/quote', $form)->json('grand_total_raw');

        $this->from('/checkout')->post('/checkout', ['name' => ''] + $form);
        $failed = ActivityLog::where('event', 'checkout_failed')->sole();
        $this->assertSame('guest@example.com', $failed->email);
        $this->assertNotEmpty($failed->details['reason']);

        $this->post('/checkout', $form);
        $placed = ActivityLog::where('event', 'order_placed')->sole();
        $this->assertSame(\App\Models\Order::sole()->number, $placed->subject);
        $this->assertSame('card', $placed->details['payment']);
    }

    public function test_bots_and_switched_off_logging_record_nothing(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1)')->get('/product/band');
        Settings::set(['activity_log_enabled' => false]);
        $this->withHeader('User-Agent', 'Mozilla/5.0 Safari')->get('/product/band');
        $this->assertSame(0, ActivityLog::count());
    }

    public function test_admin_sees_live_visitors_and_filters_one_customers_movements(): void
    {
        $customer = User::factory()->create(['name' => 'Mariam']);
        $now = now();
        foreach ([['a', $customer->id, 'product_view', 1], ['a', $customer->id, 'add_to_bag', 0], ['b', null, 'browse', 2], ['c', null, 'browse', 40]] as [$vid, $uid, $event, $ago]) {
            ActivityLog::create(['visitor_id' => $vid, 'user_id' => $uid, 'event' => $event, 'path' => '/x', 'created_at' => $now->copy()->subMinutes($ago)]);
        }
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $live = Livewire::test(LiveVisitors::class);
        $this->assertSame(['Online now' => 2, 'Signed in' => 1, 'Guests' => 1, 'In bag / checkout' => 1], $live->instance()->stats());
        $live->assertSee('Mariam')->assertCountTableRecords(2);

        Livewire::test(ListActivityLogs::class)->assertCountTableRecords(4)
            ->set('tableFilters.person.user_id', $customer->id)->assertCountTableRecords(2)
            ->set('tableFilters.person.user_id', null)->set('tableFilters.person.visitor', 'b')->assertCountTableRecords(1);
        $this->get(\App\Filament\Resources\ActivityLogResource::urlFor($customer->id))->assertOk()->assertSee('Customer: Mariam');
        $this->get(\App\Filament\Resources\UserResource::getUrl())->assertOk()->assertSee('Activity');
    }

    public function test_old_entries_are_pruned(): void
    {
        ActivityLog::create(['visitor_id' => 'x', 'event' => 'browse', 'path' => '/', 'created_at' => now()->subDays(100)]);
        ActivityLog::create(['visitor_id' => 'x', 'event' => 'browse', 'path' => '/', 'created_at' => now()->subDays(10)]);
        $this->artisan('shop:prune-activity')->assertSuccessful();
        $this->assertSame(1, ActivityLog::count());
    }
}
