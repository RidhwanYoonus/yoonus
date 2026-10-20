<?php

namespace App\Filament\Resources\AffiliateResource\RelationManagers;

use App\Filament\Resources\AffiliateCommissionResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class CommissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'commissions';

    protected static ?string $title = 'Commission';

    public function table(Table $table): Table
    {
        return AffiliateCommissionResource::table($table, withAffiliate: false);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
