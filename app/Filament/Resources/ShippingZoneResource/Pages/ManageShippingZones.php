<?php

namespace App\Filament\Resources\ShippingZoneResource\Pages;

use App\Filament\Resources\ShippingZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageShippingZones extends ManageRecords
{
    use \App\Filament\Concerns\ExportsTable;

    use \App\Filament\Concerns\TranslatesMissingArabic;

    protected static string $resource = ShippingZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->translateMissingArabicAction(),
            Actions\CreateAction::make(),
        ];
    }

    protected function arabicPairs(): array
    {
        return ['name' => 'name_ar'];
    }
}
