<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Filament\Resources\StaffResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditStaff extends EditRecord
{
    use \App\Filament\Concerns\ReturnsAfterSave;

    protected static string $resource = StaffResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['access'] = $this->record->is_admin ? 'admin' : ($this->record->is_staff ? 'staff' : 'none');
        $data['permissions'] = $this->record->permissions ?? [];

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $data = StaffResource::toColumns($data);
        if ($record->getKey() === auth()->id()) {
            $data['is_admin'] = true;   // never lock yourself out
            $data['is_staff'] = false;
        }
        $record->forceFill($data)->save();

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
