<?php

namespace Tests;

use App\Models\GoldCategory;
use App\Pricing\GoldRateService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    /** Set karat rates by hand, e.g. ['22K' => '200.00']. */
    protected function setGoldRates(array $rates): void
    {
        foreach ($rates as $karat => $rate) {
            app(GoldRateService::class)->updateRate(GoldCategory::where('name', $karat)->firstOrFail(), $rate);
        }
    }

    /** Proof-of-ID fields a delivery checkout needs (photos go to a faked private disk). */
    protected function idFields(array $over = []): array
    {
        Storage::fake('local');

        return $over + [
            'id_type' => 'emirates_id', 'id_number' => '784-1990-1234567-1', 'id_expiry' => now()->addYears(2)->toDateString(),
            'id_front' => UploadedFile::fake()->image('eid-front.jpg'),
        ];
    }
}
