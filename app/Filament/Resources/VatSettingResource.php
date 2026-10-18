<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VatSettingResource\Pages;
use App\Models\VatSetting;
use App\Pricing\PricingService;
use App\Support\StorefrontCache;
use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Concerns\HasStaffArea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VatSettingResource extends Resource
{
    use HasStaffArea;

    protected static string $staffArea = 'vat';

    protected static ?string $model = VatSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'VAT';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('vat_percent')->numeric()->suffix('%')->required()->default(5)->minValue(0)->maxValue(100),
            Forms\Components\Select::make('gold_category_id')->relationship('goldCategory', 'name')->label('Karat override')->placeholder('Default (all karats)')->unique(ignoreRecord: true),
            Forms\Components\Toggle::make('is_default'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('vat_percent')->suffix('%'),
            Tables\Columns\TextColumn::make('goldCategory.name')->label('Applies to')->placeholder('All karats'),
            Tables\Columns\IconColumn::make('is_default')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()->after(fn () => self::flush())]);
    }

    public static function flush(): void
    {
        app(PricingService::class)->bumpVersion();
        StorefrontCache::flushPriceDependent();
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageVatSettings::route('/')];
    }
}
