<?php

namespace App\Filament\Resources\PendingCartResource\Pages;

use App\Filament\Resources\PendingCartResource;
use App\Filament\Resources\PendingCartResource\Widgets\PendingCartStats;
use Filament\Resources\Pages\ListRecords;

class ListPendingCarts extends ListRecords
{
    protected static string $resource = PendingCartResource::class;

    protected function getHeaderWidgets(): array
    {
        return [PendingCartStats::class];
    }
}
