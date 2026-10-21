<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records each step a visitor takes (customers, guests and staff) for the Activity log and Live visitors.
 * Background calls (price refresh, rate ticker, feeds, assets) and search-engine bots are skipped.
 */
class LogActivity
{
    public const COOKIE = 'vid';

    /** Route name => event. Routes not listed are a plain "page_view" (GET) or skipped (other methods). */
    private const ROUTES = [
        'home' => 'browse', 'shop.index' => 'browse', 'category.show' => 'browse', 'product.show' => 'product_view', 'compare' => 'browse', 'gift-cards' => 'browse',
        'cart.show' => 'view_bag', 'cart.add' => 'add_to_bag', 'cart.update' => 'change_qty', 'cart.remove' => 'remove_from_bag', 'cart.recover' => 'cart_recovered',
        'checkout.show' => 'checkout', 'checkout.place' => 'order_placed', 'checkout.complete' => 'order_complete',
        'order.show' => 'view_order', 'order.invoice' => 'download_invoice', 'order.pay' => 'retry_payment', 'payments.return' => 'payment_return', 'order.track' => 'track_order',
        'logout' => 'logout', 'google.callback' => 'login_google',
        'account.addresses.store' => 'account', 'account.addresses.delete' => 'account', 'account.id.forget' => 'account',
        'phone.send' => 'phone_verify', 'phone.check' => 'phone_verify',
        'wishlist.toggle' => 'wishlist', 'compare.toggle' => 'compare', 'reviews.store' => 'review', 'video-call.store' => 'video_call',
        'store-visit.store' => 'store_visit', 'alerts.store' => 'gold_alert', 'newsletter.subscribe' => 'newsletter',
        'affiliate.page' => 'affiliate_page', 'affiliate.dashboard' => 'affiliate', 'affiliate.update' => 'affiliate', 'affiliate.lock' => 'price_lock',
    ];

    private const SKIP_ROUTES = ['checkout.quote', 'api.rates', 'placeholder', 'feed.google', 'feed.meta', 'sitemap', 'video-call.status',
        'payments.webhook', 'admin.update-database', 'admin.print', 'admin.id-document'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->cookies->get(self::COOKIE)) {
            $id = Str::random(32);
            $request->cookies->set(self::COOKIE, $id);
            Cookie::queue(self::COOKIE, $id, 60 * 24 * 365);
        }

        $request->attributes->set('activity_user', $request->user());   // still known after signing out

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if ($row = $this->row($request, $response)) {
                ActivityLog::create($row);
                // Steps taken as a guest join the account once they sign in or register.
                if (in_array($row['event'], ['login', 'register', 'login_google'], true) && $row['user_id']) {
                    ActivityLog::where('visitor_id', $row['visitor_id'])->whereNull('user_id')->update(['user_id' => $row['user_id']]);
                }
            }
        } catch (\Throwable) {
            // Logging must never break a page (e.g. before the database update ran).
        }
    }

    private function row(Request $request, Response $response): ?array
    {
        if (! Settings::bool('activity_log_enabled', true) || $request->isMethod('HEAD') || $request->isMethod('OPTIONS')) {
            return null;
        }
        $route = $request->route();
        $name = $route?->getName();
        $path = '/'.ltrim($request->path(), '/');
        $ua = (string) $request->userAgent();
        if (in_array($name, self::SKIP_ROUTES, true) || $request->hasHeader('X-Livewire') || str_starts_with($path, '/livewire')
            || str_starts_with($path, '/filament') || str_starts_with($path, '/build') || $path === '/up'
            || preg_match('/bot|crawl|spider|slurp|preview|monitor|curl|wget|python|headlesschrome\/[0-9.]+ \(compatible/i', $ua)
            || str_contains(strtolower((string) $request->header('Sec-Purpose', $request->header('Purpose'))), 'prefetch')) {
            return null;
        }

        $user = $request->user() ?? $request->attributes->get('activity_user');
        $status = $response->getStatusCode();
        $event = self::ROUTES[$name] ?? null;
        if (str_starts_with($path, '/admin')) {
            if (! $request->isMethod('GET') || ! $user) {
                return null;
            }
            $event = 'admin_page';
        }
        $subject = null;
        $details = [];

        if ($request->isMethod('POST') && $path === '/login') {
            $event = $user ? 'login' : 'login_failed';
            $subject = $request->input('email');
        } elseif ($request->isMethod('POST') && $path === '/register') {
            $event = $user ? 'register' : null;
        } elseif ($name === 'shop.index' && filled($request->query('q'))) {
            $event = 'search';
            $subject = Str::limit((string) $request->query('q'), 100, '');
        } elseif ($name === 'checkout.place') {
            $subject = $request->attributes->get('activity_order');
            if (! $subject) {
                $event = 'checkout_failed';
                $errors = $request->session()->get('errors');
                $details['reason'] = $errors ? array_slice(array_unique($errors->all()), 0, 4) : [];
            }
            $details['payment'] = $request->input('payment_method');
            $details['code'] = $request->input('coupon') ?: null;
        }
        $event ??= $request->isMethod('GET') ? 'page_view' : null;
        if (! $event) {
            return null;
        }
        if ($status === 404 && $request->isMethod('GET')) {
            $event = 'not_found';
        }

        // What it was about: the product, order or affiliate in the address.
        foreach (['product' => 'name', 'order' => 'number', 'code' => null] as $param => $field) {
            $value = $route?->parameter($param);
            if ($subject === null && $value !== null) {
                $subject = is_object($value) ? (string) ($value->{$field} ?? '') : (string) $value;
            }
        }
        if (in_array($event, ['add_to_bag', 'change_qty'], true) && $request->filled('qty')) {
            $details['qty'] = (int) $request->input('qty');
        }
        $referrer = (string) $request->headers->get('referer');
        $host = parse_url($referrer, PHP_URL_HOST);

        return [
            'visitor_id' => (string) $request->cookies->get(self::COOKIE),
            'user_id' => $user?->id,
            'email' => $user?->email ?? (filled($request->input('email')) && filter_var($request->input('email'), FILTER_VALIDATE_EMAIL) ? Str::lower(Str::limit($request->input('email'), 180, '')) : null),
            'event' => $event,
            'subject' => $subject !== null ? Str::limit($subject, 180, '') : null,
            'method' => $request->method(),
            'path' => Str::limit($path.($request->getQueryString() && $request->isMethod('GET') ? '?'.$request->getQueryString() : ''), 250, ''),
            'referrer' => $host && $host !== $request->getHost() ? Str::limit($referrer, 250, '') : null,
            'status' => $status,
            'ip' => $request->ip(),
            'device' => ActivityLog::device($ua),
            'user_agent' => Str::limit($ua, 250, ''),
            'is_staff' => (bool) ($user?->is_admin || $user?->is_staff),
            'details' => array_filter($details, fn ($v) => $v !== null && $v !== []) ?: null,
            'created_at' => now(),
        ];
    }
}
