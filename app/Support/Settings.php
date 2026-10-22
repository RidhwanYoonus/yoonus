<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Admin-editable key/value settings (store details, TRN, pixels, toggles).
 * Defaults live in config/shop.php; stored values override them.
 */
class Settings
{
    private const CACHE_KEY = 'settings.all';

    public static function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return Setting::query()->pluck('value', 'key')
                    ->map(fn ($v) => json_decode($v, true))
                    ->all();
            } catch (Throwable) {
                return [];
            }
        });

        return array_merge(config('shop.defaults', []), $stored);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::all();

        return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
    }

    /** Link to a picture uploaded in Settings (logo, favicon, home photo…), or null when none. */
    public static function imageUrl(string $key): ?string
    {
        $path = static::get($key);

        return is_string($path) && $path !== '' ? \Illuminate\Support\Facades\Storage::disk('public')->url($path) : null;
    }

    public static function bool(string $key): bool
    {
        return filter_var(static::get($key, false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
        }
        static::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
