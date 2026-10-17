<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\LoyaltyEntry;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Customers';

    protected static ?string $navigationLabel = 'Customers';

    protected static ?string $modelLabel = 'customer';

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
            Forms\Components\Toggle::make('is_admin')->label('Admin panel access')->helperText('Grants full access to this admin panel.'),
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
            Tables\Columns\IconColumn::make('is_admin')->boolean()->label('Admin'),
            Tables\Columns\TextColumn::make('created_at')->date()->label('Joined'),
        ])->filters([
            Tables\Filters\TernaryFilter::make('is_admin'),
        ])->actions([
            Tables\Actions\Action::make('points')->label('Adjust points')->icon('heroicon-o-star')
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
