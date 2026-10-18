<?php

namespace App\Filament\Widgets;

use App\Support\Reports;
use Filament\Widgets\ChartWidget;

class SalesChart extends ChartWidget
{
    public static function canView(): bool
    {
        return \App\Support\StaffAccess::current('reports');
    }

    protected static ?string $heading = 'Revenue · last 30 days';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $days = Reports::lastDays(30)->rateVsSales()['days'];

        return [
            'datasets' => [['label' => 'AED', 'data' => array_column($days, 'revenue'), 'borderColor' => '#b8954a', 'backgroundColor' => 'rgba(184,149,74,.15)', 'fill' => true, 'tension' => .3]],
            'labels' => array_map(fn ($data) => substr($data, 5), array_column($days, 'date')),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
