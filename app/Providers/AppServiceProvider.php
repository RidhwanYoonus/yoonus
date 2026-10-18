<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Product;
use App\Pricing\PricingService;
use App\Shop\CartService;
use App\Support\StorefrontCache;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PricingService::class);
        $this->app->scoped(CartService::class);
    }

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip()),
            Limit::perMinute(20)->by($r->ip()),
        ]);
        RateLimiter::for('checkout', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));

        // A product edit (e.g. switching making charge % ↔ per piece) re-prices it immediately.
        Product::saved(function (Product $p) {
            if ($p->wasChanged(['weight_grams', 'manufacturer_charge_type', 'manufacturer_charge_value', 'stone_value', 'wastage_percent', 'gold_category_id', 'pricing_type', 'fixed_price']) || $p->wasRecentlyCreated) {
                app(PricingService::class)->refreshCachedPrice($p, null, $p->wasRecentlyCreated ? 'created' : 'product_edit');
            }
            StorefrontCache::flushPriceDependent();
        });
        Product::deleted(fn () => StorefrontCache::flushPriceDependent());
        Category::saved(fn () => StorefrontCache::flushPriceDependent());

        View::composer('layouts.app', function ($view) {
            $view->with('cartCount', app(CartService::class)->count());
            $view->with('wishlistCount', count(\App\Http\Controllers\ShopController::wishlistIds(request())));
        });
    }
}
