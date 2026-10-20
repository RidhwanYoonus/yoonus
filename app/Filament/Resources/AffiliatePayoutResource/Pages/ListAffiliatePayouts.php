<?php

namespace App\Filament\Resources\AffiliatePayoutResource\Pages;

use App\Filament\Resources\AffiliatePayoutResource;
use Filament\Resources\Pages\ListRecords;

class ListAffiliatePayouts extends ListRecords
{
    use \App\Filament\Concerns\ExportsTable;

    protected static string $resource = AffiliatePayoutResource::class;
}
