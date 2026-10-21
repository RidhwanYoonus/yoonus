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
use App\Filament\Concerns\HasStaffArea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    use HasStaffArea;

    protected static string $staffArea = 'products';

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
            Forms\Components\Group::make()->columnSpan(['lg' => 2])->schema([
                Forms\Components\Section::make('Details')->columns(2)->schema([
                    Forms\Components\TextInput::make('name')->label('Name (English)')->required()->maxLength(255)
                        ->live(onBlur: true)->afterStateUpdated(fn (Forms\Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                    Forms\Components\TextInput::make('name_ar')->label('Name (Arabic)')->extraInputAttributes(['dir' => 'rtl'])->hintAction(\App\Filament\Support\ArabicField::translate('name', 'name_ar'))->maxLength(255),
                    Forms\Components\TextInput::make('sku')->required()->maxLength(64)->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true)->helperText('URL: /product/{slug}'),
                    Forms\Components\Select::make('category_id')->relationship('category', 'name')->searchable()->preload()
                        ->createOptionForm([Forms\Components\TextInput::make('name')->required(), Forms\Components\TextInput::make('name_ar')->label('Name (Arabic)')->extraInputAttributes(['dir' => 'rtl'])->hintAction(\App\Filament\Support\ArabicField::translate('name', 'name_ar')), Forms\Components\TextInput::make('slug')->required()]),
                    Forms\Components\TextInput::make('hallmark_ref')->label('UAE hallmark reference')->maxLength(64),
                    Forms\Components\Textarea::make('description')->label('Description (English)')->rows(4),
                    Forms\Components\Textarea::make('description_ar')->label('Description (Arabic)')->rows(4)->extraInputAttributes(['dir' => 'rtl'])->hintAction(\App\Filament\Support\ArabicField::translate('description', 'description_ar')),
                ]),
                Forms\Components\Section::make('Pricing')->columns(3)->schema([
                    Forms\Components\ToggleButtons::make('pricing_type')->label('How is this product priced?')->inline()->required()->default('GOLD_RATE')->live()->columnSpanFull()
                        ->options(['GOLD_RATE' => 'By weight × today\'s gold rate', 'FIXED' => 'Fixed price per piece'])
                        ->icons(['GOLD_RATE' => 'heroicon-o-scale', 'FIXED' => 'heroicon-o-tag'])
                        ->helperText(fn (Forms\Get $get) => $get('pricing_type') === 'FIXED'
                            ? 'Fixed price per piece: one set price for each piece. The gold rate and weight do not change it, and no making charge is added.'
                            : 'Price = weight × today\'s gold rate for the karat + making charge (+ stone, wastage). It changes when the gold rate changes.'),
                    Forms\Components\TextInput::make('fixed_price')->label('Price per piece (excl. VAT)')->numeric()->minValue(0.01)->prefix('AED')->live(debounce: 400)
                        ->visible(fn (Forms\Get $get) => $get('pricing_type') === 'FIXED')->required(fn (Forms\Get $get) => $get('pricing_type') === 'FIXED')
                        ->helperText('Shown in the shop as the price before VAT; 5% VAT is added at checkout. Gold-rate changes do not affect it.'),
                    Forms\Components\Select::make('gold_category_id')->label(fn (Forms\Get $get) => $get('pricing_type') === 'FIXED' ? 'Karat (optional, for display)' : 'Karat')->relationship('goldCategory', 'name')
                        ->required(fn (Forms\Get $get) => $get('pricing_type') !== 'FIXED')->live()
                        ->getOptionLabelFromRecordUsing(fn (GoldCategory $record) => "{$record->name} — AED {$record->rate_per_gram}/g"),
                    Forms\Components\TextInput::make('weight_grams')->label(fn (Forms\Get $get) => $get('pricing_type') === 'FIXED' ? 'Weight (g, optional)' : 'Weight (g)')
                        ->required(fn (Forms\Get $get) => $get('pricing_type') !== 'FIXED')->numeric()->minValue(0)->step(0.001)->default(0)->live(debounce: 400),
                    Forms\Components\TextInput::make('wastage_percent')->visible(fn (Forms\Get $get) => $get('pricing_type') !== 'FIXED')->label('Wastage %')->numeric()->default(0)->minValue(0)->maxValue(100)->live(debounce: 400),
                    Forms\Components\ToggleButtons::make('manufacturer_charge_type')->label('Making charge type')->inline()->required()->default('PERCENTAGE')->live()->columnSpan(['lg' => 2])
                        ->visible(fn (Forms\Get $get) => $get('pricing_type') !== 'FIXED')
                        ->options(['PERCENTAGE' => '% of gold value', 'PER_PIECE' => 'Fixed making AED per piece'])
                        ->icons(['PERCENTAGE' => 'heroicon-o-receipt-percent', 'PER_PIECE' => 'heroicon-o-banknotes'])
                        ->helperText(fn (Forms\Get $get) => $get('manufacturer_charge_type') === 'PER_PIECE'
                            ? 'Fixed making per piece: the same AED making charge for each piece, whatever its weight. The gold itself is still priced by weight × gold rate.'
                            : 'Making charge is a % of the gold value, so it goes up and down with the gold rate.'),
                    Forms\Components\TextInput::make('manufacturer_charge_value')->label(fn (Forms\Get $get) => $get('manufacturer_charge_type') === 'PER_PIECE' ? 'Making per piece' : 'Making charge')->required()->numeric()->default(0)->minValue(0)->live(debounce: 400)
                        ->visible(fn (Forms\Get $get) => $get('pricing_type') !== 'FIXED')
                        ->prefix(fn (Forms\Get $get) => $get('manufacturer_charge_type') === 'PER_PIECE' ? 'AED' : null)
                        ->suffix(fn (Forms\Get $get) => $get('manufacturer_charge_type') === 'PER_PIECE' ? null : '%'),
                    Forms\Components\TextInput::make('stone_value')->label('Stone value')->numeric()->prefix('AED')->default(0)->minValue(0)->live(debounce: 400)
                        ->visible(fn (Forms\Get $get) => $get('pricing_type') !== 'FIXED'),
                    Forms\Components\TextInput::make('stone_type')->placeholder('Diamond, Emerald…')->maxLength(64),
                ]),
                Forms\Components\Section::make('Diamond certificate')->columns(3)->collapsible()
                    ->collapsed(fn (?Product $record) => ! $record?->hasCertificate())
                    ->description('Optional. Shown on the product page with the lab\'s small logo. Labs are listed in Catalogue → Certificate labs.')
                    ->schema([
                        Forms\Components\Select::make('certificate_lab_id')->label('Lab')->relationship('certificateLab', 'name', fn ($query) => $query->where('is_active', true)->orderBy('sort_order'))
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\CertificateLab $record) => $record->short_name ? "{$record->short_name} — {$record->name}" : $record->name)
                            ->searchable()->preload()
                            ->createOptionForm(\App\Filament\Resources\CertificateLabResource::fields())->createOptionModalHeading('Add certificate lab')
                            ->editOptionForm(\App\Filament\Resources\CertificateLabResource::fields())->editOptionModalHeading('Edit certificate lab')
                            ->columnSpan(['lg' => 2])->helperText('+ adds a new lab · the pencil edits the chosen lab (name, logo, link).'),
                        Forms\Components\TextInput::make('certificate_number')->label('Certificate no.')->maxLength(64),
                        Forms\Components\FileUpload::make('certificate_file')->label('Certificate copy (PDF / image)')->disk('public')->directory('certificates')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])->maxSize(8192)->openable()->downloadable(),
                        Forms\Components\ToggleButtons::make('diamond_origin')->label('Diamond type')->inline()->columnSpanFull()
                            ->options(\App\Models\Product::DIAMOND_ORIGINS)->icons(['natural' => 'heroicon-o-sparkles', 'lab_grown' => 'heroicon-o-beaker'])
                            ->colors(['natural' => 'success', 'lab_grown' => 'info'])
                            ->helperText('Shown on the product page and on the product card. Leave empty for pieces without diamonds.'),
                        Forms\Components\TextInput::make('diamond_carat')->label('Total carat')->numeric()->minValue(0)->step(0.001)->suffix('ct'),
                        Forms\Components\Select::make('diamond_shape')->label('Shape')->options(array_combine($shapes = ['Round', 'Princess', 'Oval', 'Emerald', 'Cushion', 'Pear', 'Marquise', 'Radiant', 'Asscher', 'Heart', 'Baguette', 'Mixed'], $shapes)),
                        Forms\Components\Select::make('diamond_color')->label('Colour')->options(array_combine($c = ['D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'D-F', 'G-H', 'I-J', 'Fancy'], $c)),
                        Forms\Components\Select::make('diamond_clarity')->label('Clarity')->options(array_combine($c = ['FL', 'IF', 'VVS1', 'VVS2', 'VS1', 'VS2', 'SI1', 'SI2', 'I1', 'I2', 'I3', 'VVS', 'VS', 'SI'], $c)),
                        Forms\Components\Select::make('diamond_cut')->label('Cut')->options(array_combine($c = ['Excellent', 'Ideal', 'Very Good', 'Good', 'Fair'], $c)),
                    ]),
                Forms\Components\Section::make('Images')->schema([
                    Forms\Components\FileUpload::make('images')->multiple()->reorderable()->image()->imageEditor()
                        ->disk('public')->directory('products')->maxSize(8192)->maxFiles(8)
                        // Phone photos are large: shrink them in the browser before uploading.
                        ->imageResizeMode('contain')->imageResizeTargetWidth('2000')->imageResizeTargetHeight('2000')->imageResizeUpscale(false)
                        ->helperText('Converted to WebP automatically. First image is the main photo. On a phone, "Browse" also offers the camera and gallery.'),
                    Forms\Components\FileUpload::make('camera_photos')->label('Take photos with the camera')->multiple()->image()
                        ->disk('public')->directory('products')->maxSize(8192)->maxFiles(8)
                        ->imageResizeMode('contain')->imageResizeTargetWidth('2000')->imageResizeTargetHeight('2000')->imageResizeUpscale(false)
                        ->extraInputAttributes(['capture' => 'environment'])   // opens the back camera straight away on phones
                        ->placeholder('📷 Tap to open the camera')
                        ->helperText('On a phone this opens the back camera. Photos taken here are added after the images above when you save.'),
                ]),
                \App\Filament\Support\SeoFields::section(Product::class)->collapsed(fn (?Product $record) => $record && filled($record->meta_title) && filled($record->meta_description)),
            ]),
            Forms\Components\Group::make()->columnSpan(1)->schema([
                Forms\Components\Section::make('Live price')->schema([
                    Forms\Components\Placeholder::make('price_preview')->hiddenLabel()->content(fn (Forms\Get $get) => self::preview($get)),
                    Forms\Components\Placeholder::make('price_calculated_at')->label('Last recalculated')
                        ->content(fn (?Product $record) => $record?->price_calculated_at?->diffForHumans() ?? '—'),
                ]),
                Forms\Components\Section::make('Stock & visibility')->schema([
                    Forms\Components\TextInput::make('stock_qty')->label('Stock')->numeric()->required()->default(1)->minValue(0)
                        ->helperText(fn (?Product $record) => $record?->hidden_after_sale
                            ? 'Sold and handed over — hidden from the shop. Enter new stock to show it again.'
                            : 'At 0 the shop shows "Sold" and stops orders; after that order is delivered the piece is hidden.'),
                    Forms\Components\TextInput::make('low_stock_threshold')->numeric()->default(2)->minValue(0),
                    Forms\Components\Toggle::make('is_active')->label('Visible in shop')->default(false)
                        ->helperText('New products start hidden: check photos and price, then switch on (here or in the products list).'),
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
            ->modifyQueryUsing(fn (Builder $query) => Product::tracksAuthors() ? $query->with(['creator:id,name', 'editor:id,name']) : $query)
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(fn (Product $record) => self::getUrl('edit', ['record' => $record]))
            ->actionsPosition(Tables\Enums\ActionsPosition::BeforeColumns)
            ->columns([
                Tables\Columns\ImageColumn::make('images')->label('')->disk('public')->limit(1)->square()
                    ->defaultImageUrl(fn (Product $record) => route('placeholder', $record->slug)),
                Tables\Columns\TextColumn::make('name')->searchable(['name', 'name_ar', 'sku'])->description(fn (Product $record) => $record->sku)->limit(40),
                Tables\Columns\ToggleColumn::make('is_active')->label('In shop')->onColor('success')->offColor('gray')
                    ->tooltip(fn (Product $record) => $record->is_active ? 'Shown in the shop — switch off to hide' : 'Hidden — switch on to show in the shop')
                    ->disabled(fn () => ! \App\Support\StaffAccess::current('products', 'edit')),
                Tables\Columns\TextColumn::make('goldCategory.name')->visibleFrom('md')->label('Karat')->badge()->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('pricing_type')->visibleFrom('md')->label('Pricing')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'FIXED' ? 'Fixed per piece' : 'Gold rate')->color(fn (string $state) => $state === 'FIXED' ? 'info' : 'warning'),
                Tables\Columns\TextColumn::make('weight_grams')->visibleFrom('md')->label('Weight')->suffix(' g')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('making')->label('Making')->toggleable(isToggledHiddenByDefault: true)
                    ->state(fn (Product $record) => $record->isFixedPrice() ? '—' : ($record->manufacturer_charge_type === 'PER_PIECE' ? 'AED '.$record->manufacturer_charge_value.' / piece' : $record->manufacturer_charge_value.'%')),
                Tables\Columns\TextColumn::make('cached_subtotal')->label('Price (excl. VAT)')->money('AED')->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('created_at')->label('Created')->dateTime('d M Y')->sortable()->toggleable(isToggledHiddenByDefault: true)
                    ->description(fn (Product $record) => $record->creator?->name),
                Tables\Columns\TextColumn::make('updated_at')->label('Edited')->since()->sortable()->toggleable(isToggledHiddenByDefault: true)
                    ->description(fn (Product $record) => $record->editor?->name),
                Tables\Columns\TextColumn::make('creator.name')->label('Created by')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('editor.name')->label('Last edited by')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('price_calculated_at')->label('Recalculated')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('stock_qty')->label('Stock')->sortable()
                    ->description(fn (Product $record) => $record->hidden_after_sale ? 'Sold · hidden' : ($record->stock_qty <= 0 ? 'Sold · showing' : null))
                    ->color(fn (Product $record) => $record->isLowStock() ? 'danger' : null)->icon(fn (Product $record) => $record->isLowStock() ? 'heroicon-o-exclamation-triangle' : null),
                Tables\Columns\TextColumn::make('diamond_origin')->label('Diamond type')->badge()->placeholder('—')->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn (?string $state) => \App\Models\Product::DIAMOND_ORIGINS[$state] ?? $state)->color(fn (?string $state) => $state === 'natural' ? 'success' : 'info'),
                Tables\Columns\ToggleColumn::make('is_featured')->label('Featured')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('In shop')->trueLabel('Shown in shop')->falseLabel('Hidden'),
                Tables\Filters\SelectFilter::make('stock')->label('Stock')
                    ->options(['in' => 'In stock', 'low' => 'Low stock', 'sold' => 'Sold out (0)'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'in' => $query->where('stock_qty', '>', 0),
                        'low' => $query->where('stock_qty', '>', 0)->whereColumn('stock_qty', '<=', 'low_stock_threshold'),
                        'sold' => $query->where('stock_qty', '<=', 0),
                        default => $query,
                    }),
                Tables\Filters\SelectFilter::make('category_id')->label('Category')->relationship('category', 'name')->multiple()->preload(),
                Tables\Filters\SelectFilter::make('gold_category_id')->label('Karat')->relationship('goldCategory', 'name')->multiple()->preload(),
                Tables\Filters\SelectFilter::make('pricing_type')->label('Pricing')->options(['GOLD_RATE' => 'Gold rate', 'FIXED' => 'Fixed price per piece']),
                Tables\Filters\SelectFilter::make('diamond_origin')->label('Diamond type')->options(\App\Models\Product::DIAMOND_ORIGINS),
                Tables\Filters\TernaryFilter::make('is_featured')->label('Featured on homepage'),
                Tables\Filters\SelectFilter::make('created_by')->label('Created by')->multiple()->options(fn () => self::staffOptions())
                    ->visible(fn () => Product::tracksAuthors()),
                Tables\Filters\SelectFilter::make('updated_by')->label('Last edited by')->multiple()->options(fn () => self::staffOptions())
                    ->visible(fn () => Product::tracksAuthors()),
                Tables\Filters\Filter::make('created')->label('Created')
                    ->form([Forms\Components\DatePicker::make('created_from')->label('Created from'), Forms\Components\DatePicker::make('created_until')->label('Created until')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['created_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
                        ->when($data['created_until'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v)))
                    ->indicateUsing(fn (array $data) => array_values(array_filter([
                        ($data['created_from'] ?? null) ? 'Created from '.\Illuminate\Support\Carbon::parse($data['created_from'])->format('d M Y') : null,
                        ($data['created_until'] ?? null) ? 'Created until '.\Illuminate\Support\Carbon::parse($data['created_until'])->format('d M Y') : null,
                    ]))),
                Tables\Filters\Filter::make('edited')->label('Edited')
                    ->form([Forms\Components\DatePicker::make('edited_from')->label('Edited from'), Forms\Components\DatePicker::make('edited_until')->label('Edited until')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['edited_from'] ?? null, fn ($q, $v) => $q->whereDate('updated_at', '>=', $v))
                        ->when($data['edited_until'] ?? null, fn ($q, $v) => $q->whereDate('updated_at', '<=', $v)))
                    ->indicateUsing(fn (array $data) => array_values(array_filter([
                        ($data['edited_from'] ?? null) ? 'Edited from '.\Illuminate\Support\Carbon::parse($data['edited_from'])->format('d M Y') : null,
                        ($data['edited_until'] ?? null) ? 'Edited until '.\Illuminate\Support\Carbon::parse($data['edited_until'])->format('d M Y') : null,
                    ]))),
                Tables\Filters\Filter::make('price')->label('Price (excl. VAT)')
                    ->form([Forms\Components\TextInput::make('price_min')->label('Price from')->numeric()->prefix('AED'), Forms\Components\TextInput::make('price_max')->label('Price up to')->numeric()->prefix('AED')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when(filled($data['price_min'] ?? null), fn ($q) => $q->where('cached_subtotal', '>=', $data['price_min']))
                        ->when(filled($data['price_max'] ?? null), fn ($q) => $q->where('cached_subtotal', '<=', $data['price_max'])))
                    ->indicateUsing(fn (array $data) => array_values(array_filter([
                        filled($data['price_min'] ?? null) ? 'Price ≥ AED '.$data['price_min'] : null, filled($data['price_max'] ?? null) ? 'Price ≤ AED '.$data['price_max'] : null,
                    ]))),
                Tables\Filters\Filter::make('weight')->label('Weight')
                    ->form([Forms\Components\TextInput::make('weight_min')->label('Weight from')->numeric()->suffix('g'), Forms\Components\TextInput::make('weight_max')->label('Weight up to')->numeric()->suffix('g')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when(filled($data['weight_min'] ?? null), fn ($q) => $q->where('weight_grams', '>=', $data['weight_min']))
                        ->when(filled($data['weight_max'] ?? null), fn ($q) => $q->where('weight_grams', '<=', $data['weight_max'])))
                    ->indicateUsing(fn (array $data) => array_values(array_filter([
                        filled($data['weight_min'] ?? null) ? 'Weight ≥ '.$data['weight_min'].' g' : null, filled($data['weight_max'] ?? null) ? 'Weight ≤ '.$data['weight_max'].' g' : null,
                    ]))),
                Tables\Filters\Filter::make('no_photo')->label('Without photos')->query(fn (Builder $query) => $query->where(fn ($q) => $q->whereNull('images')->orWhere('images', '[]')->orWhere('images', ''))),
                Tables\Filters\Filter::make('no_seo')->label('SEO missing')->query(fn (Builder $query) => $query->where(fn ($q) => $q->whereNull('meta_title')->orWhere('meta_title', '')->orWhereNull('meta_description')->orWhere('meta_description', ''))),
                Tables\Filters\Filter::make('no_arabic')->label('Arabic name missing')->query(fn (Builder $query) => $query->where(fn ($q) => $q->whereNull('name_ar')->orWhere('name_ar', ''))),
            ], layout: Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(['sm' => 2, 'lg' => 4])
            ->actions([
                Tables\Actions\EditAction::make()->button()->size('sm'),
                Tables\Actions\Action::make('view')->tooltip('View in shop')->icon('heroicon-o-eye')->url(fn (Product $record) => route('product.show', $record))->openUrlInNewTab()->iconButton(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('show')->label('Show in shop')->icon('heroicon-o-eye')->color('success')
                        ->visible(fn () => \App\Support\StaffAccess::current('products', 'edit'))->deselectRecordsAfterCompletion()
                        ->action(fn ($records) => $records->each(fn (Product $p) => $p->update(['is_active' => true]))),
                    Tables\Actions\BulkAction::make('hide')->label('Hide from shop')->icon('heroicon-o-eye-slash')->color('gray')
                        ->visible(fn () => \App\Support\StaffAccess::current('products', 'edit'))->deselectRecordsAfterCompletion()
                        ->action(fn ($records) => $records->each(fn (Product $p) => $p->update(['is_active' => false]))),
                    Tables\Actions\BulkAction::make('recalculate')->visible(fn () => \App\Support\StaffAccess::current('products', 'edit'))->icon('heroicon-o-arrow-path')
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

    /** Admin and staff logins, for the "Created by" / "Last edited by" filters. */
    private static function staffOptions(): array
    {
        return \App\Models\User::where(fn ($q) => $q->where('is_admin', true)->orWhere('is_staff', true))->orderBy('name')->pluck('name', 'id')->all();
    }
}
