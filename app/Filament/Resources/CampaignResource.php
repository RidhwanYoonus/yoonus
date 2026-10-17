<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignResource\Pages;
use App\Models\Campaign;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Offers';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->placeholder('Ramadan, Eid, DSF, National Day…')->live(onBlur: true)
                ->afterStateUpdated(fn (Forms\Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
            Forms\Components\TextInput::make('name_ar')->extraInputAttributes(['dir' => 'rtl']),
            Forms\Components\TextInput::make('slug')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('link_url')->placeholder('/shop?karat[]=2'),
            Forms\Components\TextInput::make('headline'),
            Forms\Components\TextInput::make('headline_ar')->extraInputAttributes(['dir' => 'rtl']),
            Forms\Components\DateTimePicker::make('starts_at')->required()->default(now()),
            Forms\Components\DateTimePicker::make('ends_at')->required()->after('starts_at'),
            Forms\Components\FileUpload::make('banner_image')->image()->disk('public')->directory('campaigns')->columnSpanFull(),
            Forms\Components\Toggle::make('show_countdown')->default(true),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('starts_at', 'desc')->columns([
            Tables\Columns\TextColumn::make('name'),
            Tables\Columns\TextColumn::make('starts_at')->dateTime('d M Y H:i'),
            Tables\Columns\TextColumn::make('ends_at')->dateTime('d M Y H:i'),
            Tables\Columns\TextColumn::make('state')->badge()->state(fn (Campaign $record) => ! $record->is_active ? 'off' : ($record->starts_at->isFuture() ? 'scheduled' : ($record->ends_at->isPast() ? 'ended' : 'live')))
                ->color(fn ($state) => ['live' => 'success', 'scheduled' => 'info', 'ended' => 'gray', 'off' => 'gray'][$state]),
            Tables\Columns\TextColumn::make('coupons_count')->counts('coupons')->label('Coupons'),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCampaigns::route('/')];
    }
}
