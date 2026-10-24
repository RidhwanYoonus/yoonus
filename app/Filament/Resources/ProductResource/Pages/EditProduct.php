<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Support\Images;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    use \App\Filament\Concerns\ReturnsAfterSave;

    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Opens over this product page (no new tab), so phones stay on the same screen; saved photos show up here.
            Actions\Action::make('studio')->label('Photo studio')->icon('heroicon-o-sparkles')->color('warning')
                ->modalHeading('Photo studio — beautiful backgrounds')->modalWidth(\Filament\Support\Enums\MaxWidth::Screen)
                ->modalContent(fn () => view('filament.photo-studio-modal', ['url' => route('admin.photo-studio', ['product' => $this->record, 'embed' => 1])]))
                ->modalSubmitAction(false)->modalCancelActionLabel('Close')
                ->after(fn () => $this->studioSaved()),
            Actions\Action::make('view')->url(fn () => route('product.show', $this->record))->openUrlInNewTab()->color('gray'),
            Actions\DeleteAction::make(),
        ];
    }

    /** Photos were changed in the Photo studio: show them in the form without losing other unsaved edits. */
    public function studioSaved(): void
    {
        $this->record->refresh();
        $this->refreshFormData(['images']);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $images = [...array_values($data['images'] ?? []), ...array_values($data['camera_photos'] ?? [])];
        unset($data['camera_photos']);
        $data = ProductResource::touchUpNewPhotos($data, $images, (array) ($this->record->images ?? []));
        $data['images'] = array_map(fn ($p) => Images::convertStored($p), array_slice($images, 0, 12));

        return $data;
    }
}
