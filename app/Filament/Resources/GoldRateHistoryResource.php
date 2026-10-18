<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GoldRateHistoryResource\Pages;
use App\Models\GoldRateHistory;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use App\Filament\Concerns\HasStaffArea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Read-only audit log: every rate change, who made it, and the product prices it produced. */
class GoldRateHistoryResource extends Resource
{
    use HasStaffArea;

    protected static string $staffArea = 'gold_rates';

    protected static ?string $model = GoldRateHistory::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Gold rates';

    protected static ?string $navigationLabel = 'Rate audit log';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('effective_from')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('goldCategory.name')->label('Karat')->badge(),
                Tables\Columns\TextColumn::make('previous_rate')->money('AED')->placeholder('—'),
                Tables\Columns\TextColumn::make('rate_per_gram')->label('New rate')->money('AED')->weight('bold'),
                Tables\Columns\TextColumn::make('input')->label('Entered as')->state(fn ($record) => $record->input_value.' / '.str_replace('_', ' ', $record->input_unit)),
                Tables\Columns\TextColumn::make('source')->badge(),
                Tables\Columns\TextColumn::make('changedBy.name')->label('Changed by')->placeholder('System'),
                Tables\Columns\TextColumn::make('price_changes_count')->counts('priceChanges')->label('Prices changed'),
            ])
            ->filters([Tables\Filters\SelectFilter::make('gold_category_id')->relationship('goldCategory', 'name')->label('Karat')])
            ->actions([Tables\Actions\ViewAction::make()]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make()->columns(4)->schema([
                Infolists\Components\TextEntry::make('goldCategory.name')->label('Karat'),
                Infolists\Components\TextEntry::make('previous_rate')->money('AED'),
                Infolists\Components\TextEntry::make('rate_per_gram')->money('AED'),
                Infolists\Components\TextEntry::make('changedBy.name')->placeholder('System'),
            ]),
            Infolists\Components\RepeatableEntry::make('priceChanges')->label('Resulting product prices')->columns(4)->columnSpanFull()->schema([
                Infolists\Components\TextEntry::make('product.name'),
                Infolists\Components\TextEntry::make('old_price')->money('AED'),
                Infolists\Components\TextEntry::make('new_price')->money('AED'),
                Infolists\Components\TextEntry::make('created_at')->dateTime(),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageGoldRateHistories::route('/')];
    }
}
