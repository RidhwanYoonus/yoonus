<?php

use App\Support\StorefrontCache;
use Illuminate\Support\Facades\Schedule;

Schedule::command('shop:expire-unpaid')->everyMinute()->withoutOverlapping();
Schedule::command('shop:abandoned-carts')->everyTenMinutes()->withoutOverlapping();
Schedule::command('shop:fetch-gold-rates')->everyFifteenMinutes()->withoutOverlapping();
Schedule::call(fn () => StorefrontCache::flushPriceDependent())->hourly()->name('refresh-feeds');
Schedule::command('queue:prune-batches --hours=72')->daily();
