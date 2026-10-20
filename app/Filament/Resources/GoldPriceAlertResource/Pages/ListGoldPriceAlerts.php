<?php

namespace App\Filament\Resources\GoldPriceAlertResource\Pages;

use App\Filament\Resources\GoldPriceAlertResource;
use Filament\Resources\Pages\ListRecords;

class ListGoldPriceAlerts extends ListRecords
{
    use \App\Filament\Concerns\ExportsTable;

    protected static string $resource = GoldPriceAlertResource::class;
}
