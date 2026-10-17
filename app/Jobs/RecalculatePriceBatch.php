<?php

namespace App\Jobs;

use App\Models\Product;
use App\Pricing\PricingService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Recalculates and logs the cached price of up to 500 products after a rate change. */
class RecalculatePriceBatch implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    /** @param array<int,int> $historyByCategory gold_category_id => gold_rate_history id */
    public function __construct(public array $productIds, public array $historyByCategory = [], public string $reason = 'rate_change') {}

    public function handle(PricingService $pricing): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        Product::whereIn('id', $this->productIds)->get()->each(function (Product $p) use ($pricing) {
            $pricing->refreshCachedPrice($p, $this->historyByCategory[$p->gold_category_id] ?? null, $this->reason);
        });
    }
}
