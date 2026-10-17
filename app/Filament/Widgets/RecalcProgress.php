<?php

namespace App\Filament\Widgets;

use App\Models\GoldRateHistory;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Bus;

/** Progress bar for the most recent price recalculation batch (polls every 2s). */
class RecalcProgress extends Widget
{
    protected static string $view = 'filament.recalc-progress';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    public function getViewData(): array
    {
        $last = GoldRateHistory::whereNotNull('recalc_batch_id')->latest('id')->first();
        $batch = $last ? Bus::findBatch($last->recalc_batch_id) : null;

        return [
            'history' => $last,
            'batch' => $batch,
            'products' => $last ? GoldRateHistory::where('recalc_batch_id', $last->recalc_batch_id)->sum('affected_products') : 0,
        ];
    }
}
