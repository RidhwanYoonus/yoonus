<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Models\OrderItem;
use App\Shop\OrderService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Items (price snapshot)';

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('product_name')->description(fn (OrderItem $record) => "{$record->sku} · {$record->karat} · {$record->weight_grams} g"),
            Tables\Columns\TextColumn::make('qty'),
            Tables\Columns\TextColumn::make('gold_rate_snapshot')->label('Rate / g')->money('AED'),
            Tables\Columns\TextColumn::make('gold_value')->money('AED'),
            Tables\Columns\TextColumn::make('making_fee')->money('AED'),
            Tables\Columns\TextColumn::make('stone_value')->money('AED'),
            Tables\Columns\TextColumn::make('discount_amount')->label('Discount')->money('AED'),
            Tables\Columns\TextColumn::make('vat_amount')->label('VAT')->money('AED'),
            Tables\Columns\TextColumn::make('line_total')->money('AED')->weight('bold'),
        ])->actions([
            Tables\Actions\Action::make('override')->label('Override price')->icon('heroicon-o-pencil-square')
                ->visible(fn () => ! $this->getOwnerRecord()->isPaid() && ! $this->getOwnerRecord()->invoice_number)
                ->form(fn (OrderItem $record) => [
                    Forms\Components\TextInput::make('line_total')->label('New line total (incl. VAT)')->numeric()->prefix('AED')->required()->default($record->line_total),
                    Forms\Components\Textarea::make('reason')->required()->minLength(5)->maxLength(255),
                ])
                ->action(function (OrderItem $record, array $data) {
                    try {
                        app(OrderService::class)->overrideLine($record, (string) $data['line_total'], $data['reason'], Auth::user());
                        Notification::make()->success()->title('Price overridden and logged')->send();
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                    }
                }),
        ]);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
