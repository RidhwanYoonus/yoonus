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
            Forms\Components\Section::make('Login account')->columnSpan(2)->columns(2)
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
            Forms\Components\Section::make('Commission')->columnSpan(2)->columns(2)
                ->description('Commission becomes "ready to collect" when the order is delivered / collected. Cancelled or refunded orders earn nothing.')
                ->schema([
                    Forms\Components\Group::make()->schema([
                        Forms\Components\ToggleButtons::make('making_commission_type')->label('By-weight items: commission as')->inline()->required()->live()->default('percent')
                            ->options(['percent' => '% of making charge', 'per_gram' => 'Fixed AED per gram']),
                        Forms\Components\TextInput::make('making_commission_percent')->hiddenLabel()->numeric()->minValue(0)->maxValue(100)->suffix('%')
                            ->default(fn () => Settings::get('affiliate_default_making_percent', 10))
                            ->required(fn (Forms\Get $get) => $get('making_commission_type') !== 'per_gram')->visible(fn (Forms\Get $get) => $get('making_commission_type') !== 'per_gram')
                            ->helperText('Items priced by weight × gold rate. Example: making AED 300 × 10% = AED 30.'),
                        Forms\Components\TextInput::make('commission_per_gram')->hiddenLabel()->numeric()->minValue(0)->prefix('AED')->suffix('per gram')
                            ->default(fn () => Settings::get('affiliate_default_commission_per_gram', 2))
                            ->required(fn (Forms\Get $get) => $get('making_commission_type') === 'per_gram')->visible(fn (Forms\Get $get) => $get('making_commission_type') === 'per_gram')
                            ->helperText('Example: a 12.5 g chain × AED 2 = AED 25, whatever the making charge.'),
                    ]),
                    Forms\Components\TextInput::make('fixed_commission_percent')->label('Fixed-price items: % of item total')->numeric()->minValue(0)->maxValue(100)->suffix('%')->required()
                        ->default(fn () => Settings::get('affiliate_default_fixed_percent', 3))
                        ->helperText('Items with a fixed price per piece. % of the item price before VAT, after discounts.'),
                ]),
            Forms\Components\Section::make('White page (own price page)')->columnSpan(1)
                ->description('Their own link where every price includes their mark-up. They keep the full mark-up on top of the commission.')
                ->schema([
                    Forms\Components\Toggle::make('white_page_enabled')->label('Allow white page')->live(),
                    Forms\Components\Fieldset::make('Maximum mark-up allowed')->columns(1)->visible(fn (Forms\Get $get) => $get('white_page_enabled'))->schema([
                        Forms\Components\TextInput::make('max_making_markup_percent')->label('By-weight items: % of making charge')->numeric()->minValue(0)->maxValue(500)->suffix('%')
                            ->default(fn () => Settings::get('affiliate_default_max_making_markup', 30)),
                        Forms\Components\TextInput::make('max_fixed_markup_percent')->label('Fixed-price items: % of price')->numeric()->minValue(0)->maxValue(100)->suffix('%')
                            ->default(fn () => Settings::get('affiliate_default_max_markup', 10)),
                        Forms\Components\TextInput::make('max_markup_amount')->label('Or amount per piece')->numeric()->minValue(0)->prefix('AED')
                            ->default(fn () => Settings::get('affiliate_default_max_markup_amount', 100)),
                    ]),
                    Forms\Components\Fieldset::make('Mark-up in use')->columns(1)->visible(fn (Forms\Get $get) => $get('white_page_enabled'))->schema([
                        Forms\Components\Radio::make('markup_mode')->hiddenLabel()->default('percent')->live()
                            ->options(['percent' => 'Percentage', 'amount' => 'Same amount on every piece']),
                        Forms\Components\TextInput::make('making_markup_percent')->label('By-weight items: % of making')->numeric()->minValue(0)->suffix('%')->default(0)
                            ->lte('max_making_markup_percent')->visible(fn (Forms\Get $get) => $get('markup_mode') !== 'amount'),
                        Forms\Components\TextInput::make('fixed_markup_percent')->label('Fixed-price items: % of price')->numeric()->minValue(0)->suffix('%')->default(0)
                            ->lte('max_fixed_markup_percent')->visible(fn (Forms\Get $get) => $get('markup_mode') !== 'amount'),
                        Forms\Components\TextInput::make('markup_amount')->label('Amount per piece')->numeric()->minValue(0)->prefix('AED')->default(0)
                            ->lte('max_markup_amount')->visible(fn (Forms\Get $get) => $get('markup_mode') === 'amount'),
                        Forms\Components\Placeholder::make('markup_hint')->hiddenLabel()->content('The affiliate can change this on their dashboard, up to the maximums.'),
                    ]),
                ]),
            Forms\Components\Textarea::make('notes')->label('Internal notes')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $money = fn (string $key) => fn (Affiliate $record) => Money::format($record->totals()[$key]);

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                Tables\Columns\TextColumn::make('code')->badge()->searchable()->copyable(),
                Tables\Columns\TextColumn::make('user.name')->label('Name')->searchable()->description(fn (Affiliate $r) => $r->user?->email),
                Tables\Columns\TextColumn::make('rates')->label('Commission')
                    ->state(fn (Affiliate $r) => $r->goldCommissionLabel().' · '.rtrim(rtrim((string) $r->fixed_commission_percent, '0'), '.').'% fixed'),
                Tables\Columns\IconColumn::make('white_page_enabled')->label('White page')->boolean(),
                Tables\Columns\TextColumn::make('pending')->label('Pending')->state($money('pending'))->color('gray'),
                Tables\Columns\TextColumn::make('earned')->label('Earned')->state($money('earned')),
                Tables\Columns\TextColumn::make('collected')->label('Collected')->state($money('collected')),
                Tables\Columns\TextColumn::make('balance')->label('To collect')->state($money('balance'))->weight('bold')->color('success'),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([Tables\Filters\TernaryFilter::make('is_active')->label('Active')])
            ->actions([self::collectAction(), Tables\Actions\EditAction::make()]);
    }

    /** Staff hand over the affiliate's money at the store. */
    public static function collectAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('collect')->label('Record collection')->icon('heroicon-o-banknotes')->color('success')
            ->visible(fn () => \App\Support\StaffAccess::current('affiliates', 'edit'))
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
        return [RelationManagers\CommissionsRelationManager::class, RelationManagers\PayoutsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAffiliates::route('/'),
            'create' => Pages\CreateAffiliate::route('/create'),
            'edit' => Pages\EditAffiliate::route('/{record}/edit'),
        ];
    }
}
