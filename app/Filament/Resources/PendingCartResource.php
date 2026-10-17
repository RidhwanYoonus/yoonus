<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PendingCartResource\Pages;
use App\Messaging\WhatsApp;
use App\Models\Cart;
use App\Models\Product;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Shoppers who put pieces in their bag but have not placed the order yet. */
class PendingCartResource extends Resource
{
    protected static ?string $model = Cart::class;

    protected static ?string $slug = 'pending-carts';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Pending carts';

    protected static ?string $pluralModelLabel = 'Pending carts';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    /** A cart is pending while it holds items and has not turned into an order. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('converted_at')->where('last_known_total', '>', 0);
    }

    public static function getNavigationBadge(): ?string
    {
        $n = static::getEloquentQuery()->where('last_activity_at', '>=', now()->subDays(7))->count();

        return $n ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_activity_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                Tables\Columns\TextColumn::make('last_activity_at')->label('Last active')->since()->sortable()
                    ->description(fn (Cart $record) => $record->last_activity_at?->format('d M, H:i')),
                Tables\Columns\TextColumn::make('customer')->label('Customer')
                    ->state(fn (Cart $record) => $record->user?->name ?? ($record->email || $record->phone ? 'Guest' : 'Guest — no contact yet'))
                    ->description(fn (Cart $record) => collect([$record->email ?? $record->user?->email, $record->phone ?? $record->user?->phone])->filter()->implode(' · ') ?: null)
                    ->searchable(['email', 'phone']),
                Tables\Columns\TextColumn::make('items')->label('In the bag')->wrap()->width('18rem')
                    ->state(fn (Cart $record) => self::itemsSummary($record)),
                Tables\Columns\TextColumn::make('last_known_total')->label('Value')->money('AED')->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('stage')->badge()
                    ->state(fn (Cart $record) => $record->checkout_started_at ? 'Reached checkout' : 'In bag')
                    ->color(fn (string $state) => $state === 'Reached checkout' ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('reminders_sent')->label('Reminders')->alignCenter()
                    ->description(fn (Cart $record) => $record->last_reminder_at?->diffForHumans()),
                Tables\Columns\TextColumn::make('created_at')->label('Started')->dateTime('d M, H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('period')->label('Last active')->default('30')
                    ->options(['1' => 'Today', '7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days'])
                    ->query(fn (Builder $query, array $data) => $data['value'] ? $query->where('last_activity_at', '>=', now()->subDays((int) $data['value'])->startOfDay()) : $query),
                Tables\Filters\Filter::make('contact')->label('Has email or phone')->toggle()
                    ->query(fn (Builder $query) => $query->where(fn ($q) => $q->whereNotNull('email')->orWhereNotNull('phone')->orWhereNotNull('user_id'))),
                Tables\Filters\Filter::make('checkout')->label('Reached checkout')->toggle()
                    ->query(fn (Builder $query) => $query->whereNotNull('checkout_started_at')),
            ])
            ->actions([
                Tables\Actions\Action::make('whatsapp')->label('WhatsApp')->icon('heroicon-o-chat-bubble-oval-left')->color('success')
                    ->visible(fn (Cart $record) => (bool) ($record->phone ?? $record->user?->phone))
                    ->url(fn (Cart $record) => WhatsApp::link($record->phone ?? $record->user?->phone, 'Hello'.($record->user ? ' '.$record->user->name : '').', you left '.self::itemsSummary($record).' in your bag at our shop. Can we help you complete your order?'), true),
                Tables\Actions\Action::make('email')->label('Email')->icon('heroicon-o-envelope')->color('gray')
                    ->visible(fn (Cart $record) => (bool) ($record->email ?? $record->user?->email))
                    ->url(fn (Cart $record) => 'mailto:'.($record->email ?? $record->user?->email).'?subject='.rawurlencode('Your bag is waiting'), true),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()->label('Delete carts')])
            ->emptyStateHeading('No pending carts')
            ->emptyStateDescription('Carts with items that have not been ordered yet appear here.');
    }

    public static function itemsSummary(Cart $cart): string
    {
        static $names = [];
        $items = collect($cart->items ?? []);
        $missing = $items->pluck('product_id')->diff(array_keys($names));
        if ($missing->isNotEmpty()) {
            $names += Product::whereIn('id', $missing)->pluck('name', 'id')->all();
        }

        return $items->map(fn ($i) => ($names[$i['product_id']] ?? 'Removed product').($i['qty'] > 1 ? ' × '.$i['qty'] : ''))->implode(', ');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPendingCarts::route('/')];
    }
}
