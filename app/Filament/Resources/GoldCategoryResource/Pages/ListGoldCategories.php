<?php

namespace App\Filament\Resources\GoldCategoryResource\Pages;

use App\Filament\Resources\GoldCategoryResource;
use App\Filament\Widgets\RecalcProgress;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGoldCategories extends ListRecords
{
    protected static string $resource = GoldCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Add karat')];
    }

    protected function getHeaderWidgets(): array
    {
        return [RecalcProgress::class];
    }
}
