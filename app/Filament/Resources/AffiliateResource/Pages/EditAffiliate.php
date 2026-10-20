<?php

namespace App\Filament\Resources\AffiliateResource\Pages;

use App\Filament\Resources\AffiliateResource;
use App\Pricing\Money;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditAffiliate extends EditRecord
{
    protected static string $resource = AffiliateResource::class;

    public function getSubheading(): ?string
    {
        $t = $this->record->totals();

        return 'Pending '.Money::format($t['pending']).' · Earned '.Money::format($t['earned']).' · Collected '.Money::format($t['collected'])
            .' · To collect '.Money::format($t['balance']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('links')->label('Their links')->icon('heroicon-o-link')->color('gray')
                ->modalSubmitAction(false)->modalCancelActionLabel('Close')
                ->modalContent(fn () => new \Illuminate\Support\HtmlString(
                    '<p class="text-sm">Referral link (normal prices):<br><code>'.e($this->record->referralLink()).'</code></p>'
                    .($this->record->white_page_enabled ? '<p class="mt-3 text-sm">White page (prices include their mark-up):<br><code>'.e($this->record->whitePageLink()).'</code></p>' : '')
                    .'<p class="mt-3 text-sm">Invite new affiliates (team):<br><code>'.e($this->record->joinLink()).'</code></p>'
                    .((float) $this->record->coupon_discount_percent > 0 ? '<p class="mt-3 text-sm">Coupon code: <code>'.e($this->record->code).'</code> ('.(float) $this->record->coupon_discount_percent.'% off)</p>' : ''))),
            \Filament\Actions\Action::make('storeSale')->label('In-store sale')->icon('heroicon-o-building-storefront')->color('warning')
                ->visible(fn () => \App\Support\StaffAccess::current('affiliates', 'edit'))
                ->form(\App\Filament\Resources\AffiliateResource::storeSaleForm())
                ->action(fn (array $data) => \App\Filament\Resources\AffiliateResource::runStoreSale($this->record, $data)),
            Actions\DeleteAction::make()->visible(fn () => ! $this->record->commissions()->exists()),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->record->user;

        return $data + ['user_name' => $user?->name, 'user_email' => $user?->email, 'user_phone' => $user?->phone];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data) {
            $user = $record->user;
            $user->forceFill(array_filter([
                'name' => $data['user_name'], 'email' => strtolower($data['user_email']), 'phone' => $data['user_phone'] ?? null,
                'password' => $data['user_password'] ?? null,
            ], fn ($v) => $v !== null))->save();
            $record->update(collect($data)->except(['user_name', 'user_email', 'user_phone', 'user_password'])->all());

            return $record;
        });
    }
}
