<?php

namespace App\Http\Middleware;

use App\Affiliates\AffiliateContext;
use App\Models\Affiliate;
use App\Pricing\PricingService;
use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * ?aff=CODE (referral link) or /w/CODE (white page) remembers the affiliate in a cookie, last
 * click wins. On the white page the affiliate's mark-up is added to every price the visitor sees.
 */
class TrackAffiliate
{
    public const COOKIE = 'aff';

    public function handle(Request $request, Closure $next): Response
    {
        $context = new AffiliateContext;
        app()->instance(AffiliateContext::class, $context);
        if ($request->is('admin', 'admin/*', 'livewire/*')) {
            return $next($request);
        }
        try {
            [$code, $white] = $this->fromRequest($request);
            if ($code !== null) {
                $affiliate = Affiliate::where('code', $code)->where('is_active', true)->first();
                // Affiliates don't earn on their own purchases, but may preview their own page.
                $own = $affiliate && $affiliate->user_id === $request->user()?->id;
                if ($affiliate && (! $own || $request->routeIs('affiliate.page'))) {
                    $context->affiliate = $affiliate;
                    $context->whitePage = $white && $affiliate->white_page_enabled;
                    $context->preview = $own;
                }
            }
            // Signed up with an affiliate's code (Settings → Affiliates): that affiliate's prices, always.
            // Signed up with an affiliate's code: their orders count for that affiliate (commission), and the
            // affiliate's white-page prices apply when that is switched on (white page, affiliate and customer).
            if (! $context->preview && ($linked = $request->user()?->linkedAffiliate())) {
                $context->affiliate = $linked;
                $context->whitePage = (bool) $request->user()->markupAffiliate();
            }
            // Admin stopped affiliate commission for this customer: no affiliate at all (normal prices, nothing earned).
            if (! $context->preview && $request->user()?->affiliate_commission_off) {
                $context->affiliate = null;
                $context->whitePage = false;
            }
        } catch (Throwable) {
            // Before the database update has run.
        }
        app(PricingService::class)->setMarkup($context->markup());
        view()->share('affiliateContext', $context);
        if ($context->affiliate && ! $context->preview) {
            $this->track($request, $context);
        }

        $response = $next($request);
        if ($context->affiliate && ! $context->preview && ($request->query('aff') || $request->routeIs('affiliate.page'))) {
            $value = $context->affiliate->code.'|'.($context->whitePage ? 'w' : 'r');
            $days = max(1, (int) Settings::get('affiliate_cookie_days', 30));
            Cookie::queue(self::COOKIE, $value, $days * 1440);
            if ($request->hasSession()) {
                $request->session()->put(self::COOKIE, $value);
            }
        }

        return $response;
    }

    /** Visits through the link (once per visitor per 30 min) and product views (once per visitor per product per day). */
    private function track(Request $request, AffiliateContext $context): void
    {
        try {
            $visitor = substr(hash('sha256', $request->ip().'|'.$request->userAgent().'|'.config('app.key')), 0, 40);
            $entry = $request->query('aff') || $request->routeIs('affiliate.page');
            $product = $request->routeIs('product.show') ? $request->route('product') : null;
            $productId = $product instanceof \App\Models\Product ? $product->id : null;
            $log = function (string $type, ?int $productId, int $minutes) use ($context, $visitor) {
                $key = "aff_click.{$context->affiliate->id}.{$type}.{$productId}.{$visitor}";
                if (\Illuminate\Support\Facades\Cache::add($key, 1, now()->addMinutes($minutes))) {
                    \App\Models\AffiliateClick::create(['affiliate_id' => $context->affiliate->id, 'type' => $type, 'product_id' => $productId,
                        'white_page' => $context->whitePage, 'visitor' => $visitor, 'created_at' => now()]);
                }
            };
            if ($entry) {
                $log('visit', null, 30);
            }
            if ($productId) {
                $log('product', $productId, 1440);
            }
        } catch (Throwable) {
            // Tracking must never break the shop.
        }
    }

    /** @return array{0: ?string, 1: bool} */
    private function fromRequest(Request $request): array
    {
        if ($request->route('code') && $request->routeIs('affiliate.page')) {
            return [strtoupper((string) $request->route('code')), true];
        }
        if ($request->query('aff')) {
            // &w=1: a product shared by the affiliate from their own price page.
            return [strtoupper(substr((string) $request->query('aff'), 0, 32)), $request->query('w') === '1'];
        }
        $cookie = (string) ($request->hasSession() ? $request->session()->get(self::COOKIE) : null) ?: (string) $request->cookie(self::COOKIE);
        if ($cookie !== '' && str_contains($cookie, '|')) {
            [$code, $mode] = explode('|', $cookie, 2);

            return [strtoupper($code), $mode === 'w'];
        }

        return [null, false];
    }
}
