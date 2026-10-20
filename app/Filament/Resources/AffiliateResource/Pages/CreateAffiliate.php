<?php

namespace App\Filament\Resources\AffiliateResource\Pages;

use App\Affiliates\AffiliateService;
use App\Filament\Resources\AffiliateResource;
use App\Models\Affiliate;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateAffiliate extends CreateRecord
{
    protected static string $resource = AffiliateResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $user = new User;
            // Never staff or admin: an affiliate only sees their own dashboard.
            $user->forceFill([
                'name' => $data['user_name'], 'email' => strtolower($data['user_email']), 'phone' => $data['user_phone'] ?? null,
                'password' => $data['user_password'], 'email_verified_at' => now(), 'is_admin' => false, 'is_staff' => false,
            ])->save();

            return Affiliate::create([
                'user_id' => $user->id,
                'code' => strtoupper(($data['code'] ?? '') ?: AffiliateService::newCode($data['user_name'])),
            ] + collect($data)->except(['user_name', 'user_email', 'user_phone', 'user_password', 'code'])->all());
        });
    }

    protected function getRedirectUrl(): string
    {
        return AffiliateResource::getUrl('edit', ['record' => $this->record]);
    }
}
