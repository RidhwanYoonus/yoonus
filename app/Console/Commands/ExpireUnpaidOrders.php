<?php

namespace App\Console\Commands;

use App\Shop\OrderService;
use Illuminate\Console\Command;

/** Cancels online orders that were not paid within the payment window, so nobody can pay later at an old gold price. */
class ExpireUnpaidOrders extends Command
{
    protected $signature = 'shop:expire-unpaid';

    protected $description = 'Cancel unpaid online orders whose payment time has run out';

    public function handle(OrderService $orders): int
    {
        $n = 0;
        OrderService::expiredUnpaid()->pluck('id')->each(function ($id) use ($orders, &$n) {
            $n += (int) $orders->expireUnpaid(\App\Models\Order::find($id));
        });
        $this->info("Cancelled {$n} unpaid order(s).");

        return self::SUCCESS;
    }
}
