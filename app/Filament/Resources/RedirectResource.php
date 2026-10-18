<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RedirectResource\Pages;
use App\Models\Redirect;
use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Concerns\HasStaffArea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RedirectResource extends Resource
{
    use HasStaffArea;

    protected static string $staffArea = 'redirects';

    protected static ?string $model = Redirect::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-right';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = '301 redirects';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('from_path')->required()->placeholder('/old-product-url')->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('to_path')->required()->placeholder('/product/new-slug'),
            Forms\Components\Select::make('status_code')->options([301 => '301 Permanent', 302 => '302 Temporary'])->default(301)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('from_path')->searchable(),
            Tables\Columns\TextColumn::make('to_path'),
            Tables\Columns\TextColumn::make('status_code')->badge(),
            Tables\Columns\TextColumn::make('hits')->sortable(),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageRedirects::route('/')];
    }
}
