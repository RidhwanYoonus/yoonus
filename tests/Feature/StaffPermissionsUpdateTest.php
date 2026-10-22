<?php

namespace Tests\Feature;

use App\Filament\Resources\AffiliatePayoutResource;
use App\Filament\Resources\CertificateLabResource;
use App\Filament\Resources\ProductResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class StaffPermissionsUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $perms): User
    {
        return User::factory()->create(['is_staff' => true, 'permissions' => $perms]);
    }

    public function test_existing_staff_keep_their_access_after_the_split(): void
    {
        $old = $this->staff(['affiliates' => ['view', 'edit'], 'products' => ['view'], 'orders' => ['view', 'edit']]);
        Artisan::call('migrate:rollback', ['--step' => 3, '--force' => true]);   // tourist flag, stone types + the permission split
        Artisan::call('migrate', ['--force' => true]);

        $perms = $old->fresh()->permissions;
        $this->assertSame(['view', 'edit'], $perms['affiliate_payouts']);
        $this->assertSame(['view'], $perms['certificate_labs']);
        $this->assertSame(['view', 'edit'], $perms['store_visits']);
        $this->assertSame(['view'], $perms['export']);
    }

    public function test_areas_are_separate_now(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $catalogue = $this->staff(['products' => ['view', 'edit']]);
        $this->actingAs($catalogue)->get(ProductResource::getUrl())->assertOk()->assertDontSee('Print / Export');
        $this->get(CertificateLabResource::getUrl())->assertForbidden();
        $this->get(AffiliatePayoutResource::getUrl())->assertForbidden();

        $cashier = $this->staff(['affiliate_payouts' => ['view', 'create'], 'export' => ['view']]);
        $this->actingAs($cashier)->get(AffiliatePayoutResource::getUrl())->assertOk()->assertSee('Print / Export');
        $this->get(ProductResource::getUrl())->assertForbidden();
    }

    public function test_staff_form_lists_new_areas_presets_and_admin_only_items(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get(\App\Filament\Resources\StaffResource::getUrl('create'))->assertOk()
            ->assertSee('Collections (hand over affiliate money)')->assertSee('Certificate labs')->assertSee('Print / Export lists')
            ->assertSee('Live visitors &amp; activity log', false)->assertSee('Always admin only')->assertSee('Affiliate manager');
    }

    public function test_dashboard_speed_check_for_admins(): void
    {
        $problems = collect(\App\Support\SpeedCheck::problems())->pluck('text')->implode(' ');
        $this->assertStringContainsString('Speed up now', $problems);   // tests never cache config
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin')->assertOk()->assertSee('Speed check');
        $this->actingAs($this->staff(['orders' => ['view']]))->get('/admin')->assertOk()->assertDontSee('Speed check');
    }

    public function test_hover_prefetch_is_not_counted_as_a_view(): void
    {
        $this->seed(\Database\Seeders\ReferenceDataSeeder::class);
        $this->setGoldRates(['22K' => '200.00']);
        $p = \App\Models\Product::create(['name' => 'Band', 'sku' => 'B-1', 'slug' => 'band', 'gold_category_id' => \App\Models\GoldCategory::where('name', '22K')->value('id'),
            'weight_grams' => 5, 'manufacturer_charge_type' => 'PERCENTAGE', 'manufacturer_charge_value' => 10, 'stock_qty' => 1, 'is_active' => true]);
        $this->withHeader('Sec-Purpose', 'prefetch')->get('/product/band')->assertOk();
        $this->assertSame(0, (int) $p->fresh()->view_count);
        $this->assertSame(0, \App\Models\ActivityLog::count());
        $this->withHeader('Sec-Purpose', '')->get('/product/band');
        $this->assertSame(1, (int) $p->fresh()->view_count);
        $this->get('/')->assertSee('speculationrules', false);
    }

    public function test_speed_cache_buttons_and_auto_refresh(): void
    {
        $this->assertFalse(\App\Support\SpeedCache::isStale(), 'no cache yet: nothing to refresh');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(\App\Filament\Pages\Settings::class)->assertActionVisible('speedUp')->assertActionVisible('speedClear');

        // Building inside a request must leave the running app in place (it used to break the page with error 500).
        $app = app();
        try {
            \App\Support\SpeedCache::build();
            $this->assertSame($app, \Illuminate\Container\Container::getInstance());
            $this->assertFileExists(app()->getCachedRoutesPath());
        } finally {
            \App\Support\SpeedCache::clear();
        }
        $this->assertFileDoesNotExist(app()->getCachedRoutesPath());

        // Building for real writes bootstrap/cache; check the stale test on a fake cache file instead.
        $cache = app()->getCachedConfigPath();
        file_put_contents($cache, '<?php return [];');
        try {
            touch($cache, time() - 3600);
            touch(base_path('routes/web.php'));
            $this->assertTrue(\App\Support\SpeedCache::isStale(), 'an update newer than the cache is noticed');
            touch($cache, time() + 60);
            $this->assertFalse(\App\Support\SpeedCache::isStale());
        } finally {
            @unlink($cache);
        }
    }

    public function test_production_on_a_pc_without_ssl_keeps_http_links(): void
    {
        $this->app['env'] = 'production';
        config(['app.url' => 'http://127.0.0.1:8000']);
        \Illuminate\Support\Facades\URL::forceScheme(null);
        (new \App\Providers\AppServiceProvider($this->app))->boot();
        $this->assertStringStartsWith('http://', url('/build/app.css'));

        config(['app.url' => 'https://shop.ae']);
        (new \App\Providers\AppServiceProvider($this->app))->boot();
        $this->assertStringStartsWith('https://', url('/build/app.css'));
        \Illuminate\Support\Facades\URL::forceScheme(null);
        $this->app['env'] = 'testing';
    }
}
