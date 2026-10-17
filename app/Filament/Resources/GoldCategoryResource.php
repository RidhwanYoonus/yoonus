<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GoldCategoryResource\Pages;
use App\Filament\Resources\GoldCategoryResource\RelationManagers\HistoryRelationManager;
use App\Models\GoldCategory;
use App\Pricing\GoldRateService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class GoldCategoryResource extends Resource
{
    protected static ?string $model = GoldCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Gold rates';

    protected static ?string $navigationLabel = 'Gold rate manager';

    protected static ?string $modelLabel = 'gold category';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Karat')->columns(3)->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(32)->unique(ignoreRecord: true)->placeholder('22K'),
                Forms\Components\TextInput::make('karat')->required()->numeric()->minValue(1)->maxValue(24)->helperText('Used to derive the rate: base × (karat ÷ base karat).'),
                Forms\Components\TextInput::make('purity_percent')->label('Purity %')->required()->numeric()->minValue(1)->maxValue(100),
            ]),
            Forms\Components\Section::make('Rate')->columns(3)->schema([
                Forms\Components\Toggle::make('auto_derive')->label('Derive rate from base karat')->live()->inline(false),
                Forms\Components\Select::make('base_category_id')->label('Base karat')
                    ->relationship('base', 'name', fn ($query, $record) => $query->when($record, fn ($q) => $q->whereKeyNot($record->id)))
                    ->visible(fn (Forms\Get $get) => $get('auto_derive'))->required(fn (Forms\Get $get) => $get('auto_derive')),
                Forms\Components\TextInput::make('rate_per_gram')->label('Rate (AED / gram)')->numeric()->prefix('AED')->disabled()
                    ->helperText('Change rates with the "Update rate" action so the change is audited and prices recalculate.'),
            ]),
            Forms\Components\Section::make()->columns(2)->schema([
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('karat', 'desc')
            ->poll('30s')
            ->columns([
                Tables\Columns\TextColumn::make('name')->weight('bold'),
                Tables\Columns\TextColumn::make('purity_percent')->label('Purity')->suffix('%'),
                Tables\Columns\TextColumn::make('rate_per_gram')->label('AED / g')->money('AED')->size('lg')->weight('bold'),
                Tables\Columns\TextColumn::make('per_tola')->label('AED / tola')->state(fn (GoldCategory $record) => round($record->rate_per_gram * config('shop.rate_units.tola'), 2))->money('AED')->color('gray'),
                Tables\Columns\TextColumn::make('base.name')->label('Derived from')->placeholder('Base rate')->badge(),
                Tables\Columns\TextColumn::make('products_count')->counts('products')->label('Products'),
                Tables\Columns\TextColumn::make('rate_updated_at')->label('Last updated')->since()->tooltip(fn ($record) => $record->rate_updated_at?->toDayDateTimeString())
                    ->color(fn (GoldCategory $record) => $record->isStale() ? 'danger' : 'success')
                    ->icon(fn (GoldCategory $record) => $record->isStale() ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([
                self::updateRateAction(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function updateRateAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('updateRate')
            ->label('Update rate')
            ->icon('heroicon-o-arrow-path')
            ->color('primary')
            ->button()
            ->modalHeading(fn (GoldCategory $record) => "Update {$record->name} rate")
            ->modalDescription(fn (GoldCategory $record) => $record->derived()->where('auto_derive', true)->exists()
                ? 'Derived karats ('.$record->derived()->pluck('name')->join(', ').') will update automatically.' : null)
            ->form([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('value')->label('New rate')->required()->numeric()->minValue(0.01)->prefix('AED')->autofocus(),
                    Forms\Components\Select::make('unit')->options(['gram' => 'per gram', 'ten_gram' => 'per 10 g', 'tola' => 'per tola (11.6638 g)'])->default('gram')->required()->live(),
                ]),
                Forms\Components\Placeholder::make('preview')->label('Rate per gram')
                    ->content(fn (Forms\Get $get) => is_numeric($get('value')) ? 'AED '.GoldRateService::toPerGram($get('value'), $get('unit') ?: 'gram').' / g' : '—'),
            ])
            ->action(function (GoldCategory $record, array $data) {
                $result = app(GoldRateService::class)->updateRate($record, $data['value'], $data['unit'], Auth::id());
                $names = collect($result['history'])->map(fn ($h) => $h->goldCategory->name.' → AED '.$h->rate_per_gram)->join(', ');
                Notification::make()->success()->title('Rate updated')
                    ->body("{$names}. Recalculating {$result['affected']} product price(s)".($result['batch'] ? ' in the background.' : '.'))
                    ->send();
            })
            ->visible(fn (GoldCategory $record) => ! $record->auto_derive);
    }

    public static function getRelations(): array
    {
        return [HistoryRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoldCategories::route('/'),
            'create' => Pages\CreateGoldCategory::route('/create'),
            'edit' => Pages\EditGoldCategory::route('/{record}/edit'),
        ];
    }
}
