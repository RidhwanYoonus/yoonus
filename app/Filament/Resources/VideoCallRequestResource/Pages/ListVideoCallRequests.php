<?php

namespace App\Filament\Resources\VideoCallRequestResource\Pages;

use App\Filament\Resources\VideoCallRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListVideoCallRequests extends ListRecords
{
    use \App\Filament\Concerns\ExportsTable;

    protected static string $resource = VideoCallRequestResource::class;
}
