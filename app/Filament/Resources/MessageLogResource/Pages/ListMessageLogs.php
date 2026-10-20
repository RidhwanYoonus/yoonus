<?php

namespace App\Filament\Resources\MessageLogResource\Pages;

use App\Filament\Resources\MessageLogResource;
use App\Filament\Resources\MessageLogResource\Widgets\MessageStats;
use Filament\Resources\Pages\ListRecords;

class ListMessageLogs extends ListRecords
{
    use \App\Filament\Concerns\ExportsTable;

    protected static string $resource = MessageLogResource::class;

    protected function getHeaderWidgets(): array
    {
        return [MessageStats::class];
    }
}
