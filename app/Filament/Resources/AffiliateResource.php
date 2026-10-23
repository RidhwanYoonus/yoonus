<?php

namespace App\Filament\Resources;

use App\Affiliates\AffiliateService;
use App\Filament\Concerns\HasStaffArea;
use App\Filament\Resources\AffiliateResource\Pages;
use App\Filament\Resources\AffiliateResource\RelationManagers;
use App\Models\Affiliate;
use App\Models\Branch;
use App\Pricing\Money;
use App\Support\Settings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

/** Affiliate partners: login, commission rates, white page, balance and cash collections. */
class AffiliateResource extends Resource
{
    use HasStaffArea;

    protected static string $staffArea = 'affiliates';

    protected static ?string $model = Affiliate::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = 'Affiliates';

    protected static ?string $navigationLabel = 'Affiliates';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->columns(3)->schema([
            Forms\Components\Section::make('Login account')->columnSpan(['lg' => 2])->columns(2)
                ->description('The affiliate signs in on the shop (Sign in) with this email and password and sees their dashboard.')
                ->schema([
                    Forms\Components\TextInput::make('user_name')->label('Full name')->required()->maxLength(120),
                    Forms\Components\TextInput::make('user_email')->label('Email')->email()->required()
                        ->rule(fn (?Affiliate $record) => Rule::unique('users', 'email')->ignore($record?->user_id)),
                    Forms\Components\TextInput::make('user_phone')->label('Mobile')->tel()->maxLength(32),
                    Forms\Components\TextInput::make('user_password')->label(fn (?Affiliate $record) => $record ? 'New password (optional)' : 'Password')
                        ->password()->revealable()->minLength(8)->required(fn (?Affiliate $record) => ! $record)->dehydrated(fn ($state) => filled($state)),
                ]),
            Forms\Components\Section::make('Status')->columnSpan(1)->schema([
                Forms\Components\Toggle::make('is_active')->label('Active')->default(true)
                    ->helperText('Off: links stop working and no new commission is earned. Earned money can still be collected.'),
                Forms\Components\TextInput::make('code')->label('Affiliate code')->required(fn (?Affiliate $record) => (bool) $record)->maxLength(32)->alphaNum()
                    ->placeholder('Leave empty to create one')
                    ->dehydrateStateUsing(fn ($state) => strtoupper((string) $state))
                    ->unique(ignoreRecord: true)->helperText('Used in their links, e.g. ?aff=CODE'),
                Forms\Components\TextInput::make('display_name')->label('Name shown to customers')->maxLength(80),
            ]),
            Forms\Components\Section::make('Commission')->columnSpanFull()->columns(2)
                ->description('Commission becomes "ready to collect" when the order is delivered / collected. Cancelled or refunded orders earn nothing.')
                ->schema([
                    Forms\Components\ToggleButtons::make('commission_mode')->label('How is commission paid?')->inline()->required()->live()->default('by_type')->columnSpanFull()
                        ->options(['by_type' => 'Different for each item type', 'per_piece' => 'Same amount per piece for every item']),
                    Forms\Components\TextInput::make('commission_per_piece')->label('Commission per piece (any item)')->numeric()->minValue(0)->prefix('AED')->suffix('per piece')->columnSpanFull()
                        ->default(fn () => Settings::get('affiliate_default_commission_per_piece', 10))
                        ->visible(fn (Forms\Get $get) => $get('commission_mode') === 'per_piece')->required(fn (Forms\Get $get) => $get('commission_mode') === 'per_piece')
                        ->helperText('Example: AED 10 per piece — 1 gold ring + 2 diamond earrings = 3 pieces × AED 10 = AED 30.'),
                    Forms\Components\Group::make()->visible(fn (Forms\Get $get) => $get('commission_mode') !== 'per_piece')->schema([
                        Forms\Components\ToggleButtons::make('making_commission_type')->label('By-weight items: commission as')->inline()->required()->live()->default('percent')
                            ->options(['percent' => '% of making charge', 'per_gram' => 'Fixed AED per gram']),
                        Forms\Components\TextInput::make('making_commission_percent')->hiddenLabel()->required()->numeric()->minValue(0)->maxValue(100)->suffix('%')
                            ->default(fn () => Settings::get('affiliate_default_making_percent', 10))
                            ->visible(fn (Forms\Get $get) => $get('making_commission_type') !== 'per_gram')
                            ->helperText('Items priced by weight × gold rate. Example: making AED 300 × 10% = AED 30.'),
                        Forms\Components\TextInput::make('commission_per_gram')->hiddenLabel()->required()->numeric()->minValue(0)->prefix('AED')->suffix('per gram')
                            ->default(fn () => Settings::get('affiliate_default_commission_per_gram', 2))
                            ->visible(fn (Forms\Get $get) => $get('making_commission_type') === 'per_gram')
                            ->helperText('Example: a 12.5 g chain × AED 2 = AED 25, whatever the making charge.'),
                        Forms\Components\TextInput::make('stone_commission_percent')->label('Stone items: also % of the stone value')->numeric()->minValue(0)->maxValue(100)->suffix('%')->default(0)
                            ->visible(fn (Forms\Get $get) => $get('making_commission_type') === 'per_gram')
                            ->helperText('Added to the per-gram commission on pieces with stones. Example: diamond worth AED 2,400 × 2% = AED 48 extra. 0 = none.'),
                    ]),
                    Forms\Components\Group::make()->visible(fn (Forms\Get $get) => $get('commission_mode') !== 'per_piece')->schema([
                        Forms\Components\ToggleButtons::make('fixed_commission_type')->label('Fixed-price items: commission as')->inline()->required()->live()->default('percent')
                            ->options(['percent' => '% of item total', 'per_piece' => 'Fixed AED per piece']),
                        Forms\Components\TextInput::make('fixed_commission_percent')->hiddenLabel()->required()->numeric()->minValue(0)->maxValue(100)->suffix('%')
                            ->default(fn () => Settings::get('affiliate_default_fixed_percent', 3))
                            ->visible(fn (Forms\Get $get) => $get('fixed_commission_type') !== 'per_piece')
                            ->helperText('% of the item price before VAT, after discounts. Example: AED 2,500 × 3% = AED 75.'),
                        Forms\Components\TextInput::make('fixed_commission_amount')->hiddenLabel()->required()->numeric()->minValue(0)->prefix('AED')->suffix('per piece')
                            ->default(fn () => Settings::get('affiliate_default_commission_per_piece', 10))
                            ->visible(fn (Forms\Get $get) => $get('fixed_commission_type') === 'per_piece')
                            ->helperText('Example: 2 diamond pendants × AED 10 = AED 20.'),
                    ]),
                ]),
            Forms\Components\Section::make('White page (own price page)')->columnSpanFull()->columns(2)
                ->description('Their own link where every price includes their mark-up. They keep the full mark-up on top of the commission. The affiliate can change the mark-up on their dashboard, up to your maximum. Maximum 0 = no limit.')
                ->schema(self::markupFields()),
            Forms\Components\Section::make('Growth tools')->columnSpanFull()->columns(3)->schema([
                Forms\Components\TextInput::make('coupon_discount_percent')->label('Customer discount with their code (% of their commission)')->numeric()->minValue(0)->maxValue(100)->suffix('%')->default(0)
                    ->helperText('0 = off. The customer gets this % of the affiliate\'s commission on their cart as a discount — e.g. commission AED 100 × 10% = AED 10 off — and it is taken from that commission (shown in the commission report and on the order). The order counts for the affiliate.'),
                Forms\Components\Toggle::make('can_lock_price')->label('Can send price-lock links')->inline(false)
                    ->helperText(fn () => 'The affiliate can hold today\'s price of a piece for a customer for '.\App\Affiliates\PriceLocks::hours().' hours (Settings → Affiliates). Watch the gold rate when allowing this.'),
                Forms\Components\Select::make('recruited_by_id')->label('Recruited by (team leader)')->searchable()->visible(fn () => Affiliate::teamEnabled())
                    ->options(fn (?Affiliate $record) => Affiliate::with('user')->whereKeyNot($record?->id)->get()->mapWithKeys(fn ($a) => [$a->id => $a->code.' — '.$a->user?->name]))
                    ->helperText('They earn a share of this affiliate\'s commission (Settings → Affiliates).'),
            ]),
            Forms\Components\Textarea::make('notes')->label('Internal notes')->rows(2)->columnSpanFull(),
        ]);
    }

    /** White-page mark-up, laid out like the commission: per item type (two options each) or one amount per piece. */
    private static function markupFields(): array
    {
        $on = fn (Forms\Get $get) => (bool) $get('white_page_enabled');
        $byType = fn (Forms\Get $get) => $on($get) && $get('markup_mode') !== 'amount';
        // "In use" may not go above the maximum, unless the maximum is 0 (no limit).
        $capped = fn (string $max) => fn (Forms\Get $get) => (float) $get($max) > 0 ? 'max:'.(float) $get($max) : 'min:0';
        $pair = fn (string $value, string $max, string $prefix, string $suffix, $visible, ?string $defaultKey = null) => [
            Forms\Components\TextInput::make($value)->label('In use')->numeric()->minValue(0)->default(0)->prefix($prefix ?: null)->suffix($suffix)
                ->rule($capped($max))->visible($visible),
            Forms\Components\TextInput::make($max)->label('Maximum allowed')->numeric()->minValue(0)->prefix($prefix ?: null)->suffix($suffix)
                ->default(fn () => $defaultKey ? Settings::get($defaultKey, 0) : 0)
                ->helperText('0 = no limit')->visible($visible),
        ];

        return [
            Forms\Components\Toggle::make('white_page_enabled')->label('Allow white page')->live()->columnSpanFull(),
            Forms\Components\Toggle::make('signup_markup')->label('Customers who sign up with this code keep this affiliate\'s prices')->default(true)->columnSpanFull()->visible($on)
                ->helperText('On: a customer who types this affiliate\'s code when signing up always sees the white-page prices (mark-up) while signed in. Off: those customers see normal shop prices.'),
            Forms\Components\ToggleButtons::make('markup_mode')->label('How is the mark-up added?')->inline()->required()->live()->default('percent')->columnSpanFull()
                ->visible($on)->options(['percent' => 'Different for each item type', 'amount' => 'Same amount per piece for every item']),

            Forms\Components\Fieldset::make('By-weight items: mark-up as')->columns(2)->columnSpan(1)->visible($byType)->schema([
                Forms\Components\ToggleButtons::make('making_markup_type')->hiddenLabel()->inline()->live()->default('percent')->columnSpanFull()
                    ->options(['percent' => '% of making charge', 'per_gram' => 'Fixed AED per gram']),
                ...$pair('making_markup_percent', 'max_making_markup_percent', '', '%', fn (Forms\Get $get) => $get('making_markup_type') !== 'per_gram', 'affiliate_default_max_making_markup'),
                ...$pair('making_markup_per_gram', 'max_making_markup_per_gram', 'AED', 'per gram', fn (Forms\Get $get) => $get('making_markup_type') === 'per_gram'),
                Forms\Components\Placeholder::make('making_markup_hint')->hiddenLabel()->columnSpanFull()
                    ->content(fn (Forms\Get $get) => $get('making_markup_type') === 'per_gram'
                        ? 'Example: AED 3 per gram on a 12.5 g chain shows AED 37.50 more.'
                        : 'Example: 20% on a ring with AED 300 making shows AED 60 more. The gold value is never marked up.'),
            ]),
            Forms\Components\Fieldset::make('Fixed-price items: mark-up as')->columns(2)->columnSpan(1)->visible($byType)->schema([
                Forms\Components\ToggleButtons::make('fixed_markup_type')->hiddenLabel()->inline()->live()->default('percent')->columnSpanFull()
                    ->options(['percent' => '% of price', 'per_piece' => 'Fixed AED per piece']),
                ...$pair('fixed_markup_percent', 'max_fixed_markup_percent', '', '%', fn (Forms\Get $get) => $get('fixed_markup_type') !== 'per_piece', 'affiliate_default_max_markup'),
                ...$pair('fixed_markup_amount', 'max_fixed_markup_amount', 'AED', 'per piece', fn (Forms\Get $get) => $get('fixed_markup_type') === 'per_piece'),
                Forms\Components\Placeholder::make('fixed_markup_hint')->hiddenLabel()->columnSpanFull()
                    ->content(fn (Forms\Get $get) => $get('fixed_markup_type') === 'per_piece'
                        ? 'Example: AED 25 per piece on 2 diamond pendants shows AED 25 more each.'
                        : 'Example: 5% on a fixed-price piece of AED 2,000 shows AED 100 more.'),
            ]),
            Forms\Components\Fieldset::make('Stones: mark-up on the stone value')->columns(2)->columnSpanFull()->visible($byType)->schema([
                ...$pair('stone_markup_percent', 'max_stone_markup_percent', '', '% of stone value', fn () => true),
                Forms\Components\Placeholder::make('stone_markup_hint')->hiddenLabel()->columnSpanFull()
                    ->content('Example: 5% on a diamond worth AED 2,400 shows AED 120 more. Shown to the customer inside the stone value. 0 = no stone mark-up.'),
            ]),
            Forms\Components\Fieldset::make('Amount added to each piece (any item)')->columns(2)->columnSpanFull()
                ->visible(fn (Forms\Get $get) => $on($get) && $get('markup_mode') === 'amount')->schema([
                    ...$pair('markup_amount', 'max_markup_amount', 'AED', 'per piece', fn () => true, 'affiliate_default_max_markup_amount'),
                    Forms\Components\Placeholder::make('amount_markup_hint')->hiddenLabel()->columnSpanFull()
                        ->content('Example: AED 50 per piece — 1 gold ring + 2 diamond earrings show AED 50 more each.'),
                ]),
        ];
    }

    /** Customer sent by the affiliate bought at the counter: give the commission now. */
    public static function storeSaleAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('storeSale')->label('In-store sale')->icon('heroicon-o-building-storefront')->color('warning')
            ->visible(fn () => \App\Support\StaffAccess::current('affiliate_commissions', 'edit'))
            ->modalDescription('The customer bought in the shop. Commission is worked out with this affiliate\'s rates and is ready to collect at once.')
            ->form(self::storeSaleForm())
            ->action(fn (Affiliate $record, array $data) => self::runStoreSale($record, $data));
    }

    public static function storeSaleForm(): array
    {
        return [
            Forms\Components\ToggleButtons::make('item_type')->label('Item')->inline()->required()->live()->default('gold')
                ->options(['gold' => 'Priced by weight', 'fixed' => 'Fixed price']),
            Forms\Components\TextInput::make('pieces')->numeric()->minValue(1)->default(1)->required(),
            Forms\Components\TextInput::make('making_charge')->label('Making charge (all pieces, excl. VAT)')->numeric()->prefix('AED')->minValue(0)
                ->visible(fn (Forms\Get $get) => $get('item_type') === 'gold'),
            Forms\Components\TextInput::make('weight_grams')->label('Total weight')->numeric()->suffix('g')->minValue(0)
                ->visible(fn (Forms\Get $get) => $get('item_type') === 'gold'),
            Forms\Components\TextInput::make('price')->label('Price (all pieces, excl. VAT)')->numeric()->prefix('AED')->minValue(0)
                ->visible(fn (Forms\Get $get) => $get('item_type') === 'fixed'),
            Forms\Components\TextInput::make('receipt')->label('Shop receipt / invoice no.')->maxLength(64),
        ];
    }

    public static function runStoreSale(Affiliate $record, array $data): void
    {
        try {
            $adj = app(AffiliateService::class)->storeSale($record, $data, auth()->user());
            Notification::make()->success()->title('In-store sale credited: AED '.$adj->amount)->send();
        } catch (\RuntimeException $e) {
            Notification::make()->danger()->title('Not saved')->body($e->getMessage())->send();
        }
    }

    /** Manual bonus or correction (negative to take back). */
    public static function adjustAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('adjust')->label('Bonus / correction')->icon('heroicon-o-plus-circle')->color('gray')
            ->visible(fn () => (bool) auth()->user()?->is_admin)
            ->form([
                Forms\Components\TextInput::make('amount')->numeric()->prefix('AED')->required()->helperText('Use a minus amount to take money back.'),
                Forms\Components\TextInput::make('description')->required()->maxLength(191),
            ])
            ->action(function (Affiliate $record, array $data) {
                \App\Models\AffiliateAdjustment::create(['affiliate_id' => $record->id, 'type' => 'manual', 'amount' => $data['amount'], 'status' => 'available',
                    'description' => $data['description'], 'created_by' => auth()->id()]);
                Notification::make()->success()->title('Saved')->send();
            });
    }

    public static function table(Table $table): Table
    {
        $money = fn (string $key) => fn (Affiliate $record) => Money::format($record->totals()[$key]);

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'recruitedBy']))
            ->columns([
                Tables\Columns\TextColumn::make('code')->badge()->searchable()->copyable(),
                Tables\Columns\TextColumn::make('user.name')->label('Name')->searchable()->description(fn (Affiliate $r) => $r->user?->email),
                Tables\Columns\TextColumn::make('rates')->label('Commission')
                    ->state(fn (Affiliate $r) => $r->commissionSummary())->wrap(),
                Tables\Columns\TextColumn::make('user.phone')->label('Phone')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('white_page_enabled')->label('White page')->boolean(),
                Tables\Columns\TextColumn::make('user.last_login_at')->label('Last login')->since()->sortable()->placeholder('Never')
                    ->tooltip(fn (Affiliate $r) => $r->user?->last_login_at?->timezone(config('app.timezone'))->format('d M Y, h:i A')),
                Tables\Columns\TextColumn::make('markup')->label('White-page mark-up')->wrap()->toggleable(isToggledHiddenByDefault: true)
                    ->state(fn (Affiliate $r) => $r->white_page_enabled ? $r->markupSummary() : '—'),
                Tables\Columns\TextColumn::make('coupon_discount_percent')->label('Code discount (% of commission)')->suffix('%')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('can_lock_price')->label('Price lock')->boolean()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('recruitedBy.code')->label('Recruited by')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('month_sales')->label('Sales this month')->toggleable(isToggledHiddenByDefault: true)
                    ->state(fn (Affiliate $r) => Money::format(rescue(fn () => $r->monthlySales(), 0, false))),
                Tables\Columns\TextColumn::make('orders_count')->label('Orders')->counts('orders')->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('level')->label('Level')->badge()->color('warning')->state(fn (Affiliate $r) => $r->level()['name'] ?? '—')->toggleable(),
                Tables\Columns\TextColumn::make('clicks30')->label('Visits (30 d)')->toggleable()
                    ->state(fn (Affiliate $r) => rescue(fn () => $r->clicks()->where('type', 'visit')->where('created_at', '>=', now()->subDays(30))->count(), 0, false)),
                Tables\Columns\TextColumn::make('pending')->label('Pending')->state($money('pending'))->color('gray'),
                Tables\Columns\TextColumn::make('earned')->label('Earned')->state($money('earned')),
                Tables\Columns\TextColumn::make('collected')->label('Collected')->state($money('collected')),
                Tables\Columns\TextColumn::make('balance')->label('To collect')->state($money('balance'))->weight('bold')->color('success'),
                Tables\Columns\TextColumn::make('is_active')->label('Status')->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Disabled')->color(fn ($state) => $state ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('created_at')->label('Joined')->date('d M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([Tables\Filters\TernaryFilter::make('is_active')->label('Status')->trueLabel('Active')->falseLabel('Disabled')])
            ->actions([self::collectAction(), self::toggleActive(Tables\Actions\Action::class), Tables\Actions\ActionGroup::make([self::storeSaleAction(), self::adjustAction()]), self::loginAsAction(Tables\Actions\Action::class), Tables\Actions\EditAction::make()])
            // Row buttons first: the table is wider than most screens.
            ->actionsPosition(Tables\Enums\ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkAction::make('activate')->label('Activate')->icon('heroicon-o-play')->color('success')->requiresConfirmation()
                    ->visible(fn () => \App\Support\StaffAccess::current('affiliates', 'edit'))
                    ->action(fn (\Illuminate\Support\Collection $records) => $records->each(fn (Affiliate $a) => self::setActive($a, true))),
                Tables\Actions\BulkAction::make('disable')->label('Disable')->icon('heroicon-o-pause')->color('danger')->requiresConfirmation()
                    ->modalDescription(self::DISABLE_NOTE)
                    ->visible(fn () => \App\Support\StaffAccess::current('affiliates', 'edit'))
                    ->action(fn (\Illuminate\Support\Collection $records) => $records->each(fn (Affiliate $a) => self::setActive($a, false))),
            ]);
    }

    public const DISABLE_NOTE = 'While disabled: their link, own page and coupon code stop working, and new visits are not credited to them. They can still sign in, see their balance and collect money already earned. Orders already placed keep their commission.';

    /** "Disable" / "Activate" button for one affiliate (table row or edit page). */
    public static function toggleActive(string $actionClass, ?\Closure $after = null)
    {
        return $actionClass::make('toggleActive')
            ->label(fn (Affiliate $record) => $record->is_active ? 'Disable' : 'Activate')
            ->icon(fn (Affiliate $record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
            ->color(fn (Affiliate $record) => $record->is_active ? 'danger' : 'success')
            ->visible(fn () => \App\Support\StaffAccess::current('affiliates', 'edit'))
            ->requiresConfirmation()
            ->modalHeading(fn (Affiliate $record) => ($record->is_active ? 'Disable ' : 'Activate ').$record->code.'?')
            ->modalDescription(fn (Affiliate $record) => $record->is_active ? self::DISABLE_NOTE : 'Their link, own page and coupon code work again.')
            ->form(fn (Affiliate $record) => $record->is_active ? [
                \Filament\Forms\Components\Checkbox::make('void_pending')->label('Also cancel their commission on orders not delivered yet')
                    ->helperText('Money already available or collected is not touched.'),
            ] : [])
            ->action(function (Affiliate $record, array $data) use ($after) {
                if ($record->is_active && ! empty($data['void_pending'])) {
                    app(\App\Affiliates\AffiliateService::class)->voidPending($record);
                }
                self::setActive($record, ! $record->is_active);
                \Filament\Notifications\Notification::make()->success()->title($record->code.($record->is_active ? ' is active' : ' is disabled'))->send();
                $after && $after();
            });
    }

    public static function setActive(Affiliate $affiliate, bool $active): void
    {
        $affiliate->update(['is_active' => $active]);   // saving also switches their coupon code on / off
    }

    /** Staff hand over the affiliate's money at the store. */
    public static function collectAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('collect')->label('Record collection')->icon('heroicon-o-banknotes')->color('success')
            ->visible(fn () => \App\Support\StaffAccess::current('affiliate_payouts', 'create'))
            ->modalDescription(fn (Affiliate $record) => 'Ready to collect: '.Money::format($record->totals()['balance']).'. Give the cash at the store, then save. It is taken off the balance.')
            ->form([
                Forms\Components\TextInput::make('amount')->label('Amount handed over')->numeric()->prefix('AED')->required()->minValue(0.01)
                    ->default(fn (Affiliate $record) => Money::toAed(max(0, $record->totals()['balance']))),
                Forms\Components\Select::make('branch_id')->label('Store')->options(fn () => Branch::pluck('name', 'id')),
                Forms\Components\TextInput::make('reference')->label('Receipt / voucher no.')->maxLength(64),
                Forms\Components\Textarea::make('note')->rows(2),
            ])
            ->action(function (Affiliate $record, array $data) {
                try {
                    app(AffiliateService::class)->payout($record, (string) $data['amount'], auth()->user(), $data['branch_id'] ?? null, $data['reference'] ?? null, $data['note'] ?? null);
                    Notification::make()->success()->title('Collection recorded')->body('New balance: '.Money::format($record->fresh()->totals()['balance']))->send();
                } catch (\RuntimeException $e) {
                    Notification::make()->danger()->title('Not saved')->body($e->getMessage())->send();
                }
            });
    }

    public static function getRelations(): array
    {
        return [RelationManagers\CommissionsRelationManager::class, RelationManagers\AdjustmentsRelationManager::class, RelationManagers\PayoutsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAffiliates::route('/'),
            'create' => Pages\CreateAffiliate::route('/create'),
            'edit' => Pages\EditAffiliate::route('/{record}/edit'),
        ];
    }

    /**
     * Admin "Log in as" the affiliate (table row and edit page): see their portal exactly as they do.
     *
     * @param  class-string  $class  Tables\Actions\Action or Filament\Actions\Action
     */
    public static function loginAsAction(string $class)
    {
        return $class::make('loginAs')->label('Log in as')->icon('heroicon-o-eye')->color('gray')
            ->visible(fn () => (bool) auth()->user()?->is_admin)
            ->requiresConfirmation()->modalHeading('Log in as this affiliate')
            ->modalDescription('Opens their portal exactly as they see it (prices, commission, orders). They are not told and their last-login time does not change. A red bar lets you go back to the admin; orders cannot be placed meanwhile.')
            ->action(function (Affiliate $record) {
                \App\Support\Impersonation::start(auth()->user(), $record->user);

                return redirect()->route('affiliate.dashboard');
            });
    }
}
