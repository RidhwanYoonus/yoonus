<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Filament\Resources\StaffResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStaff extends CreateRecord
{
    use \App\Filament\Concerns\ReturnsAfterSave;

    protected static string $resource = StaffResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = new User;
        $user->forceFill(StaffResource::toColumns($data) + ['email_verified_at' => now()])->save();

        return $user;
    }
}
