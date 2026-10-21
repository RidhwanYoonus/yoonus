<?php

namespace App\Filament\Widgets;

use App\Models\GoldCategory;
use App\Support\Reports;
use Filament\Widgets\StatsOverviewWidget as Base;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends Base
{
    public static function canView(): bool
    {
        return \App\Support\StaffAccess::current('reports');
    }

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = Reports::lastDays(1)->kpis();
        $month = Reports::lastDays(30)->kpis();
        $base = GoldCategory::orderByDesc('karat')->first();

        return [
            Stat::make('Revenue today', 'AED '.number_format($today['revenue'], 2))->description($today['orders'].' paid order(s)'),
            Stat::make('Revenue · 30 days', 'AED '.number_format($month['revenue'], 2))->description('AOV AED '.number_format($month['aov'], 0)),
            Stat::make('Conversion · 30 days', $month['conversion'].'%')->description($month['abandoned_count'].' abandoned cart(s)'),
            Stat::make(($base?->name ?? 'Gold').' rate', 'AED '.($base?->rate_per_gram ?? '—').'/g')
                ->description(($base?->rate_updated_at ? 'Updated '.$base->rate_updated_at->diffForHumans() : 'Never updated')
                    .((($auto = \App\Support\AutoTasks::goldRate()) && ! $auto['ok']) ? ' · Auto update failed — see Settings → Pricing' : ''))
                ->color($base?->isStale() ? 'danger' : 'success'),
        ];
    }
}
