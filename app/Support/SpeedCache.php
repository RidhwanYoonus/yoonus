<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;

/**
 * Speed cache from the admin (like "php artisan optimize"): settings, routes, views and the admin panel are cached so pages build faster.
 * When an update (new files) or a .env change is newer than the cache, it is rebuilt automatically on the next visit,
 * so a cached copy can never hide new pages or settings.
 */
class SpeedCache
{
    public static function isOn(): bool
    {
        return app()->configurationIsCached() && app()->routesAreCached();
    }

    /**
     * Same as "php artisan optimize", minus the page (view) step: re-compiling pages while a page is being shown
     * breaks that page, and pages compile themselves on first use anyway.
     */
    private const BUILD = ['config:cache', 'route:cache', 'event:cache', 'filament:cache-components', 'icons:cache'];

    private const CLEAR = ['config:clear', 'route:clear', 'event:clear', 'filament:clear-cached-components', 'icons:clear'];

    public static function build(): string
    {
        self::run(self::CLEAR);

        return self::run(self::BUILD);
    }

    public static function clear(): string
    {
        return self::run(self::CLEAR);
    }

    private static function run(array $commands): string
    {
        $app = app();
        $out = [];
        try {
            foreach ($commands as $command) {
                if (array_key_exists($command, Artisan::all())) {
                    Artisan::call($command);
                    $out[] = trim(Artisan::output());
                }
            }
        } finally {
            // config:cache and route:cache boot a second copy of the app to read fresh settings, and that copy
            // makes itself "the" app. Put the running one back, or the page being shown breaks (error 500).
            \Illuminate\Container\Container::setInstance($app);
            \Illuminate\Support\Facades\Facade::setFacadeApplication($app);
            \Illuminate\Support\Facades\Facade::clearResolvedInstances();
        }

        return implode("\n", array_filter($out));
    }

    /** php artisan storage:link; true when public/storage works afterwards. */
    public static function storageLink(): bool
    {
        if (! is_dir(public_path('storage'))) {
            try {
                Artisan::call('storage:link');
            } catch (\Throwable) {
                // symlink() is switched off on some shared hosts
            }
        }

        return is_dir(public_path('storage'));
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
