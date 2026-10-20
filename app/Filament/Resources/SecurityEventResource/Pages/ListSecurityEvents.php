<?php

namespace App\Filament\Resources\SecurityEventResource\Pages;

use App\Filament\Resources\SecurityEventResource;
use Filament\Resources\Pages\ListRecords;

class ListSecurityEvents extends ListRecords
{
    use \App\Filament\Concerns\ExportsTable;

    protected static string $resource = SecurityEventResource::class;
}
