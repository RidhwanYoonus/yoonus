<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    use \App\Filament\Concerns\ReturnsAfterSave;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('removeAffiliateLink')->label('Remove affiliate link')->icon('heroicon-o-link-slash')->color('danger')
                ->visible(fn () => (bool) $this->record->signup_affiliate_id)
                ->requiresConfirmation()->modalDescription('The customer sees normal shop prices from now on. Past orders and commissions are not changed.')
                ->action(function () {
                    $this->record->forceFill(['signup_affiliate_id' => null, 'affiliate_markup_on' => true])->save();
                    $this->refreshFormData(['affiliate_markup_on']);
                    \Filament\Notifications\Notification::make()->success()->title('Affiliate link removed')->send();
                }),
        ];
    }

    /** is_admin is not mass-assignable from the storefront; only admins set it here. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->forceFill($data)->save();

        return $record;
    }
}
