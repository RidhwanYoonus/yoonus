<?php

namespace App\Filament\Widgets;

use App\Support\Reports;
use Filament\Widgets\ChartWidget;

class RateVsSalesChart extends ChartWidget
{
    public static function canView(): bool
    {
        return \App\Support\StaffAccess::current('reports');
    }

    protected static ?string $heading = '22K gold rate vs. daily revenue';

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return ['30' => 'Last 30 days', '90' => 'Last 90 days', '365' => 'Last 12 months'];
    }

    protected function getData(): array
    {
        $data = Reports::lastDays((int) $this->filter)->rateVsSales()['days'];

        return [
            'datasets' => [
                ['label' => 'Revenue (AED)', 'data' => array_column($data, 'revenue'), 'yAxisID' => 'y', 'borderColor' => '#b8954a', 'backgroundColor' => 'rgba(184,149,74,.2)', 'type' => 'bar'],
                ['label' => '22K rate (AED/g)', 'data' => array_column($data, 'rate'), 'yAxisID' => 'y1', 'borderColor' => '#17120d', 'tension' => .3, 'type' => 'line'],
            ],
            'labels' => array_column($data, 'date'),
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['position' => 'left'], 'y1' => ['position' => 'right', 'grid' => ['drawOnChartArea' => false]]]];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
