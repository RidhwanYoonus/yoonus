<?php

namespace App\Filament\Resources\GoldCategoryResource\Pages;

use App\Filament\Resources\GoldCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGoldCategory extends EditRecord
{
    protected static string $resource = GoldCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
