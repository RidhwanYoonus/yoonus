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

    /** Sign-ins (shop and admin), wrong passwords and lockouts go to Settings → Security log. */
    private function logSignIns(): void
    {
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Failed::class, fn ($e) => \App\Support\SecurityLog::record('login_failed', $e->user, (string) ($e->credentials['email'] ?? '')));
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Lockout::class, fn ($e) => \App\Support\SecurityLog::record('lockout', email: (string) $e->request->input('email', $e->request->input('data.email'))));
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Logout::class, fn ($e) => $e->user ? \App\Support\SecurityLog::record('logout', $e->user) : null);
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function ($e) {
            if (! $e->user instanceof \App\Models\User || ! ($e->user->is_admin || $e->user->is_staff)) {
                \App\Support\SecurityLog::record('login', $e->user);

                return;
            }
            $new = \App\Support\SecurityLog::isNewDevice($e->user);
            \App\Support\SecurityLog::record('admin_login', $e->user);
            if ($new) {
                \App\Support\SecurityLog::record('new_device', $e->user);
                app(\App\Messaging\Messenger::class)->email($e->user->email, new \App\Mail\ShopMessage('New sign-in to your shop admin', [
                    'Someone signed in to the admin panel with your account from IP '.request()->ip().' ('.mb_substr((string) request()->userAgent(), 0, 120).') at '.now()->format('d M Y, H:i').'.',
                    'If this was not you, change your password immediately and turn on two-step sign-in under Settings → Security.',
                ]), 'admin_new_device');
            }
        });
    }

    public function boot(): void
    {
        // A new update or .env change makes the speed cache out of date: rebuild it once, automatically.
        if (! $this->app->runningInConsole()) {
            rescue(fn () => \App\Support\SpeedCache::refreshIfStale(), null, false);
        }
        // Every admin list gets the column menu, so staff choose which columns to show, print and export.
        // Every admin photo upload is shrunk in the browser before it is sent (fields can set their own size).
        \Filament\Forms\Components\FileUpload::configureUsing(fn (\Filament\Forms\Components\FileUpload $upload) => $upload
            ->imageResizeMode('contain')->imageResizeTargetWidth('2000')->imageResizeTargetHeight('2000')->imageResizeUpscale(false));
        \Filament\Tables\Columns\Column::configureUsing(fn (\Filament\Tables\Columns\Column $column) => $column->toggleable());
        // Live shop behind SSL: always https links. A PC without SSL (APP_URL=http://…) keeps http, or the design files would not load.
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
        // Windows PHP (e.g. XAMPP) often has no CA list, so every HTTPS call (translation, WhatsApp,
        // newsletter, gold rates) fails with "cURL error 60". Trust the Windows certificate store plus
        // the Mozilla list shipped in resources/certs — certificates are still fully verified.
        if (! ini_get('curl.cainfo') && ! ini_get('openssl.cafile') && is_file(resource_path('certs/cacert.pem'))) {
            \Illuminate\Support\Facades\Http::globalOptions(array_filter([
                'verify' => resource_path('certs/cacert.pem'),
                'curl' => PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA') ? [CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA] : null,
            ]));
        }
        try {
            \App\Support\MailSettings::apply();
        } catch (\Throwable) {
            // Fresh install before migrations: keep the .env mail settings.
        }
        // Long-running queue workers pick up email settings changed in the admin before each job.
        \Illuminate\Support\Facades\Queue::before(fn () => \App\Support\MailSettings::apply());

        $lockedOut = function (Request $r, array $headers) {
            \App\Support\SecurityLog::record('lockout', email: (string) $r->input('email'));

            return back()->withInput($r->only('email'))->withErrors(['email' => __('auth.throttle', ['seconds' => $headers['Retry-After'] ?? 60])]);
        };
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip())->response($lockedOut),
            Limit::perMinute(20)->by($r->ip())->response($lockedOut),
        ]);
        $this->logSignIns();
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
