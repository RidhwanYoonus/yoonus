<?php

namespace App\Filament\Resources\AffiliateResource\RelationManagers;

use App\Models\AffiliateAdjustment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Table;

class AdjustmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'adjustments';

    protected static ?string $title = 'Bonuses & in-store sales';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            Tables\Columns\TextColumn::make('created_at')->label('Date')->dateTime('d M Y'),
            Tables\Columns\TextColumn::make('type')->badge()->formatStateUsing(fn ($state) => AffiliateAdjustment::TYPES[$state] ?? $state),
            Tables\Columns\TextColumn::make('description')->wrap(),
            Tables\Columns\TextColumn::make('createdBy.name')->label('By')->placeholder('Automatic'),
            Tables\Columns\TextColumn::make('amount')->money('AED')->weight('bold')->summarize(Sum::make()->money('AED')->label('')),
        ]);
    }
}
