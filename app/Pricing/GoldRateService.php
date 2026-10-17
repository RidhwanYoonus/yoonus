<?php

namespace App\Pricing;

use App\Jobs\NotifyGoldPriceDrop;
use App\Jobs\RecalculatePriceBatch;
use App\Models\GoldCategory;
use App\Models\GoldRateHistory;
use App\Models\Product;
use App\Support\StorefrontCache;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GoldRateService
{
    public function __construct(private readonly PricingService $pricing) {}

    public static function toPerGram(float|string $value, string $unit): string
    {
        $grams = config("shop.rate_units.$unit") ?? throw new InvalidArgumentException("Unknown rate unit [$unit].");

        return number_format(round((float) $value / $grams, 2), 2, '.', '');
    }

    /** Lower-karat rate derived from its base: base × (karat / base karat). */
    public static function derive(GoldCategory $base, GoldCategory $child): string
    {
        $fils = Money::divRound(Money::toFils($base->rate_per_gram) * Money::scaled($child->karat, 2), Money::scaled($base->karat, 2));

        return Money::toAed($fils);
    }

    /**
     * Set a category's rate, cascade it to auto-derived karats, write the audit trail,
     * and queue recalculation of every affected product (batches of 500).
     *
     * @return array{history: array<GoldRateHistory>, batch: ?Batch, affected: int}
     */
    public function updateRate(GoldCategory $category, float|string $value, string $unit = 'gram', ?int $userId = null, string $source = 'manual'): array
    {
        $perGram = self::toPerGram($value, $unit);
        if ((float) $perGram <= 0) {
            throw new InvalidArgumentException('Gold rate must be greater than zero.');
        }

        $histories = DB::transaction(function () use ($category, $perGram, $unit, $value, $userId, $source) {
            $histories = [];
            $this->apply($category, $perGram, $userId, $source, $histories, $unit, (string) $value);

            return $histories;
        });

        $this->pricing->bumpVersion();
        StorefrontCache::flushPriceDependent();

        $historyByCategory = collect($histories)->mapWithKeys(fn (GoldRateHistory $h) => [$h->gold_category_id => $h->id])->all();
        $ids = Product::whereIn('gold_category_id', array_keys($historyByCategory))->pluck('id');

        $batch = null;
        if ($ids->isNotEmpty()) {
            $jobs = $ids->chunk((int) config('shop.recalc_chunk', 500))
                ->map(fn ($chunk) => new RecalculatePriceBatch($chunk->values()->all(), $historyByCategory))
                ->values()->all();
            $batch = Bus::batch($jobs)->name('Gold rate update: '.$category->name)->allowFailures()->dispatch();
        }

        foreach ($histories as $h) {
            $h->forceFill([
                'recalc_batch_id' => $batch?->id,
                'affected_products' => Product::where('gold_category_id', $h->gold_category_id)->count(),
            ])->saveQuietly();
            if ($h->previous_rate !== null && (float) $h->rate_per_gram < (float) $h->previous_rate) {
                NotifyGoldPriceDrop::dispatch($h->id);
            }
        }

        return ['history' => $histories, 'batch' => $batch, 'affected' => $ids->count()];
    }

    private function apply(GoldCategory $category, string $perGram, ?int $userId, string $source, array &$histories, string $unit = 'gram', ?string $input = null): void
    {
        $previous = $category->rate_per_gram;
        $category->forceFill(['rate_per_gram' => $perGram, 'rate_updated_at' => now()])->save();

        $histories[] = GoldRateHistory::create([
            'gold_category_id' => $category->id,
            'previous_rate' => $previous > 0 ? $previous : null,
            'rate_per_gram' => $perGram,
            'input_unit' => $unit,
            'input_value' => $input ?? $perGram,
            'effective_from' => now(),
            'changed_by' => $userId,
            'source' => $source,
        ]);

        foreach ($category->derived()->where('auto_derive', true)->get() as $child) {
            $this->apply($child, self::derive($category, $child), $userId, $source === 'manual' ? 'derived' : $source, $histories);
        }
    }
}
