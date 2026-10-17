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
        foreach (['pending', 'paid', 'packed', 'shipped', 'delivered'] as $s) {
            $tabs[$s] = Tab::make(ucfirst($s))->modifyQueryUsing(fn ($query) => $query->where('status', $s));
        }

        return $tabs;
    }
}
