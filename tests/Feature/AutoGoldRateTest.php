<?php

namespace Tests\Feature;

use App\Models\GoldCategory;
use App\Models\GoldRateHistory;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use App\Support\AutoTasks;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutoGoldRateTest extends TestCase
{
    use RefreshDatabase;

    private const CARD = '<ul><li>24K GOLD AED 386.75</li><li>22K GOLD AED 358.00</li><li>21K GOLD AED 343.25</li><li>18K GOLD AED 294.25</li></ul>';

    public function test_cron_does_nothing_until_switched_on(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        Http::fake();

        $this->artisan('shop:fetch-gold-rates')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_cron_reads_every_karat_from_the_website(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        Settings::set(['gold_rate_auto_website' => true]);
        Http::fake(['dubaicityofgold.com/*' => Http::response(self::CARD)]);

        $this->artisan('shop:fetch-gold-rates')->assertSuccessful();

        $this->assertSame(['24K' => '386.75', '22K' => '358.00', '21K' => '343.25', '18K' => '294.25'], GoldCategory::pluck('rate_per_gram', 'name')->all());
        $this->assertSame('website', GoldRateHistory::latest('id')->first()->source);
    }

    public function test_unchanged_rate_only_refreshes_the_updated_time(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        Settings::set(['gold_rate_auto_website' => true]);
        Http::fake(['dubaicityofgold.com/*' => Http::response(self::CARD)]);
        $this->artisan('shop:fetch-gold-rates')->assertSuccessful();
        $history = GoldRateHistory::count();

        Carbon::setTestNow(now()->addHour());
        $this->artisan('shop:fetch-gold-rates')->assertSuccessful();

        $this->assertSame($history, GoldRateHistory::count());
        $this->assertSame(now()->format('Y-m-d H:i:s'), GoldCategory::where('name', '24K')->first()->rate_updated_at->format('Y-m-d H:i:s'));
        Carbon::setTestNow();
    }

    public function test_big_jump_is_skipped(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        Settings::set(['gold_rate_auto_website' => true]);
        Http::fake(['dubaicityofgold.com/*' => Http::sequence()->push(self::CARD)->push(str_replace('386.75', '480.00', self::CARD))]);
        $this->artisan('shop:fetch-gold-rates')->assertSuccessful();

        $this->artisan('shop:fetch-gold-rates')->assertFailed();

        $this->assertSame('386.75', GoldCategory::where('name', '24K')->value('rate_per_gram'));
    }

    public function test_admin_text_size_setting(): void
    {
        $this->assertStringContainsString('min-width:1680px', AdminPanelProvider::textSizeStyle());
        Settings::set(['admin_text_size' => 'large']);
        $this->assertStringContainsString('112.5%', AdminPanelProvider::textSizeStyle());
        Settings::set(['admin_text_size' => 'normal']);
        $this->assertSame('', AdminPanelProvider::textSizeStyle());

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        Settings::set(['admin_text_size' => 'xlarge']);
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('html{font-size:125%}', false);
    }

    public function test_status_is_recorded_for_the_admin(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        Settings::set(['gold_rate_auto_website' => true]);
        Http::fake(['dubaicityofgold.com/*' => Http::sequence()->push(self::CARD)->push('<p>Rates load with JavaScript</p>')]);

        $this->artisan('shop:fetch-gold-rates')->assertSuccessful();
        $this->assertTrue(AutoTasks::goldRate()['ok']);
        $this->assertStringContainsString('24K = AED 386.75/g', AutoTasks::goldRateText());

        $this->artisan('shop:fetch-gold-rates')->assertFailed();
        $this->assertFalse(AutoTasks::goldRate()['ok']);
        $this->assertStringContainsString('No gold rates found', AutoTasks::goldRateText());
    }

    public function test_cron_heartbeat(): void
    {
        $this->assertStringContainsString('has not run yet', AutoTasks::cronText());
        $this->artisan('schedule:run')->assertSuccessful();
        $this->assertTrue(AutoTasks::cronRunning());
        $this->assertStringContainsString('Cron job is running', AutoTasks::cronText());
    }
}
