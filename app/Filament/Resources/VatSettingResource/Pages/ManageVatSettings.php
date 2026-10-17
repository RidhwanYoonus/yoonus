<?php

namespace App\Filament\Resources\VatSettingResource\Pages;

use App\Filament\Resources\VatSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageVatSettings extends ManageRecords
{
    protected static string $resource = VatSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
