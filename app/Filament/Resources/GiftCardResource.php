<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GiftCardResource\Pages;
use App\Models\GiftCard;
use App\Models\GiftCardTransaction;
use App\Pricing\Money;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use App\Filament\Concerns\HasStaffArea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class GiftCardResource extends Resource
{
    use HasStaffArea;

    protected static string $staffArea = 'gift_cards';

    protected static ?string $model = GiftCard::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'Offers';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('initial_value')->label('Denomination')->required()->disabledOn('edit')
                ->options(collect([250, 500, 1000, 2000, 5000])->mapWithKeys(fn ($v) => [$v => "AED {$v}"])),
            Forms\Components\DateTimePicker::make('expires_at')->default(now()->addYear()),
            Forms\Components\TextInput::make('recipient_name'),
            Forms\Components\TextInput::make('recipient_email')->email(),
            Forms\Components\Textarea::make('message')->maxLength(500)->columnSpanFull(),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            Tables\Columns\TextColumn::make('code')->searchable()->copyable()->weight('bold'),
            Tables\Columns\TextColumn::make('initial_value')->money('AED'),
            Tables\Columns\TextColumn::make('balance')->money('AED')->weight('bold'),
            Tables\Columns\TextColumn::make('source')->badge(),
            Tables\Columns\TextColumn::make('recipient_email')->placeholder('—'),
            Tables\Columns\TextColumn::make('expires_at')->date()->placeholder('Never'),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])->actions([
            Tables\Actions\Action::make('redeemInStore')->label('Redeem in store')->icon('heroicon-o-building-storefront')
                ->visible(fn (GiftCard $record) => $record->isUsable())
                ->form(fn (GiftCard $record) => [
                    Forms\Components\TextInput::make('amount')->numeric()->prefix('AED')->required()->minValue(0.01)->maxValue((float) $record->balance),
                    Forms\Components\TextInput::make('note')->placeholder('Branch / receipt number'),
                ])
                ->action(function (GiftCard $record, array $data) {
                    DB::transaction(function () use ($record, $data) {
                        $card = GiftCard::lockForUpdate()->find($record->id);
                        $amount = min(Money::toFils($data['amount']), Money::toFils($card->balance));
                        $card->update(['balance' => Money::toAed(Money::toFils($card->balance) - $amount)]);
                        GiftCardTransaction::create(['gift_card_id' => $card->id, 'amount' => Money::toAed(-$amount), 'channel' => 'store', 'note' => $data['note'] ?? null]);
                    });
                    Notification::make()->success()->title('Redeemed. New balance: AED '.$record->fresh()->balance)->send();
                }),
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageGiftCards::route('/')];
    }
}
