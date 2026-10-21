<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;

/**
 * "php artisan optimize" from the admin: settings, routes, views and the admin panel are cached so pages build faster.
 * When an update (new files) or a .env change is newer than the cache, it is rebuilt automatically on the next visit,
 * so a cached copy can never hide new pages or settings.
 */
class SpeedCache
{
    public static function isOn(): bool
    {
        return app()->configurationIsCached() && app()->routesAreCached();
    }

    public static function build(): string
    {
        Artisan::call('optimize:clear');
        Artisan::call('optimize');

        return trim(Artisan::output());
    }

    public static function clear(): string
    {
        Artisan::call('optimize:clear');

        return trim(Artisan::output());
    }

    /** True when code or .env changed after the cache was made (e.g. a new update zip was unpacked). */
    public static function isStale(): bool
    {
        $cache = app()->getCachedConfigPath();
        if (! is_file($cache)) {
            return false;
        }
        $built = filemtime($cache);
        $paths = [base_path('.env'), base_path('composer.lock')];
        foreach (['routes', 'config', 'app/Providers', 'database/migrations', 'resources/views/layouts'] as $dir) {
            foreach (glob(base_path($dir).'/*.php') ?: [] as $f) {
                $paths[] = $f;
            }
        }
        foreach ($paths as $f) {
            if (is_file($f) && filemtime($f) > $built) {
                return true;
            }
        }

        return false;
    }

    /** Called early on each web request; cheap (file dates only) and does the rebuild once. */
    public static function refreshIfStale(): void
    {
        if (! app()->configurationIsCached() || ! self::isStale()) {
            return;
        }
        $lock = fopen(storage_path('framework/speed-cache.lock'), 'c');
        if ($lock && flock($lock, LOCK_EX | LOCK_NB)) {
            try {
                if (self::isStale()) {
                    self::build();
                }
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }
}
