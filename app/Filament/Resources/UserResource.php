<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\LoyaltyEntry;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use App\Filament\Concerns\HasStaffArea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    use HasStaffArea;

    protected static string $staffArea = 'customers';

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Customers';

    protected static ?string $navigationLabel = 'Customers';

    protected static ?string $modelLabel = 'customer';

    /** Shop customers only — admin and staff logins are managed in Settings → Staff & permissions. */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('is_admin', false)->where('is_staff', false);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('phone'),
            Forms\Components\Select::make('locale')->options(['en' => 'English', 'ar' => 'العربية'])->default('en'),
            Forms\Components\TextInput::make('buyer_trn')->label('TRN (B2B)')->maxLength(15),
            Forms\Components\TextInput::make('referral_code')->disabled(),
            Forms\Components\TextInput::make('password')->password()->revealable()->dehydrated(fn ($state) => filled($state))->required(fn (string $operation) => $operation === 'create'),
            Forms\Components\Toggle::make('marketing_opt_in'),
            Forms\Components\Section::make('Affiliate commission')->columns(2)->collapsible()->collapsed(fn (?User $record) => ! $record?->affiliate_commission_off)
                ->visible(fn (string $operation) => $operation === 'edit')
                ->schema([
                    Forms\Components\Toggle::make('affiliate_commission_off')->label('No affiliate commission for this customer')->inline(false)
                        ->helperText('On: this customer\'s orders never earn commission for any affiliate (sign-up code, links or white page) and they see normal shop prices. Orders already placed are not changed — use "Cancel pending commission" below for those.'),
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('voidPending')->label('Cancel pending commission on their orders')->color('danger')->icon('heroicon-o-x-circle')
                            ->requiresConfirmation()->modalDescription('Affiliate commission on this customer\'s orders that are not delivered yet is cancelled. Money already available or collected is not touched.')
                            ->action(function (?User $record) {
                                $n = app(\App\Affiliates\AffiliateService::class)->voidPending(customer: $record);
                                \Filament\Notifications\Notification::make()->success()->title($n.' commission line(s) cancelled')->send();
                            }),
                    ]),
                ]),
            Forms\Components\Section::make('Affiliate prices')->columns(2)->collapsible()
                ->visible(fn (?User $record) => (bool) $record?->signup_affiliate_id)
                ->description('This customer signed up with an affiliate\'s code and sees that affiliate\'s white-page prices (mark-up) while signed in. If the customer complains, switch it off here or use "Remove affiliate link" at the top.')
                ->schema([
                    Forms\Components\Placeholder::make('signup_affiliate')->label('Affiliate')
                        ->content(fn (?User $record) => $record?->signupAffiliate ? $record->signupAffiliate->code.' — '.($record->signupAffiliate->user?->name ?? '') : '—'),
                    Forms\Components\Placeholder::make('markup_now')->label('Prices now')
                        ->content(fn (?User $record) => $record?->markupAffiliate() ? 'With the affiliate\'s mark-up'
                            : ($record?->signupAffiliate && ! $record->signupAffiliate->signup_markup ? 'Normal shop prices (switched off for this affiliate)' : 'Normal shop prices')),
                    Forms\Components\Toggle::make('affiliate_markup_on')->label('Show this customer the affiliate\'s mark-up')->inline(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('email')->searchable()->copyable(),
            Tables\Columns\TextColumn::make('phone')->searchable(),
            Tables\Columns\TextColumn::make('orders_count')->counts('orders')->label('Orders')->sortable(),
            Tables\Columns\TextColumn::make('orders_sum_grand_total')->sum('orders', 'grand_total')->label('Lifetime spend')->money('AED')->sortable(),
            Tables\Columns\TextColumn::make('loyalty_entries_sum_points')->sum('loyaltyEntries', 'points')->label('Points'),
            Tables\Columns\TextColumn::make('created_at')->date()->label('Joined'),
            Tables\Columns\TextColumn::make('signupAffiliate.code')->label('Signed up via affiliate')->badge()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            Tables\Filters\TernaryFilter::make('signup_affiliate_id')->label('Signed up with an affiliate code')->nullable(),
        ])->actions([
            \Filament\Tables\Actions\Action::make('loginAs')->label('Log in as')->icon('heroicon-o-eye')->color('gray')
                ->visible(fn () => (bool) auth()->user()?->is_admin)
                ->requiresConfirmation()->modalHeading('Log in as this person')
                ->modalDescription('Opens the shop exactly as they see it (their portal, prices and orders). They are not told and their last-login time does not change. A red bar lets you go back to the admin; orders cannot be placed meanwhile.')
                ->action(function ($record) {
                    $user = $record instanceof \App\Models\User ? $record : $record->user;
                    \App\Support\Impersonation::start(auth()->user(), $user);

                    return redirect()->to($user->affiliate ? route('affiliate.dashboard') : route('account'));
                }),
            Tables\Actions\Action::make('activity')->label('Activity')->icon('heroicon-o-finger-print')->color('gray')
                ->visible(fn () => \App\Support\StaffAccess::current('activity'))
                ->url(fn (User $record) => \App\Filament\Resources\ActivityLogResource::urlFor($record->id)),
            Tables\Actions\Action::make('points')->visible(fn () => \App\Support\StaffAccess::current('customers', 'edit'))->label('Adjust points')->icon('heroicon-o-star')
                ->form([Forms\Components\TextInput::make('points')->integer()->required()->helperText('Negative to deduct')])
                ->action(function (User $record, array $data) {
                    LoyaltyEntry::create(['user_id' => $record->id, 'points' => (int) $data['points'], 'reason' => 'adjustment']);
                    Notification::make()->success()->title('Balance: '.$record->loyaltyBalance().' points')->send();
                }),
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
