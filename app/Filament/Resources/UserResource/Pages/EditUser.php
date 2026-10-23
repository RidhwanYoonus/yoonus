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
                ->requiresConfirmation()->modalDescription('The customer sees normal shop prices from now on and their new orders no longer count for the affiliate.')
                ->form([
                    \Filament\Forms\Components\Checkbox::make('stop')->label('Also stop commission from any affiliate link for this customer')
                        ->helperText('Even if they open an affiliate\'s link later, nothing is earned on their orders.'),
                    \Filament\Forms\Components\Checkbox::make('void_pending')->label('Cancel affiliate commission on their orders not delivered yet'),
                ])
                ->action(function (array $data) {
                    $this->record->forceFill(['signup_affiliate_id' => null, 'affiliate_markup_on' => true,
                        ...(! empty($data['stop']) ? ['affiliate_commission_off' => true] : [])])->save();
                    if (! empty($data['void_pending'])) {
                        app(\App\Affiliates\AffiliateService::class)->voidPending(customer: $this->record);
                    }
                    $this->refreshFormData(['affiliate_markup_on', 'affiliate_commission_off']);
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
