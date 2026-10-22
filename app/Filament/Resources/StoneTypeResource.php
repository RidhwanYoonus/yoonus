<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasStaffArea;
use App\Filament\Resources\StoneTypeResource\Pages;
use App\Models\Product;
use App\Models\StoneType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Master list of stones (Diamond, Emerald, Pearl…) picked on products — several per piece. */
class StoneTypeResource extends Resource
{
    use HasStaffArea;

    protected static string $staffArea = 'certificate_labs';

    protected static ?string $model = StoneType::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Catalogue';

    protected static ?string $navigationLabel = 'Stone types';

    protected static ?string $modelLabel = 'stone type';

    protected static ?int $navigationSort = 31;

    public static function form(Form $form): Form
    {
        return $form->schema(self::fields());
    }

    /** Also used to add a stone straight from the product form. */
    public static function fields(): array
    {
        return [
            Forms\Components\TextInput::make('name')->label('Name (English)')->required()->maxLength(80)->unique(StoneType::class, 'name', ignoreRecord: true)
                ->placeholder('Emerald')->disabledOn('edit')->helperText(fn (string $operation) => $operation === 'edit' ? 'Saved on products, so it cannot be renamed — add a new one and switch this off instead.' : null),
            Forms\Components\TextInput::make('name_ar')->label('Name (Arabic)')->maxLength(80)->placeholder('زمرد')->extraInputAttributes(['dir' => 'rtl']),
            Forms\Components\TextInput::make('sort_order')->label('Order')->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')->label('Active')->default(true)->inline(false),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('name_ar')->label('Arabic')->placeholder('—')->searchable(),
                Tables\Columns\TextColumn::make('products')->label('Products')->state(fn (StoneType $record) => Product::withStone($record->name)->count()),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()
                ->disabled(fn (StoneType $record) => Product::withStone($record->name)->exists())
                ->tooltip(fn (StoneType $record) => Product::withStone($record->name)->exists() ? 'Used on products — switch it off instead.' : null)]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageStoneTypes::route('/')];
    }
}
