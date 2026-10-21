<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Concerns\HasStaffArea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CategoryResource extends Resource
{
    use HasStaffArea;

    protected static string $staffArea = 'categories';

    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->live(onBlur: true)
                ->afterStateUpdated(fn (Forms\Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
            Forms\Components\TextInput::make('name_ar')->label('Name (Arabic)')->extraInputAttributes(['dir' => 'rtl'])->hintAction(\App\Filament\Support\ArabicField::translate('name', 'name_ar')),
            Forms\Components\TextInput::make('slug')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
            Forms\Components\Textarea::make('description'),
            Forms\Components\Textarea::make('description_ar')->extraInputAttributes(['dir' => 'rtl'])->hintAction(\App\Filament\Support\ArabicField::translate('description', 'description_ar')),
            Forms\Components\Toggle::make('is_active')->default(true),
            \App\Filament\Support\SeoFields::section(\App\Models\Category::class)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->reorderable('sort_order')->defaultSort('sort_order')->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('name_ar')->label('Arabic'),
            Tables\Columns\TextColumn::make('products_count')->counts('products')->label('Products'),
            Tables\Columns\ToggleColumn::make('is_active'),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCategories::route('/')];
    }
}
