<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers\ItemsRelationManager;
use App\Messaging\WhatsApp;
use App\Models\Order;
use App\Shop\InvoiceService;
use App\Shop\OrderService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Throwable;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'number';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Order::whereIn('status', ['pending', 'paid'])->count();

        return $n ? (string) $n : null;
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'warning', 'paid' => 'info', 'packed' => 'primary', 'shipped' => 'primary',
            'delivered' => 'success', default => 'danger',
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable()->weight('bold')->copyable(),
                Tables\Columns\TextColumn::make('created_at')->label('Date')->dateTime('d M Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('customer_name')->searchable(['customer_name', 'email', 'phone'])->description(fn (Order $record) => $record->phone),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => self::statusColor($state)),
                Tables\Columns\TextColumn::make('payment_method')->label('Payment')->badge()->color('gray')
                    ->description(fn (Order $record) => $record->payment_status),
                Tables\Columns\TextColumn::make('fulfilment')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('grand_total')->money('AED')->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('invoice_number')->toggleable()->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(array_combine(Order::STATUSES, array_map('ucfirst', Order::STATUSES)))->multiple(),
                Tables\Filters\SelectFilter::make('payment_method')->options(['card' => 'Card', 'tabby' => 'Tabby', 'tamara' => 'Tamara', 'cod' => 'Cash on delivery', 'giftcard' => 'Gift card']),
                Tables\Filters\Filter::make('created_at')->form([
                    Forms\Components\DatePicker::make('from'), Forms\Components\DatePicker::make('until'),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($query, $v) => $query->whereDate('created_at', '>=', $v))
                    ->when($data['until'] ?? null, fn ($query, $v) => $query->whereDate('created_at', '<=', $v))),
            ])
            ->actions([Tables\Actions\ViewAction::make(), self::advanceAction()->iconButton()]);
    }

    /** Move an order along Pending → Paid → Packed → Shipped → Delivered (or cancel/refund). */
    public static function advanceAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('advance')->label('Update status')->icon('heroicon-o-arrow-right-circle')
            ->form(fn (Order $record) => [
                Forms\Components\Select::make('status')->required()->options(collect(Order::TRANSITIONS[$record->status])
                    ->when($record->payment_method === 'cod' && $record->status === 'pending', fn ($options) => $options->push('packed'))
                    ->mapWithKeys(fn ($s) => [$s => ucfirst($s)])),
                Forms\Components\TextInput::make('tracking_carrier')->placeholder('Aramex, Emirates Post…')->default($record->tracking_carrier),
                Forms\Components\TextInput::make('tracking_number')->default($record->tracking_number),
                Forms\Components\TextInput::make('note')->maxLength(255),
            ])
            ->visible(fn (Order $record) => ! empty(Order::TRANSITIONS[$record->status]))
            ->action(function (Order $record, array $data) {
                try {
                    $record->update(array_filter(['tracking_carrier' => $data['tracking_carrier'] ?? null, 'tracking_number' => $data['tracking_number'] ?? null]));
                    app(OrderService::class)->transition($record, $data['status'], Auth::user(), $data['note'] ?? null);
                    Notification::make()->success()->title("Order {$record->number} → {$data['status']}")->send();
                } catch (Throwable $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                }
            });
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->columns(3)->schema([
            Infolists\Components\Section::make('Order')->columnSpan(2)->columns(3)->schema([
                Infolists\Components\TextEntry::make('number')->weight('bold'),
                Infolists\Components\TextEntry::make('status')->badge()->color(fn (string $state) => self::statusColor($state)),
                Infolists\Components\TextEntry::make('created_at')->dateTime(),
                Infolists\Components\TextEntry::make('payment_method')->badge(),
                Infolists\Components\TextEntry::make('payment_status')->badge(),
                Infolists\Components\TextEntry::make('payment_reference')->placeholder('—')->copyable(),
                Infolists\Components\TextEntry::make('invoice_number')->placeholder('Issued on payment'),
                Infolists\Components\TextEntry::make('coupon_code')->placeholder('—'),
                Infolists\Components\TextEntry::make('tracking_number')->placeholder('—')->prefix(fn (Order $record) => $record->tracking_carrier ? $record->tracking_carrier.' ' : ''),
                Infolists\Components\TextEntry::make('notes')->columnSpanFull()->placeholder('—'),
            ]),
            Infolists\Components\Section::make('Customer')->columnSpan(1)->schema([
                Infolists\Components\TextEntry::make('customer_name'),
                Infolists\Components\TextEntry::make('email')->copyable(),
                Infolists\Components\TextEntry::make('phone')->url(fn (Order $record) => WhatsApp::link($record->phone, "Order {$record->number}"))->openUrlInNewTab(),
                Infolists\Components\TextEntry::make('buyer_trn')->label('Buyer TRN')->placeholder('—'),
                Infolists\Components\TextEntry::make('shipping_address')->label('Delivery')->placeholder(fn (Order $record) => $record->pickupBranch ? 'Pickup: '.$record->pickupBranch->name : '—')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', array_filter([$state['line1'] ?? null, $state['line2'] ?? null, $state['city'] ?? null, $state['zone'] ?? null])) : $state),
            ]),
            Infolists\Components\Section::make('Totals (frozen at checkout)')->columnSpan(2)->columns(4)->schema([
                Infolists\Components\TextEntry::make('items_subtotal')->money('AED'),
                Infolists\Components\TextEntry::make('discount_total')->money('AED'),
                Infolists\Components\TextEntry::make('taxable_amount')->money('AED'),
                Infolists\Components\TextEntry::make('vat_total')->label('VAT')->money('AED'),
                Infolists\Components\TextEntry::make('shipping_total')->money('AED'),
                Infolists\Components\TextEntry::make('gift_card_amount')->money('AED'),
                Infolists\Components\TextEntry::make('grand_total')->money('AED')->weight('bold')->size('lg'),
                Infolists\Components\TextEntry::make('amount_due')->money('AED'),
            ]),
            Infolists\Components\Section::make('Status history')->columnSpan(1)->schema([
                Infolists\Components\RepeatableEntry::make('statusHistory')->hiddenLabel()->schema([
                    Infolists\Components\TextEntry::make('to_status')->hiddenLabel()->badge()
                        ->suffix(fn ($record) => ' · '.$record->created_at?->format('d M H:i').($record->changedBy ? ' · '.$record->changedBy->name : '').($record->note ? ' — '.$record->note : '')),
                ]),
            ]),
            Infolists\Components\Section::make('Price overrides')->columnSpanFull()->visible(fn (Order $record) => $record->priceOverrides()->exists())->schema([
                Infolists\Components\RepeatableEntry::make('priceOverrides')->hiddenLabel()->columns(4)->schema([
                    Infolists\Components\TextEntry::make('old_line_total')->money('AED'),
                    Infolists\Components\TextEntry::make('new_line_total')->money('AED'),
                    Infolists\Components\TextEntry::make('reason'),
                    Infolists\Components\TextEntry::make('changedBy.name')->label('By'),
                ]),
            ]),
        ]);
    }

    public static function invoiceAction(): Action
    {
        return Action::make('invoice')->label('Tax invoice (PDF)')->icon('heroicon-o-document-text')->color('gray')
            ->visible(fn (Order $record) => (bool) $record->invoice_number)
            ->action(function (Order $record) {
                $svc = app(InvoiceService::class);

                return response()->streamDownload(fn () => print ($svc->pdf($record)->output()), $svc->filename($record));
            });
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
