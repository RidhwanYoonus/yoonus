<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchResource\Pages;
use App\Models\Branch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Branches & pickup';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('name_ar')->extraInputAttributes(['dir' => 'rtl']),
            Forms\Components\Select::make('emirate')->required()->options(array_combine($e = ['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Umm Al Quwain', 'Ras Al Khaimah', 'Fujairah'], $e)),
            Forms\Components\TextInput::make('phone'),
            Forms\Components\TextInput::make('address')->required()->columnSpanFull(),
            Forms\Components\TextInput::make('opening_hours'),
            Forms\Components\Toggle::make('pickup_enabled')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name'),
            Tables\Columns\TextColumn::make('emirate'),
            Tables\Columns\TextColumn::make('stock_sum_qty')->sum('stock', 'qty')->label('Pieces in stock'),
            Tables\Columns\ToggleColumn::make('pickup_enabled'),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageBranches::route('/')];
    }
}
