<?php

namespace App\Filament\Resources\StoneTypeResource\Pages;

use App\Filament\Resources\StoneTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageStoneTypes extends ManageRecords
{
    use \App\Filament\Concerns\ExportsTable;

    protected static string $resource = StoneTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
