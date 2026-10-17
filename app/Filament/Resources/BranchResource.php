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
            Forms\Components\Section::make('Google Maps location')->columns(2)->schema([
                Forms\Components\TextInput::make('map_url')->label('Google Maps link')->url()->columnSpanFull()->live(onBlur: true)
                    ->helperText('In Google Maps: find your store → Share → Copy link. Used for the "Get directions" button. Coordinates in a full maps link are read automatically.')
                    ->afterStateUpdated(function (?string $state, Forms\Set $set, Forms\Get $get) {
                        if ($state && ! $get('latitude') && preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)|[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)|!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $state, $m)) {
                            $m = array_values(array_filter(array_slice($m, 1), fn ($v) => $v !== ''));
                            $set('latitude', $m[0]);
                            $set('longitude', $m[1]);
                        }
                    }),
                Forms\Components\TextInput::make('latitude')->numeric()->helperText('For an exact pin. In Google Maps, right-click your store and click the numbers to copy them, e.g. 25.2697'),
                Forms\Components\TextInput::make('longitude')->numeric()->helperText('e.g. 55.2972. Leave both empty to place the pin by address.'),
                Forms\Components\Placeholder::make('map_preview')->label('Preview')->columnSpanFull()
                    ->content(fn (?Branch $record) => $record ? new \Illuminate\Support\HtmlString('<iframe src="'.e($record->mapEmbedUrl()).'" style="width:100%;height:260px;border:0;border-radius:12px" loading="lazy"></iframe>') : 'Save the branch to see the map.'),
            ]),
            Forms\Components\Toggle::make('pickup_enabled')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name'),
            Tables\Columns\TextColumn::make('emirate'),
            Tables\Columns\IconColumn::make('has_map')->label('Map pin')->boolean()->getStateUsing(fn (Branch $record) => (bool) ($record->map_url || $record->latitude)),
            Tables\Columns\TextColumn::make('stock_sum_qty')->sum('stock', 'qty')->label('Pieces in stock'),
            Tables\Columns\ToggleColumn::make('pickup_enabled'),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageBranches::route('/')];
    }
}
