<?php

namespace Tests\Feature;

use App\Filament\Resources\GoldCategoryResource\Pages\ListGoldCategories;
use App\Models\GoldCategory;
use App\Models\GoldRateHistory;
use App\Models\User;
use App\Pricing\WebsiteGoldRates;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class WebsiteGoldRatesTest extends TestCase
{
    use RefreshDatabase;

    public static function layouts(): array
    {
        return [
            // Layout of dubaicityofgold.com's "Today's suggested retail gold jewellery price" card.
            'dubaicityofgold' => ['<div class="card"><h3>TODAY\'S SUGGESTED RETAIL GOLD JEWELLERY PRICE</h3><p>RATES IN AED / GRAM</p><ul><li><span>24K GOLD</span><span>AED 386.75</span></li><li><span>22K GOLD</span><span>AED 358.00</span></li><li><span>21K GOLD</span><span>AED 343.25</span></li><li><span>18K GOLD</span><span>AED 294.25</span></li><li><span>14K GOLD</span><span>AED 229.50</span></li></ul><small>Updated 9 minutes ago</small></div>'],
            'table' => ['<table><tr><th>Karat</th><th>Price (AED/g)</th></tr><tr><td>24K</td><td>386.75</td></tr><tr><td>22K</td><td>358.00</td></tr><tr><td>21K</td><td>343.25</td></tr><tr><td>18K</td><td>294.25</td></tr></table>'],
            'cards' => ['<div class="rate"><span class="karat">24 Karat</span><span class="price">AED&nbsp;386.75</span></div><div class="rate"><span>22 Carat</span><b>AED 358.00</b></div><div><h4>21 KT</h4><p>343.25 AED</p></div><div><h4>18ct</h4><p>294.25</p></div>'],
            'json' => ['<div id="rates"></div><script>window.__RATES__ = {"gold24":386.75,"gold22":358.00,"gold21":343.25,"gold18":294.25};</script>'],
            'arabic' => ['<p>ذهب عيار 24: ٣٨٦٫٧٥ درهم</p><p>عيار 22: 358.00 درهم</p>'],
        ];
    }

    /** @dataProvider layouts */
    public function test_parses_common_layouts(string $html): void
    {
        $rates = (new WebsiteGoldRates)->parse('<html><body><nav>Since 1999 · 24/7 support</nav>'.$html.'</body></html>');

        $this->assertSame('386.75', $rates['24K']);
        $this->assertSame('358.00', $rates['22K']);
        if (str_contains($html, '18K GOLD')) {
            $this->assertSame(['24K' => '386.75', '22K' => '358.00', '21K' => '343.25', '18K' => '294.25'], $rates);
        }
    }

    public function test_rejects_implausible_numbers(): void
    {
        $rates = (new WebsiteGoldRates)->parse('<p>24K 386.75</p><p>22K 1999</p><p>18K 2024</p>');

        $this->assertSame(['24K' => '386.75'], $rates);
    }

    public function test_uses_bundled_ca_certificates(): void
    {
        $this->assertFileExists(resource_path('certs/cacert.pem'));
        Http::fake(['*' => Http::response('<p>24K GOLD AED 386.75</p>')]);
        (new WebsiteGoldRates)->fetch('https://dubaicityofgold.com/');
        Http::assertSent(fn ($request) => true);
    }

    public function test_reports_when_no_rates_found(): void
    {
        Http::fake(['*' => Http::response('<html><body>Loading…</body></html>')]);
        $this->expectException(RuntimeException::class);
        (new WebsiteGoldRates)->fetch('https://dubaicityofgold.com/');
    }

    public function test_admin_button_fetches_and_applies_24k_with_derivation(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        Http::fake(['dubaicityofgold.com/*' => Http::response(self::layouts()['table'][0])]);
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);

        Livewire::test(ListGoldCategories::class)
            ->mountAction('fetchWebsiteRates')
            ->assertActionDataSet(['rate_24K' => '386.75', 'rate_22K' => '358.00'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('386.75', GoldCategory::where('name', '24K')->value('rate_per_gram'));
        $this->assertSame('354.52', GoldCategory::where('name', '22K')->value('rate_per_gram'));   // 386.75 × 22/24
        $this->assertSame('website', GoldRateHistory::latest('id')->first()->source);
    }

    public function test_admin_button_can_use_website_rate_for_every_karat(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        Http::fake(['dubaicityofgold.com/*' => Http::response(self::layouts()['table'][0])]);
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin);

        Livewire::test(ListGoldCategories::class)
            ->mountAction('fetchWebsiteRates')
            ->setActionData(['mode' => 'each'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame('358.00', GoldCategory::where('name', '22K')->value('rate_per_gram'));
        $this->assertSame('294.25', GoldCategory::where('name', '18K')->value('rate_per_gram'));
    }
}
