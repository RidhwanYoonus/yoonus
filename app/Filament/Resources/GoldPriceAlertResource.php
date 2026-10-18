<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GoldPriceAlertResource\Pages;
use App\Models\GoldPriceAlert;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Customers who asked to be told when a karat's gold rate drops (homepage / account form). */
class GoldPriceAlertResource extends Resource
{
    protected static ?string $model = GoldPriceAlert::class;

    protected static ?string $slug = 'gold-price-alerts';

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Gold price alerts';

    protected static ?string $pluralModelLabel = 'Gold price alerts';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('goldCategory'))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Signed up')->dateTime('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('phone')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('goldCategory.name')->label('Karat')->badge(),
                Tables\Columns\TextColumn::make('target_rate')->label('Alert below')->placeholder('Any drop')->suffix(' AED/g'),
                Tables\Columns\TextColumn::make('last_notified_at')->label('Last alerted')->since()->placeholder('Not yet'),
                Tables\Columns\ToggleColumn::make('is_active')->label('Active'),
            ])
            ->filters([Tables\Filters\SelectFilter::make('gold_category_id')->label('Karat')->relationship('goldCategory', 'name')])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListGoldPriceAlerts::route('/')];
    }
}
