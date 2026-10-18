<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Support\ProductCsv;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')->label('Export CSV')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->action(fn () => response()->streamDownload(ProductCsv::export(), 'products-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8'])),
            Actions\Action::make('import')->visible(fn () => \App\Support\StaffAccess::current('products', 'create'))->label('Import CSV')->icon('heroicon-o-arrow-up-tray')->color('gray')
                ->modalDescription('Columns: '.implode(', ', ProductCsv::COLUMNS).'. Existing SKUs are updated; new SKUs are created. Prices are calculated automatically.')
                ->form([Forms\Components\FileUpload::make('file')->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])->disk('local')->directory('imports')->required()])
                ->action(function (array $data) {
                    $result = ProductCsv::import(Storage::disk('local')->path($data['file']));
                    Storage::disk('local')->delete($data['file']);
                    Notification::make()
                        ->title("Imported: {$result['created']} created, {$result['updated']} updated")
                        ->body($result['errors'] ? implode("\n", array_slice($result['errors'], 0, 10)) : null)
                        ->{$result['errors'] ? 'warning' : 'success'}()->persistent()->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
