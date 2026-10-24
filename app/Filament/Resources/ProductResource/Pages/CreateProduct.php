<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Support\Images;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use \App\Filament\Concerns\ReturnsAfterSave;

    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\Action::make('createTop')->label('Create')->icon('heroicon-o-check')->color('primary')->action(fn () => $this->create())];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $images = [...array_values($data['images'] ?? []), ...array_values($data['camera_photos'] ?? [])];
        unset($data['camera_photos']);
        $data = ProductResource::touchUpNewPhotos($data, $images, []);
        $data['images'] = array_map(fn ($p) => Images::convertStored($p), array_slice($images, 0, 12));

        return $data;
    }
}
