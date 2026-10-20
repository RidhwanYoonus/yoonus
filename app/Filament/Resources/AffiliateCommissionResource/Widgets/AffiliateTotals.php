<?php

namespace App\Filament\Resources\AffiliateCommissionResource\Widgets;

use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Pricing\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AffiliateTotals extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        // Commission lines plus bonuses / in-store sales.
        $sum = fn (string $status) => Money::toFils((string) AffiliateCommission::where('status', $status)->sum('amount'))
            + rescue(fn () => Money::toFils((string) \App\Models\AffiliateAdjustment::where('status', $status)->sum('amount')), 0, false);
        $earned = $sum('available');
        $collected = Money::toFils((string) AffiliatePayout::sum('amount'));

        return [
            Stat::make('Pending (not delivered yet)', Money::format($sum('pending'))),
            Stat::make('Earned', Money::format($earned)),
            Stat::make('Collected at store', Money::format($collected)),
            Stat::make('Still to collect', Money::format($earned - $collected))->color('success'),
        ];
    }
}
