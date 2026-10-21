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
    use \App\Filament\Concerns\ExportsTable;

    use \App\Filament\Concerns\TranslatesMissingArabic;

    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->translateMissingArabicAction(),
            \Filament\Actions\Action::make('fillAllSeo')->label('Fill empty SEO')->icon('heroicon-o-sparkles')->color('gray')
                ->requiresConfirmation()->modalHeading('Fill empty SEO for the whole shop')
                ->modalDescription('Writes the page title and description (English and Arabic) for every product and category where a box is empty, from their details. Text already there is not changed.')
                ->action(function () {
                    $n = \App\Support\SeoText::fillAllBlanks();
                    \Filament\Notifications\Notification::make()->success()->title('SEO filled')
                        ->body("Products: {$n['products']} · Categories: {$n['categories']}")->send();
                }),
            Actions\Action::make('export')->label('Export CSV')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->action(fn () => response()->streamDownload(ProductCsv::export(), 'products-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8'])),
            Actions\ActionGroup::make([
                Actions\Action::make('sample')->label('Download sample CSV')->icon('heroicon-o-document-text')
                    ->action(fn () => response()->streamDownload(ProductCsv::sample(), 'products-import-sample.csv', ['Content-Type' => 'text/csv; charset=UTF-8'])),
            Actions\Action::make('import')->visible(fn () => \App\Support\StaffAccess::current('products', 'create'))->label('Import CSV')->icon('heroicon-o-arrow-up-tray')->color('gray')
                ->modalDescription('Existing SKUs are updated; new SKUs are created. Prices are calculated automatically. An empty cell keeps what the product already has. Start from "Download sample CSV" in the Import menu to get the columns right.')
                ->modalWidth('2xl')
                ->form([
                    Forms\Components\Placeholder::make('columns')->label('Columns')
                        ->content(new \Illuminate\Support\HtmlString('<p class="text-sm">'.e(implode(', ', ProductCsv::COLUMNS)).'</p><ul class="mt-2 list-disc ps-5 text-xs text-gray-500">'
                            .collect(ProductCsv::GUIDE)->map(fn ($v, $k) => '<li><b>'.e($k).'</b>: '.e($v).'</li>')->implode('').'</ul>')),
                    Forms\Components\FileUpload::make('file')->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])->disk('local')->directory('imports')->required()])
                ->action(function (array $data) {
                    $result = ProductCsv::import(Storage::disk('local')->path($data['file']));
                    Storage::disk('local')->delete($data['file']);
                    Notification::make()
                        ->title("Imported: {$result['created']} created, {$result['updated']} updated")
                        ->body($result['errors'] ? implode("\n", array_slice($result['errors'], 0, 10)) : null)
                        ->{$result['errors'] ? 'warning' : 'success'}()->persistent()->send();
                }),
            ])->label('Import')->icon('heroicon-o-arrow-up-tray')->button()->color('gray')
                ->visible(fn () => \App\Support\StaffAccess::current('products', 'create')),
            Actions\CreateAction::make(),
        ];
    }

    protected function arabicPairs(): array
    {
        return ['name' => 'name_ar', 'description' => 'description_ar'];
    }
}
