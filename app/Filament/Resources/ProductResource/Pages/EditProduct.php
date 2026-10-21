<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Support\Images;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view')->url(fn () => route('product.show', $this->record))->openUrlInNewTab()->color('gray'),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $images = [...array_values($data['images'] ?? []), ...array_values($data['camera_photos'] ?? [])];
        unset($data['camera_photos']);
        $data['images'] = array_map(fn ($p) => Images::convertStored($p), array_slice($images, 0, 12));

        return $data;
    }
}
