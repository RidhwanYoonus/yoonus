<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCategories extends ManageRecords
{
    use \App\Filament\Concerns\TranslatesMissingArabic;

    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->translateMissingArabicAction(),
            Actions\CreateAction::make(),
        ];
    }

    protected function arabicPairs(): array
    {
        return ['name' => 'name_ar', 'description' => 'description_ar'];
    }
}
