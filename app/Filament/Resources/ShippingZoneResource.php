<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingZoneResource\Pages;
use App\Models\ShippingZone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ShippingZoneResource extends Resource
{
    protected static ?string $model = ShippingZone::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Delivery zones';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')->required()->unique(ignoreRecord: true)->alphaDash(),
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('name_ar')->extraInputAttributes(['dir' => 'rtl']),
            Forms\Components\TextInput::make('flat_rate')->label('Flat rate (excl. VAT)')->numeric()->prefix('AED')->required()->default(0),
            Forms\Components\TextInput::make('free_above')->label('Free delivery above (goods excl. VAT)')->numeric()->prefix('AED')->helperText('Leave empty for never free.'),
            Forms\Components\TextInput::make('eta_days')->label('Delivery days')->numeric()->default(2),
            Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->reorderable('sort_order')->defaultSort('sort_order')->columns([
            Tables\Columns\TextColumn::make('name'),
            Tables\Columns\TextColumn::make('flat_rate')->money('AED'),
            Tables\Columns\TextColumn::make('free_above')->money('AED')->placeholder('—'),
            Tables\Columns\TextColumn::make('eta_days')->suffix(' days'),
            Tables\Columns\ToggleColumn::make('is_active'),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageShippingZones::route('/')];
    }
}
