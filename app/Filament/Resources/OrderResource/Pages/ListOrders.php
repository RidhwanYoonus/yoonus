<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('All')];
        // Card payments the customer completed on Mettpay that staff still need to confirm.
        $verifying = \App\Models\Order::where('payment_status', 'verifying')->count();
        $tabs['verifying'] = Tab::make('Payment to confirm')->badge($verifying ?: null)->badgeColor('warning')
            ->modifyQueryUsing(fn ($query) => $query->where('payment_status', 'verifying'));
        // Placed but not paid yet (online orders still inside their payment time, and cash on delivery).
        $unpaid = \App\Models\Order::where('status', 'pending')->whereIn('payment_status', ['unpaid', 'cod'])->count();
        $tabs['unpaid'] = Tab::make('Unpaid')->icon('heroicon-o-clock')->badge($unpaid ?: null)->badgeColor('gray')
            ->modifyQueryUsing(fn ($query) => $query->where('status', 'pending')->whereIn('payment_status', ['unpaid', 'cod']));
        foreach (['pending', 'paid', 'packed', 'shipped', 'ready', 'delivered'] as $s) {
            $tabs[$s] = Tab::make($s === 'delivered' ? 'Delivered / collected' : __('shop.status.'.$s))->modifyQueryUsing(fn ($query) => $query->where('status', $s));
        }

        $tabs['cancelled'] = Tab::make('Cancelled')->icon('heroicon-o-x-circle')
            ->modifyQueryUsing(fn ($query) => $query->where('status', 'cancelled'));

        return $tabs;
    }
}
