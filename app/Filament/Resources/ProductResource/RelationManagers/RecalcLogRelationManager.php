<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RecalcLogRelationManager extends RelationManager
{
    protected static string $relationship = 'recalcLog';

    protected static ?string $title = 'Price history';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            Tables\Columns\TextColumn::make('created_at')->dateTime(),
            Tables\Columns\TextColumn::make('old_price')->money('AED')->placeholder('—'),
            Tables\Columns\TextColumn::make('new_price')->money('AED')->weight('bold'),
            Tables\Columns\TextColumn::make('rate_used')->label('Rate / g')->money('AED'),
            Tables\Columns\TextColumn::make('reason')->badge(),
            Tables\Columns\TextColumn::make('rateChange.changedBy.name')->label('Rate changed by')->placeholder('—'),
        ]);
    }
}
