<?php

namespace App\Filament\Resources\AffiliateResource\RelationManagers;

use App\Filament\Resources\AffiliatePayoutResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class PayoutsRelationManager extends RelationManager
{
    protected static string $relationship = 'payouts';

    protected static ?string $title = 'Collected at store';

    public function table(Table $table): Table
    {
        return AffiliatePayoutResource::table($table, withAffiliate: false);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
