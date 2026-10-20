<?php

namespace App\Filament\Resources\BranchResource\Pages;

use App\Filament\Resources\BranchResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageBranches extends ManageRecords
{
    use \App\Filament\Concerns\ExportsTable;

    use \App\Filament\Concerns\TranslatesMissingArabic;

    protected static string $resource = BranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->translateMissingArabicAction(),
            Actions\CreateAction::make(),
        ];
    }

    protected function arabicPairs(): array
    {
        return ['name' => 'name_ar'];
    }
}
