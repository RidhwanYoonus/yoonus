<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\RateVsSalesChart;
use App\Support\Reports as ReportData;
use Filament\Pages\Page;

class Reports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.reports';

    public int $days = 30;

    protected function getFooterWidgets(): array
    {
        return [RateVsSalesChart::class];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getViewData(): array
    {
        $report = ReportData::lastDays(max(1, min(730, $this->days)));

        return [
            'kpis' => $report->kpis(),
            'products' => $report->byProduct(),
            'categories' => $report->byCategory(),
            'karats' => $report->byKarat(),
            'correlation' => $report->rateVsSales()['correlation'],
        ];
    }
}
