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
        foreach (['pending', 'paid', 'packed', 'shipped', 'ready', 'delivered'] as $s) {
            $tabs[$s] = Tab::make($s === 'delivered' ? 'Delivered / collected' : __('shop.status.'.$s))->modifyQueryUsing(fn ($query) => $query->where('status', $s));
        }

        return $tabs;
    }
}
