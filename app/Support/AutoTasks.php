<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/** Lets the admin see whether the cPanel cron job runs and how the automatic gold rate update went. */
class AutoTasks
{
    public static function heartbeat(): void
    {
        Cache::forever('auto.cron_last_run', now()->toIso8601String());
    }

    public static function cronLastRun(): ?Carbon
    {
        $at = rescue(fn () => Cache::get('auto.cron_last_run'), null, false);

        return $at ? Carbon::parse($at) : null;
    }

    public static function cronRunning(): bool
    {
        return (bool) self::cronLastRun()?->gt(now()->subMinutes(5));
    }

    public static function recordGoldRate(bool $ok, string $message): void
    {
        Cache::forever('auto.gold_rate', ['at' => now()->toIso8601String(), 'ok' => $ok, 'message' => $message]);
    }

    /** @return array{at: Carbon, ok: bool, message: string}|null */
    public static function goldRate(): ?array
    {
        $status = rescue(fn () => Cache::get('auto.gold_rate'), null, false);

        return $status ? ['at' => Carbon::parse($status['at'])] + $status : null;
    }

    public static function cronText(): string
    {
        $last = self::cronLastRun();
        if (! $last) {
            return '✗ The cron job has not run yet. In cPanel → Cron Jobs add (Once Per Minute): cd '.base_path().' && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1';
        }

        return self::cronRunning()
            ? '✓ Cron job is running (last run '.$last->diffForHumans().').'
            : '✗ The cron job stopped — last run '.$last->diffForHumans().'. Check cPanel → Cron Jobs.';
    }

    public static function goldRateText(): string
    {
        $status = self::goldRate();
        if (! $status) {
            return 'No automatic update has run yet (it runs every 15 minutes once switched on and saved).';
        }

        return ($status['ok'] ? '✓ ' : '✗ ').'Last automatic update '.$status['at']->timezone(config('app.timezone'))->format('d M Y, h:i A')
            .' ('.$status['at']->diffForHumans().'): '.$status['message'];
    }
}
