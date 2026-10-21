<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCategories extends ManageRecords
{
    use \App\Filament\Concerns\ExportsTable;

    use \App\Filament\Concerns\TranslatesMissingArabic;

    protected static string $resource = CategoryResource::class;

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
            Actions\CreateAction::make(),
        ];
    }

    protected function arabicPairs(): array
    {
        return ['name' => 'name_ar', 'description' => 'description_ar'];
    }
}
