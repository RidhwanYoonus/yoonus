<?php

namespace App\Filament\Resources\GoldCategoryResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class HistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'history';

    protected static ?string $title = 'Rate history';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('effective_from', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('effective_from')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('previous_rate')->money('AED')->placeholder('—'),
                Tables\Columns\TextColumn::make('rate_per_gram')->label('New rate')->money('AED')->weight('bold'),
                Tables\Columns\TextColumn::make('change')->state(fn ($record) => $record->previous_rate ? round(($record->rate_per_gram - $record->previous_rate) / $record->previous_rate * 100, 2) : null)
                    ->suffix('%')->color(fn ($state) => $state > 0 ? 'danger' : 'success')->placeholder('—'),
                Tables\Columns\TextColumn::make('source')->badge(),
                Tables\Columns\TextColumn::make('changedBy.name')->label('Changed by')->placeholder('System'),
                Tables\Columns\TextColumn::make('affected_products')->label('Products'),
            ]);
    }
}
