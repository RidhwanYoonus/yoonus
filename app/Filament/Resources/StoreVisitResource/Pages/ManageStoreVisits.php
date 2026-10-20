<?php

namespace App\Filament\Resources\StoreVisitResource\Pages;

use App\Filament\Resources\StoreVisitResource;
use Filament\Resources\Pages\ManageRecords;

class ManageStoreVisits extends ManageRecords
{
    use \App\Filament\Concerns\ExportsTable;

    protected static string $resource = StoreVisitResource::class;
}
