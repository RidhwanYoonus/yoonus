<?php

namespace App\Filament\Resources\GoldCategoryResource\Pages;

use App\Filament\Resources\GoldCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGoldCategory extends CreateRecord
{
    use \App\Filament\Concerns\ReturnsAfterSave;

    protected static string $resource = GoldCategoryResource::class;
}
