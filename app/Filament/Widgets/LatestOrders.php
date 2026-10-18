<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestOrders extends TableWidget
{
    public static function canView(): bool
    {
        return \App\Support\StaffAccess::current('orders');
    }

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->latest())
            ->paginated([5])
            ->columns([
                Tables\Columns\TextColumn::make('number')->weight('bold'),
                Tables\Columns\TextColumn::make('created_at')->since(),
                Tables\Columns\TextColumn::make('customer_name'),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => OrderResource::statusColor($state)),
                Tables\Columns\TextColumn::make('payment_method')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('grand_total')->money('AED'),
            ])
            ->recordUrl(fn (Order $record) => OrderResource::getUrl('view', ['record' => $record]));
    }
}
