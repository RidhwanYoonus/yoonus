<?php

namespace App\Filament\Resources\AffiliateApplicationResource\Pages;

use App\Filament\Resources\AffiliateApplicationResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAffiliateApplications extends ManageRecords
{
    use \App\Filament\Concerns\ExportsTable;

    protected static string $resource = AffiliateApplicationResource::class;
}
