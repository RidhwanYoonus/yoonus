<?php

use App\Support\AutoTasks;
use App\Support\StorefrontCache;
use Illuminate\Support\Facades\Schedule;

Schedule::call(fn () => AutoTasks::heartbeat())->everyMinute()->name('cron-heartbeat');
Schedule::command('shop:expire-unpaid')->everyMinute()->withoutOverlapping();
Schedule::command('shop:abandoned-carts')->everyTenMinutes()->withoutOverlapping();
Schedule::command('shop:fetch-gold-rates')->everyFifteenMinutes()->withoutOverlapping();
Schedule::call(fn () => StorefrontCache::flushPriceDependent())->hourly()->name('refresh-feeds');
Schedule::command('queue:prune-batches --hours=72')->daily();
// Shared hosting (cPanel) has no always-running worker: the every-minute cron sends queued emails / WhatsApp.
if (config('queue.default') !== 'sync') {
    Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')->everyMinute()->withoutOverlapping()->runInBackground();
}
// Emails held back by the daily limit go out as soon as the new day allows.
Schedule::call(fn () => \App\Messaging\EmailQueue::flush())->everyFifteenMinutes()->name('queued-emails')->withoutOverlapping();
Schedule::command('shop:prune-activity')->dailyAt('03:17')->withoutOverlapping();
