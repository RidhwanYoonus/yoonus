<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\GoldCategory;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Offers';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Offer')->columns(3)->schema([
                Forms\Components\TextInput::make('code')->required()->unique(ignoreRecord: true)->maxLength(32)->extraInputAttributes(['style' => 'text-transform:uppercase']),
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\Select::make('campaign_id')->relationship('campaign', 'name')->placeholder('None'),
                Forms\Components\Select::make('type')->required()->live()->default('percentage')->options([
                    'percentage' => 'Percentage off', 'fixed' => 'Fixed AED off', 'free_shipping' => 'Free delivery',
                    'bogo' => 'Buy X get Y', 'tiered' => 'Tiered (spend more, save more)',
                ]),
                Forms\Components\TextInput::make('value')->numeric()->minValue(0)->required()
                    ->visible(fn (Forms\Get $get) => in_array($get('type'), ['percentage', 'fixed']))
                    ->suffix(fn (Forms\Get $get) => $get('type') === 'percentage' ? '%' : 'AED'),
                Forms\Components\Placeholder::make('vat_note')->label('')->content('Discounts reduce the pre-VAT subtotal; VAT is charged on the discounted amount. A line can never drop below its gold value.'),
                Forms\Components\Repeater::make('rules')->label('Tiers')->visible(fn (Forms\Get $get) => $get('type') === 'tiered')->columnSpanFull()->columns(2)
                    ->schema([Forms\Components\TextInput::make('min')->label('Spend at least (AED)')->numeric()->required(), Forms\Components\TextInput::make('percent')->label('% off')->numeric()->required()]),
                Forms\Components\Group::make()->visible(fn (Forms\Get $get) => $get('type') === 'bogo')->columnSpanFull()->columns(3)->schema([
                    Forms\Components\TextInput::make('rules.buy')->label('Buy')->numeric()->default(1),
                    Forms\Components\TextInput::make('rules.get')->label('Get')->numeric()->default(1),
                    Forms\Components\TextInput::make('rules.get_percent')->label('% off the cheaper item(s)')->numeric()->default(100),
                ]),
            ]),
            Forms\Components\Section::make('Scope')->columns(2)->schema([
                Forms\Components\Select::make('scope')->required()->default('cart')->live()->options([
                    'cart' => 'Entire cart', 'category' => 'Specific categories', 'products' => 'Specific products', 'gold_category' => 'Specific karats',
                ]),
                Forms\Components\Select::make('scope_ids')->label('Applies to')->multiple()->searchable()
                    ->visible(fn (Forms\Get $get) => $get('scope') !== 'cart')
                    ->options(fn (Forms\Get $get) => match ($get('scope')) {
                        'category' => Category::pluck('name', 'id'),
                        'products' => Product::pluck('name', 'id'),
                        'gold_category' => GoldCategory::pluck('name', 'id'),
                        default => [],
                    }),
                Forms\Components\Toggle::make('first_order_only'),
            ]),
            Forms\Components\Section::make('Limits & schedule')->columns(3)->schema([
                Forms\Components\TextInput::make('max_uses')->numeric()->placeholder('Unlimited'),
                Forms\Components\TextInput::make('max_uses_per_customer')->numeric()->placeholder('Unlimited'),
                Forms\Components\TextInput::make('min_cart_value')->numeric()->prefix('AED')->placeholder('None'),
                Forms\Components\DateTimePicker::make('starts_at'),
                Forms\Components\DateTimePicker::make('ends_at'),
                Forms\Components\Placeholder::make('times_used')->content(fn (?Coupon $record) => $record?->times_used ?? 0),
                Forms\Components\Toggle::make('stackable')->helperText('Can combine with other stackable offers'),
                Forms\Components\Toggle::make('auto_apply')->helperText('Applied automatically without a code (e.g. "Eid Sale — 15% off all 22K")'),
                Forms\Components\Toggle::make('is_active')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            Tables\Columns\TextColumn::make('code')->weight('bold')->searchable()->copyable(),
            Tables\Columns\TextColumn::make('name')->limit(30),
            Tables\Columns\TextColumn::make('type')->badge(),
            Tables\Columns\TextColumn::make('value')->state(fn (Coupon $record) => match ($record->type) {
                'percentage' => $record->value.'%', 'fixed' => 'AED '.$record->value, default => '—'
            }),
            Tables\Columns\TextColumn::make('scope'),
            Tables\Columns\TextColumn::make('times_used')->label('Used')->suffix(fn (Coupon $record) => $record->max_uses ? ' / '.$record->max_uses : ''),
            Tables\Columns\IconColumn::make('auto_apply')->boolean(),
            Tables\Columns\TextColumn::make('ends_at')->dateTime('d M Y')->placeholder('No end'),
            Tables\Columns\ToggleColumn::make('is_active'),
        ])->filters([
            Tables\Filters\TernaryFilter::make('is_active'),
            Tables\Filters\Filter::make('personal')->label('Hide referral codes')->query(fn ($query) => $query->whereNull('issued_to_user_id'))->default(),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCoupons::route('/')];
    }
}
