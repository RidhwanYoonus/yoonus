<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Resources\ActivityLogResource;
use Filament\Resources\Pages\ListRecords;

class ListActivityLogs extends ListRecords
{
    use \App\Filament\Concerns\ExportsTable;

    protected static string $resource = ActivityLogResource::class;

    public function getSubheading(): ?string
    {
        return 'Every page and step of customers, guests and staff. Click a name (or "All movements") to follow one person.';
    }
}
