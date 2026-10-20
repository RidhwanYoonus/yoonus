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
        ])->filters([
        ])->actions([
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
