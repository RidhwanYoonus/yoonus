<?php

namespace App\Support;

/** Plain-language speed tips for the dashboard: what in the server set-up slows the shop down. */
class SpeedCheck
{
    /** @return array<int, array{level: string, text: string}> */
    public static function problems(): array
    {
        $out = [];
        $add = function (string $level, string $text) use (&$out) {
            $out[] = ['level' => $level, 'text' => $text];
        };

        if (PHP_SAPI === 'cli-server') {
            $add('danger', 'The shop runs on "php artisan serve" (port 8000). It answers one request at a time, so every photo and file waits in a queue. Use XAMPP\'s Apache (http://localhost/…) on this PC, and a real web host for the live shop.');
        }
        if (! self::opcacheOn()) {
            $add('danger', 'PHP OPcache is off: PHP re-reads every file on each page. In php.ini set zend_extension=opcache, opcache.enable=1, opcache.memory_consumption=256, opcache.max_accelerated_files=20000, then restart Apache. (Often 3–5× faster.)');
        }
        if (extension_loaded('xdebug')) {
            $add('danger', 'The Xdebug extension is loaded. It slows every page a lot; turn it off in php.ini unless you are debugging.');
        }
        if (config('app.debug')) {
            $add('warning', 'APP_DEBUG is on. Set APP_DEBUG=false (and APP_ENV=production) in .env for the live shop: faster, and safer.');
        }
        if (! app()->configurationIsCached() || ! app()->routesAreCached()) {
            $add('warning', 'Settings and routes are not cached. After each update run: php artisan optimize  (and php artisan filament:optimize). Run php artisan optimize:clear before editing .env.');
        } elseif (! is_dir(base_path('bootstrap/cache/filament'))) {
            $add('info', 'The admin panel is not cached. Run: php artisan filament:optimize');
        }
        if (config('cache.default') === 'database') {
            $add('warning', 'Cache is kept in the database (CACHE_STORE=database). Set CACHE_STORE=file in .env (or redis on a server) so cached prices and pages are read from disk.');
        }
        if (config('session.driver') === 'database') {
            $add('info', 'Sessions are kept in the database. On one server SESSION_DRIVER=file is a little faster.');
        }
        if (config('queue.default') === 'sync') {
            $add('info', 'Emails and WhatsApp messages are sent while the customer waits (QUEUE_CONNECTION=sync). Use QUEUE_CONNECTION=database with a running "php artisan queue:work" so checkout is instant.');
        }

        return $out;
    }

    private static function opcacheOn(): bool
    {
        if (! function_exists('opcache_get_status')) {
            return false;
        }
        $status = @opcache_get_status(false);

        return is_array($status) && ! empty($status['opcache_enabled']);
    }
}
