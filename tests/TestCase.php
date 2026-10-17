<?php

namespace Tests;

use App\Models\GoldCategory;
use App\Pricing\GoldRateService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Set karat rates by hand, e.g. ['22K' => '200.00']. */
    protected function setGoldRates(array $rates): void
    {
        foreach ($rates as $karat => $rate) {
            app(GoldRateService::class)->updateRate(GoldCategory::where('name', $karat)->firstOrFail(), $rate);
        }
    }
}
