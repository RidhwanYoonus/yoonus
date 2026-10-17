<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/** Cached storefront outputs that embed prices (feeds, sitemap). */
class StorefrontCache
{
    public static function flushPriceDependent(): void
    {
        foreach (['feed.google', 'feed.meta', 'sitemap.xml', 'home.rates'] as $key) {
            Cache::forget($key);
        }
    }
}
