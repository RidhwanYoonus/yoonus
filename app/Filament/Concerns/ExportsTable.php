<?php

namespace App\Filament\Concerns;

use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Adds "Print" and "Export (Excel / CSV)" to a list page's table.
 * Both use the rows the filters, search, tab and sort currently show, and only the columns ticked in the column menu.
 */
trait ExportsTable
{
    public function table(Table $table): Table
    {
        return parent::table($table)->pushHeaderActions([
            ActionGroup::make([
                Action::make('printTable')->label('Print')->icon('heroicon-o-printer')
                    ->action(fn () => $this->openTablePrint()),
                Action::make('exportTable')->label('Export (Excel / CSV)')->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => $this->downloadTableCsv()),
            ])->label('Print / Export')->icon('heroicon-o-document-arrow-down')->button()->color('gray'),
        ]);
    }

    /** @return array{0: array<string>, 1: \Closure(): iterable<array<string>>} */
    public function tableExportData(int $limit = 0): array
    {
        $columns = array_values(array_filter($this->getTable()->getVisibleColumns(),
            fn ($c) => ! $c instanceof \Filament\Tables\Columns\ImageColumn));
        $headings = array_map(fn ($c) => strip_tags((string) $c->getLabel()), $columns);
        $query = $this->getFilteredSortedTableQuery();
        if ($limit) {
            $query->limit($limit);
        }

        $rows = function () use ($columns, $query) {
            foreach ($query->lazy(200) as $record) {
                yield array_map(fn ($c) => self::cellText($c->record($record)), $columns);
            }
        };

        return [$headings, $rows];
    }

    public static function cellText(\Filament\Tables\Columns\Column $column): string
    {
        if ($column instanceof \Filament\Tables\Columns\TextColumn) {
            $column->limit(null)->words(null);   // full text in the file, not the shortened "…" shown on screen
        }
        $state = $column->getState();
        if ($column instanceof \Filament\Tables\Columns\IconColumn || $column instanceof \Filament\Tables\Columns\ToggleColumn || $column instanceof \Filament\Tables\Columns\CheckboxColumn) {
            return is_bool($state) || $state === 0 || $state === 1 || $state === '0' || $state === '1' ? ($state ? 'Yes' : 'No') : (string) $state;
        }
        $values = $state instanceof \Illuminate\Support\Collection ? $state->all() : (is_array($state) ? $state : [$state]);
        $text = [];
        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $value = method_exists($column, 'formatState') ? $column->formatState($value) : $value;
            $value = $value instanceof \BackedEnum ? $value->value : $value;
            $value = $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i') : $value;
            if (is_array($value) || is_object($value) && ! method_exists($value, '__toString')) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $text[] = trim(html_entity_decode(strip_tags((string) $value)));
        }

        $cell = implode(', ', array_filter($text, fn ($t) => $t !== ''));
        // Keep the small line shown above/below a value (e.g. the SKU under a product name).
        foreach (['getDescriptionAbove', 'getDescriptionBelow'] as $method) {
            if (method_exists($column, $method) && filled($extra = rescue(fn () => $column->{$method}(), null, false))) {
                $extra = trim(html_entity_decode(strip_tags($extra instanceof \Illuminate\Contracts\Support\Htmlable ? $extra->toHtml() : (string) $extra)));
                $cell = $extra === '' ? $cell : ($method === 'getDescriptionAbove' ? $extra.' · '.$cell : $cell.' · '.$extra);
            }
        }

        return $cell;
    }

    protected function tableExportTitle(): string
    {
        return strip_tags((string) $this->getTitle());
    }

    public function downloadTableCsv()
    {
        [$headings, $rows] = $this->tableExportData();
        $name = Str::slug($this->tableExportTitle()).'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // so Excel reads Arabic and AED correctly
            fputcsv($out, $headings);
            foreach ($rows() as $row) {
                fputcsv($out, array_map(fn ($v) => preg_match('/^[=+\-@]/', $v) && ! is_numeric($v) ? "'".$v : $v, $row));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function openTablePrint(): void
    {
        [$headings, $rows] = $this->tableExportData(5000);
        $token = Str::random(40);
        Cache::put('print:'.$token, [
            'user_id' => auth()->id(), 'title' => $this->tableExportTitle(),
            'headings' => $headings, 'rows' => iterator_to_array($rows(), false),
        ], now()->addMinutes(30));
        $url = route('admin.print', $token);
        $this->js('window.open('.json_encode($url).', "_blank")');
        \Filament\Notifications\Notification::make()->title('Print page opened in a new tab')
            ->body('If your browser blocked it, use the button below.')
            ->actions([\Filament\Notifications\Actions\Action::make('open')->label('Open print page')->url($url, shouldOpenInNewTab: true)])
            ->success()->send();
    }
}
