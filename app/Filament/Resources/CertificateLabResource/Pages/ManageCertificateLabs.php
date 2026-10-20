<?php

namespace App\Filament\Resources\CertificateLabResource\Pages;

use App\Filament\Resources\CertificateLabResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCertificateLabs extends ManageRecords
{
    use \App\Filament\Concerns\ExportsTable;

    protected static string $resource = CertificateLabResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
