<?php

namespace App\Filament\Resources\AffiliateResource\Pages;

use App\Filament\Resources\AffiliateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAffiliates extends ListRecords
{
    use \App\Filament\Concerns\ExportsTable;

    protected static string $resource = AffiliateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('New affiliate')];
    }
}
