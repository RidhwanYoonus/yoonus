<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\RateVsSalesChart;
use App\Support\Reports as ReportData;
use Filament\Pages\Page;

class Reports extends Page
{
    public static function canAccess(): bool
    {
        return \App\Support\StaffAccess::current('reports');
    }

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

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\ActionGroup::make([
                \Filament\Actions\Action::make('print')->label('Print')->icon('heroicon-o-printer')->action(fn () => $this->printReport()),
                \Filament\Actions\Action::make('export')->label('Export (Excel / CSV)')->icon('heroicon-o-arrow-down-tray')->action(fn () => $this->exportReport()),
            ])->label('Print / Export')->icon('heroicon-o-document-arrow-down')->button()->color('gray')
                ->visible(fn () => \App\Support\StaffAccess::current('export')),
        ];
    }

    /** The report as plain tables, for printing and export. */
    public function reportTables(): array
    {
        $d = $this->getViewData();
        $aed = fn ($v) => number_format((float) $v, 2);
        $k = $d['kpis'];

        return [
            ['title' => 'Summary — last '.$this->days.' days', 'headings' => ['Measure', 'Value'], 'rows' => [
                ['Revenue (AED)', $aed($k['revenue'])], ['Paid orders', (string) $k['orders']], ['Average order (AED)', $aed($k['aov'])],
                ['VAT collected (AED)', $aed($k['vat'])], ['Discounts given (AED)', $aed($k['discounts'])], ['Conversion (orders ÷ carts)', $k['conversion'].'%'],
                ['Abandoned carts', $k['abandoned_count'].' · AED '.$aed($k['abandoned_value'])], ['Recovered by reminders', (string) $k['recovered']],
                ['Gold rate vs. sales correlation', $d['correlation'] === null ? 'not enough data yet' : (string) $d['correlation']],
            ]],
            ['title' => 'Top products', 'headings' => ['Product', 'Karat', 'Units', 'Revenue (AED)'],
                'rows' => collect($d['products'])->map(fn ($p) => [(string) $p->product_name, (string) $p->karat, (string) $p->units, $aed($p->revenue)])->all()],
            ['title' => 'Sales by category', 'headings' => ['Category', 'Units', 'Revenue (AED)'],
                'rows' => collect($d['categories'])->map(fn ($c) => [(string) $c->category, (string) $c->units, $aed($c->revenue)])->all()],
            ['title' => 'Sales by karat', 'headings' => ['Karat', 'Grams', 'Revenue (AED)'],
                'rows' => collect($d['karats'])->map(fn ($r) => [(string) $r->karat, number_format((float) $r->grams, 3), $aed($r->revenue)])->all()],
        ];
    }

    public function printReport(): void
    {
        $token = \Illuminate\Support\Str::random(40);
        \Illuminate\Support\Facades\Cache::put('print:'.$token, ['user_id' => auth()->id(), 'title' => 'Sales report', 'tables' => $this->reportTables()], now()->addMinutes(30));
        $url = route('admin.print', $token);
        $this->js('window.open('.json_encode($url).', "_blank")');
        \Filament\Notifications\Notification::make()->title('Print page opened in a new tab')->body('If your browser blocked it, use the button below.')
            ->actions([\Filament\Notifications\Actions\Action::make('open')->label('Open print page')->url($url, shouldOpenInNewTab: true)])->success()->send();
    }

    public function exportReport()
    {
        $tables = $this->reportTables();

        return response()->streamDownload(function () use ($tables) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($tables as $i => $t) {
                if ($i) {
                    fputcsv($out, []);
                }
                fputcsv($out, [$t['title']]);
                fputcsv($out, $t['headings']);
                foreach ($t['rows'] as $row) {
                    fputcsv($out, $row);
                }
            }
            fclose($out);
        }, 'sales-report-'.$this->days.'-days-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
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
