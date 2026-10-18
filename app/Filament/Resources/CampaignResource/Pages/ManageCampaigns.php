<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCampaigns extends ManageRecords
{
    use \App\Filament\Concerns\TranslatesMissingArabic;

    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->translateMissingArabicAction(),
            Actions\CreateAction::make(),
        ];
    }

    protected function arabicPairs(): array
    {
        return ['name' => 'name_ar', 'headline' => 'headline_ar'];
    }
}
