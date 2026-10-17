<?php

namespace App\Filament\Resources\PendingCartResource\Widgets;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\PendingCartResource;
use App\Models\Cart;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as Base;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingCartStats extends Base
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $pending = PendingCartResource::getEloquentQuery()->where('last_activity_at', '>=', now()->subDays(30));
        $count = (clone $pending)->count();
        $contact = (clone $pending)->where(fn ($q) => $q->whereNotNull('email')->orWhereNotNull('phone')->orWhereNotNull('user_id'))->count();
        $checkout = (clone $pending)->whereNotNull('checkout_started_at')->count();
        $recovered = Cart::whereNotNull('converted_at')->where('reminders_sent', '>', 0)->where('converted_at', '>=', now()->subDays(30))->count();
        $awaiting = Order::where('status', 'pending')->where('payment_status', 'unpaid')->count();

        return [
            Stat::make('Pending carts · 30 days', number_format($count))
                ->description($checkout.' reached checkout')->color('warning'),
            Stat::make('Value waiting', 'AED '.number_format((float) (clone $pending)->sum('last_known_total'), 2))
                ->description('Excl. VAT'),
            Stat::make('Can be contacted', number_format($contact))
                ->description($count ? round($contact / $count * 100).'% left an email or phone' : 'No pending carts'),
            Stat::make('Orders awaiting payment', number_format($awaiting))
                ->description($recovered.' cart(s) recovered by reminders · 30 days')
                ->url(OrderResource::getUrl('index')),
        ];
    }
}
