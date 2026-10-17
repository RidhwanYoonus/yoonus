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
        foreach (['pending', 'paid', 'packed', 'shipped', 'ready', 'delivered'] as $s) {
            $tabs[$s] = Tab::make($s === 'delivered' ? 'Delivered / collected' : __('shop.status.'.$s))->modifyQueryUsing(fn ($query) => $query->where('status', $s));
        }

        return $tabs;
    }
}
