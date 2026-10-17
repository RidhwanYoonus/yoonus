<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers\RecalcLogRelationManager;
use App\Models\GoldCategory;
use App\Models\Product;
use App\Pricing\Money;
use App\Pricing\PricingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'name_ar', 'sku'];
    }

    public static function getNavigationBadge(): ?string
    {
        $low = Product::where('is_active', true)->whereColumn('stock_qty', '<=', 'low_stock_threshold')->count();

        return $low ? (string) $low : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Form $form): Form
    {

        return $form->columns(3)->schema([
            Forms\Components\Group::make()->columnSpan(2)->schema([
                Forms\Components\Section::make('Details')->columns(2)->schema([
                    Forms\Components\TextInput::make('name')->label('Name (English)')->required()->maxLength(255)
                        ->live(onBlur: true)->afterStateUpdated(fn (Forms\Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                    Forms\Components\TextInput::make('name_ar')->label('Name (Arabic)')->extraInputAttributes(['dir' => 'rtl'])->maxLength(255),
                    Forms\Components\TextInput::make('sku')->required()->maxLength(64)->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true)->helperText('URL: /product/{slug}'),
                    Forms\Components\Select::make('category_id')->relationship('category', 'name')->searchable()->preload()
                        ->createOptionForm([Forms\Components\TextInput::make('name')->required(), Forms\Components\TextInput::make('name_ar'), Forms\Components\TextInput::make('slug')->required()]),
                    Forms\Components\TextInput::make('hallmark_ref')->label('UAE hallmark reference')->maxLength(64),
                    Forms\Components\Textarea::make('description')->label('Description (English)')->rows(4),
                    Forms\Components\Textarea::make('description_ar')->label('Description (Arabic)')->rows(4)->extraInputAttributes(['dir' => 'rtl']),
                ]),
                Forms\Components\Section::make('Pricing')->columns(3)->schema([
                    Forms\Components\ToggleButtons::make('pricing_type')->label('How is this product priced?')->inline()->required()->default('GOLD_RATE')->live()->columnSpanFull()
                        ->options(['GOLD_RATE' => 'By weight × today\'s gold rate', 'FIXED' => 'Fixed price'])
                        ->icons(['GOLD_RATE' => 'heroicon-o-scale', 'FIXED' => 'heroicon-o-tag']),
                    Forms\Components\TextInput::make('fixed_price')->label('Selling price (excl. VAT)')->numeric()->minValue(0.01)->prefix('AED')->live(debounce: 400)
                        ->visible(fn (Forms\Get $get) => $get('pricing_type') === 'FIXED')->required(fn (Forms\Get $get) => $get('pricing_type') === 'FIXED')
                        ->helperText('Shown in the shop as the price before VAT; 5% VAT is added at checkout. Gold-rate changes do not affect it.'),
                    Forms\Components\Select::make('gold_category_id')->label(fn (Forms\Get $get) => $get('pricing_type') === 'FIXED' ? 'Karat (optional, for display)' : 'Karat')->relationship('goldCategory', 'name')
                        ->required(fn (Forms\Get $get) => $get('pricing_type') !== 'FIXED')->live()
                        ->getOptionLabelFromRecordUsing(fn (GoldCategory $record) => "{$record->name} — AED {$record->rate_per_gram}/g"),
                    Forms\Components\TextInput::make('weight_grams')->label(fn (Forms\Get $get) => $get('pricing_type') === 'FIXED' ? 'Weight (g, optional)' : 'Weight (g)')
                        ->required(fn (Forms\Get $get) => $get('pricing_type') !== 'FIXED')->numeric()->minValue(0)->step(0.001)->default(0)->live(debounce: 400),
                    Forms\Components\TextInput::make('wastage_percent')->visible(fn (Forms\Get $get) => $get('pricing_type') !== 'FIXED')->label('Wastage %')->numeric()->default(0)->minValue(0)->maxValue(100)->live(debounce: 400),
                    Forms\Components\ToggleButtons::make('manufacturer_charge_type')->label('Making charge type')->inline()->required()->default('PERCENTAGE')->live()
                        ->visible(fn (Forms\Get $get) => $get('pricing_type') !== 'FIXED')
                        ->options(['PERCENTAGE' => '% of gold value', 'PER_PIECE' => 'Fixed AED per piece']),
                    Forms\Components\TextInput::make('manufacturer_charge_value')->label('Making charge')->required()->numeric()->default(0)->minValue(0)->live(debounce: 400)
                        ->visible(fn (Forms\Get $get) => $get('pricing_type') !== 'FIXED')
                        ->prefix(fn (Forms\Get $get) => $get('manufacturer_charge_type') === 'PER_PIECE' ? 'AED' : null)
                        ->suffix(fn (Forms\Get $get) => $get('manufacturer_charge_type') === 'PER_PIECE' ? null : '%'),
                    Forms\Components\TextInput::make('stone_value')->label('Stone value')->numeric()->prefix('AED')->default(0)->minValue(0)->live(debounce: 400)
                        ->visible(fn (Forms\Get $get) => $get('pricing_type') !== 'FIXED'),
                    Forms\Components\TextInput::make('stone_type')->placeholder('Diamond, Emerald…')->maxLength(64),
                ]),
                Forms\Components\Section::make('Images')->schema([
                    Forms\Components\FileUpload::make('images')->multiple()->reorderable()->image()->imageEditor()
                        ->disk('public')->directory('products')->maxSize(8192)->maxFiles(8)
                        ->helperText('Converted to WebP automatically. First image is the main photo.'),
                ]),
                Forms\Components\Section::make('SEO')->collapsed()->columns(1)->schema([
                    Forms\Components\TextInput::make('meta_title')->maxLength(255)->helperText('Leave empty to auto-generate from name, karat and weight.'),
                    Forms\Components\Textarea::make('meta_description')->maxLength(320)->rows(2)->helperText('Leave empty to auto-generate (includes live price).'),
                ]),
            ]),
            Forms\Components\Group::make()->columnSpan(1)->schema([
                Forms\Components\Section::make('Live price')->schema([
                    Forms\Components\Placeholder::make('price_preview')->hiddenLabel()->content(fn (Forms\Get $get) => self::preview($get)),
                    Forms\Components\Placeholder::make('price_calculated_at')->label('Last recalculated')
                        ->content(fn (?Product $record) => $record?->price_calculated_at?->diffForHumans() ?? '—'),
                ]),
                Forms\Components\Section::make('Stock & visibility')->schema([
                    Forms\Components\TextInput::make('stock_qty')->label('Stock')->numeric()->required()->default(1)->minValue(0),
                    Forms\Components\TextInput::make('low_stock_threshold')->numeric()->default(2)->minValue(0),
                    Forms\Components\Toggle::make('is_active')->label('Visible in shop')->default(true),
                    Forms\Components\Toggle::make('is_featured')->label('Featured on homepage'),
                    Forms\Components\Toggle::make('video_call_enabled')->label('Live video call button')->default(true)
                        ->helperText(fn () => \App\Support\Settings::bool('video_call_enabled') ? 'Customers can request a live video call about this piece.' : 'Video calls are switched off for the whole shop in Settings → Video calls.'),
                ]),
            ]),
        ]);
    }

    /** Price breakdown card that recalculates as the admin types. */
    public static function preview(Forms\Get $get): HtmlString
    {
        $fixed = $get('pricing_type') === 'FIXED';
        if ($fixed && ! is_numeric($get('fixed_price'))) {
            return new HtmlString('<p class="text-sm text-gray-500">Enter the selling price to see the VAT split.</p>');
        }
        if (! $fixed && (! $get('gold_category_id') || ! is_numeric($get('weight_grams')))) {
            return new HtmlString('<p class="text-sm text-gray-500">Choose a karat and weight to see the price.</p>');
        }
        $p = app(PricingService::class)->preview([
            'pricing_type' => $get('pricing_type'),
            'fixed_price' => $get('fixed_price'),
            'weight_grams' => $get('weight_grams'),
            'manufacturer_charge_type' => $get('manufacturer_charge_type'),
            'manufacturer_charge_value' => $get('manufacturer_charge_value'),
            'stone_value' => $get('stone_value'),
            'wastage_percent' => $get('wastage_percent'),
        ], (int) $get('gold_category_id'));
        $row = fn ($label, $fils, $strong = false) => sprintf('<tr class="%s"><td class="py-1">%s</td><td class="py-1 text-right tabular-nums">%s</td></tr>', $strong ? 'font-bold text-base border-t' : '', e($label), e(Money::format($fils, 'en')));

        if ($fixed) {
            return new HtmlString('<table class="w-full text-sm">'
                .$row('Shop price (excl. VAT)', $p->subtotal, true)
                .$row('+ VAT '.rtrim(rtrim($p->vatPercent(), '0'), '.').'% at checkout', $p->vat)
                .$row('Customer pays (before delivery / fees)', $p->total)
                .'</table><p class="mt-2 text-xs text-gray-500">Fixed price — not linked to the gold rate.</p>');
        }

        return new HtmlString('<table class="w-full text-sm">'
            .$row('Gold value', $p->goldValue)
            .$row('Making', $p->makingFee)
            .$row('Stone', $p->stoneValue)
            .$row('Wastage', $p->wastage)
            .($p->rounding ? $row('Rounding', $p->rounding) : '')
            .$row('Shop price (excl. VAT)', $p->subtotal, true)
            .$row('+ VAT '.rtrim(rtrim($p->vatPercent(), '0'), '.').'% at checkout', $p->vat)
            .$row('Customer pays (before delivery / fees)', $p->total)
            .'</table><p class="mt-2 text-xs text-gray-500">At AED '.Money::toAed($p->rateFils).'/g</p>');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(fn (Product $record) => self::getUrl('edit', ['record' => $record]))
            ->actionsPosition(Tables\Enums\ActionsPosition::BeforeColumns)
            ->columns([
                Tables\Columns\ImageColumn::make('images')->label('')->disk('public')->limit(1)->square()
                    ->defaultImageUrl(fn (Product $record) => route('placeholder', $record->slug)),
                Tables\Columns\TextColumn::make('name')->searchable(['name', 'name_ar', 'sku'])->description(fn (Product $record) => $record->sku)->limit(40),
                Tables\Columns\TextColumn::make('goldCategory.name')->label('Karat')->badge()->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('pricing_type')->label('Pricing')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'FIXED' ? 'Fixed' : 'Gold rate')->color(fn (string $state) => $state === 'FIXED' ? 'info' : 'warning'),
                Tables\Columns\TextColumn::make('weight_grams')->label('Weight')->suffix(' g')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('making')->label('Making')->toggleable(isToggledHiddenByDefault: true)
                    ->state(fn (Product $record) => $record->isFixedPrice() ? '—' : ($record->manufacturer_charge_type === 'PER_PIECE' ? 'AED '.$record->manufacturer_charge_value : $record->manufacturer_charge_value.'%')),
                Tables\Columns\TextColumn::make('cached_subtotal')->label('Price (excl. VAT)')->money('AED')->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('price_calculated_at')->label('Recalculated')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('stock_qty')->label('Stock')->sortable()
                    ->color(fn (Product $record) => $record->isLowStock() ? 'danger' : null)->icon(fn (Product $record) => $record->isLowStock() ? 'heroicon-o-exclamation-triangle' : null),
                Tables\Columns\ToggleColumn::make('is_active')->label('Live'),
                Tables\Columns\ToggleColumn::make('is_featured')->label('Featured')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('gold_category_id')->label('Karat')->relationship('goldCategory', 'name'),
                Tables\Filters\SelectFilter::make('category_id')->label('Category')->relationship('category', 'name'),
                Tables\Filters\Filter::make('low_stock')->label('Low stock')->query(fn (Builder $query) => $query->whereColumn('stock_qty', '<=', 'low_stock_threshold')),
                Tables\Filters\SelectFilter::make('pricing_type')->label('Pricing')->options(['GOLD_RATE' => 'Gold rate', 'FIXED' => 'Fixed price']),
                Tables\Filters\TernaryFilter::make('is_active')->label('Live'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->button()->size('sm'),
                Tables\Actions\Action::make('view')->tooltip('View in shop')->icon('heroicon-o-eye')->url(fn (Product $record) => route('product.show', $record))->openUrlInNewTab()->iconButton(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('recalculate')->icon('heroicon-o-arrow-path')
                        ->action(fn ($records) => $records->each(fn ($p) => app(PricingService::class)->refreshCachedPrice($p, null, 'manual'))),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [RecalcLogRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
