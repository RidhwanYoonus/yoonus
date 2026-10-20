<?php

namespace App\Filament\Resources\AffiliateCampaignResource\Pages;

use App\Filament\Resources\AffiliateCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAffiliateCampaigns extends ManageRecords
{
    use \App\Filament\Concerns\ExportsTable;

    protected static string $resource = AffiliateCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('New campaign')];
    }
}
