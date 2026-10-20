<?php

namespace App\Filament\Resources\GoldRateHistoryResource\Pages;

use App\Filament\Resources\GoldRateHistoryResource;
use Filament\Resources\Pages\ManageRecords;

class ManageGoldRateHistories extends ManageRecords
{
    use \App\Filament\Concerns\ExportsTable;

    protected static string $resource = GoldRateHistoryResource::class;
}
