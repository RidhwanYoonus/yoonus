<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /** Pages with personal data: never kept in the browser cache (shared PCs, back button after logout). */
    private const PRIVATE_PATHS = ['admin', 'admin/*', 'account', 'account/*', 'checkout', 'checkout/*', 'orders/*', 'cart', 'login', 'register', 'video-call/*'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self), usb=(), interest-cohort=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $h->set('X-Permitted-Cross-Domain-Policies', 'none');
        // Blocks clickjacking, <base> hijacking and plugin content without breaking maps, pixels or payment pages.
        $h->set('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; object-src 'none'".($request->isSecure() ? '; upgrade-insecure-requests' : ''));
        $h->remove('X-Powered-By');
        if (function_exists('header_remove') && ! app()->runningUnitTests()) {
            header_remove('X-Powered-By');
        }
        if (($request->is(...self::PRIVATE_PATHS) || $request->user()) && ! $h->hasCacheControlDirective('public')) {
            $h->set('Cache-Control', 'no-store, private, max-age=0');
            $h->set('Pragma', 'no-cache');
        }
        if ($request->isSecure()) {   // browsers only honour HSTS over https
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
