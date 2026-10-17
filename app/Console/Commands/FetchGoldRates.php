<?php

namespace App\Console\Commands;

use App\Models\GoldCategory;
use App\Pricing\GoldRateService;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * Optional automatic rate feed. Expects a JSON endpoint returning the 24K AED rate per gram;
 * configure the URL (and the JSON path via GOLD_RATE_API_PATH) in Admin → Settings.
 */
class FetchGoldRates extends Command
{
    protected $signature = 'shop:fetch-gold-rates {--dry-run}';

    protected $description = 'Pull the 24K gold rate from the configured API and cascade it';

    public function handle(GoldRateService $rates): int
    {
        $url = Settings::get('gold_rate_api_url');
        if (! $url) {
            $this->warn('No gold rate API configured; rates are entered manually.');

            return self::SUCCESS;
        }
        $json = Http::withToken((string) Settings::get('gold_rate_api_key'))->acceptJson()->get($url)->throw()->json();
        $rate = (float) Arr::get($json, env('GOLD_RATE_API_PATH', 'rates.24K'));
        $base = GoldCategory::where('auto_derive', false)->orderByDesc('karat')->first();
        if ($rate <= 0 || ! $base) {
            $this->error('Rate missing in API response.');

            return self::FAILURE;
        }
        // Ignore absurd swings (>15%) — likely a bad feed; leave for a human to confirm.
        if ($base->rate_per_gram > 0 && abs($rate - $base->rate_per_gram) / $base->rate_per_gram > 0.15) {
            $this->error("Rejected suspicious rate {$rate} (current {$base->rate_per_gram}).");

            return self::FAILURE;
        }
        if (! $this->option('dry-run')) {
            $rates->updateRate($base, $rate, 'gram', null, 'api');
        }
        $this->info("{$base->name} = AED {$rate}/g");

        return self::SUCCESS;
    }
}
