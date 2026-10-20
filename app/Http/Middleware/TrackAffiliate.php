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
        } catch (Throwable) {
            // Before the database update has run.
        }
        app(PricingService::class)->setMarkup($context->markup());
        view()->share('affiliateContext', $context);

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

    /** @return array{0: ?string, 1: bool} */
    private function fromRequest(Request $request): array
    {
        if ($request->route('code') && $request->routeIs('affiliate.page')) {
            return [strtoupper((string) $request->route('code')), true];
        }
        if ($request->query('aff')) {
            return [strtoupper(substr((string) $request->query('aff'), 0, 32)), false];
        }
        $cookie = (string) ($request->hasSession() ? $request->session()->get(self::COOKIE) : null) ?: (string) $request->cookie(self::COOKIE);
        if ($cookie !== '' && str_contains($cookie, '|')) {
            [$code, $mode] = explode('|', $cookie, 2);

            return [strtoupper($code), $mode === 'w'];
        }

        return [null, false];
    }
}
