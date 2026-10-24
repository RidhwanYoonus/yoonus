<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Behind Cloudflare the server sees Cloudflare's address (e.g. 104.23.x.x), not the customer's. When the request
 * really comes from Cloudflare, use the customer's address Cloudflare passes on (CF-Connecting-IP). A visitor
 * cannot fake it: the header is only believed when the connection comes from one of Cloudflare's own addresses.
 */
class CloudflareClientIp
{
    /** Cloudflare's published ranges (cloudflare.com/ips). */
    public const RANGES = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
        '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $real = (string) $request->headers->get('CF-Connecting-IP');
        if ($real !== '' && filter_var($real, FILTER_VALIDATE_IP) && IpUtils::checkIp((string) $request->ip(), self::RANGES)) {
            $request->server->set('REMOTE_ADDR', $real);
            $request->headers->remove('X-Forwarded-For');
            $request->server->remove('HTTP_X_FORWARDED_FOR');
        }

        return $next($request);
    }
}
