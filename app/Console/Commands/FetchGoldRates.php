<?php

namespace App\Console\Commands;

use App\Models\GoldCategory;
use App\Pricing\GoldRateService;
use App\Pricing\WebsiteGoldRates;
use App\Support\AutoTasks;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Automatic rate feed, run by the scheduler every 15 minutes. Uses the JSON API in Admin → Settings
 * (24K AED per gram, JSON path via GOLD_RATE_API_PATH) or, when "Update rates from this website
 * automatically" is on, every karat from the rate website the Fetch button reads.
 */
class FetchGoldRates extends Command
{
    protected $signature = 'shop:fetch-gold-rates {--dry-run}';

    protected $description = 'Pull gold rates from the configured API or website and cascade them';

    /** @var array<string> what this run did, shown in Admin → Settings → Pricing */
    private array $log = [];

    public function info($string, $verbosity = null)
    {
        $this->log[] = (string) $string;
        parent::info($string, $verbosity);
    }

    public function error($string, $verbosity = null)
    {
        $this->log[] = (string) $string;
        parent::error($string, $verbosity);
    }

    public function handle(GoldRateService $rates): int
    {
        // The website switch wins: a website address typed into the API box would otherwise be read as JSON.
        $source = Settings::get('gold_rate_auto_website') ? 'fromWebsite' : (Settings::get('gold_rate_api_url') ? 'fromApi' : null);
        if ($source) {
            $this->log = [];
            try {
                $result = $this->{$source}($rates);
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
                $result = self::FAILURE;
            }
            if (! $this->option('dry-run')) {
                AutoTasks::recordGoldRate($result === self::SUCCESS, implode(' · ', $this->log) ?: 'No rates found.');
            }

            return $result;
        }
        $this->warn('No automatic gold rate source switched on; rates are entered manually.');

        return self::SUCCESS;
    }

    private function fromApi(GoldRateService $rates): int
    {
        $json = Http::withToken((string) Settings::get('gold_rate_api_key'))->acceptJson()->get(Settings::get('gold_rate_api_url'))->throw()->json();
        $rate = (float) Arr::get($json, config('shop.gold_rate_api_path', 'rates.24K'));
        $base = GoldCategory::orderByDesc('karat')->first();
        if ($rate <= 0 || ! $base) {
            $this->error('Rate missing in API response.');

            return self::FAILURE;
        }

        return $this->apply($rates, [[$base, $rate]], 'api') ? self::SUCCESS : self::FAILURE;
    }

    private function fromWebsite(GoldRateService $rates): int
    {
        $found = app(WebsiteGoldRates::class)->fetch();
        $pairs = [];
        foreach (GoldCategory::where('is_active', true)->orderByDesc('karat')->get() as $category) {
            if (isset($found[(int) $category->karat.'K'])) {
                $pairs[] = [$category, (float) $found[(int) $category->karat.'K']];
            }
        }

        return $this->apply($rates, $pairs, 'website') ? self::SUCCESS : self::FAILURE;
    }

    /** @param array<array{0: GoldCategory, 1: float}> $pairs */
    private function apply(GoldRateService $rates, array $pairs, string $source): bool
    {
        $ok = true;
        $checked = false;
        foreach ($pairs as [$category, $rate]) {
            $current = (float) $category->rate_per_gram;
            // Ignore absurd swings (>15%) — likely a bad feed; leave for a human to confirm.
            if ($current > 0 && abs($rate - $current) / $current > 0.15) {
                $this->error("Rejected suspicious {$category->name} rate {$rate} (current {$current}).");
                $ok = false;

                continue;
            }
            if ($this->option('dry-run')) {
                $this->info("{$category->name} = AED {$rate}/g (dry run)");

                continue;
            }
            if (abs($rate - $current) < 0.005) {
                // Same price: only record that it was checked, so the shop's "Updated" time stays current.
                $category->forceFill(['rate_updated_at' => now()])->save();
                $checked = true;
            } else {
                $rates->updateRate($category->fresh(), $rate, 'gram', null, $source);
            }
            $this->info("{$category->name} = AED {$rate}/g");
        }
        if ($checked) {
            Cache::forget('home.rates');
        }

        return $ok && $pairs !== [];
    }
}
