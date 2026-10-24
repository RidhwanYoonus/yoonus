<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Where an order was placed from (city, region, country), by the customer's internet address.
 * Cloudflare's own location headers first (free, instant); otherwise a free look-up (ipapi.co), kept for a day.
 * An estimate only: VPNs and mobile networks can show another city.
 */
class IpLocation
{
    /** Location from the request headers Cloudflare adds (country always; city/region with "Add visitor location headers"). */
    public static function fromHeaders(Request $request): array
    {
        $h = fn (string $k) => ($v = trim((string) $request->headers->get($k))) !== '' && strtoupper($v) !== 'XX' && strtoupper($v) !== 'T1' ? $v : null;

        return array_filter(['country' => ($c = $h('CF-IPCountry')) ? strtoupper($c) : null, 'region' => $h('cf-region'), 'city' => $h('cf-ipcity')]);
    }

    public static function lookup(string $ip): array
    {
        if (! Settings::bool('ip_lookup', true) || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return [];
        }

        return Cache::remember('ip_location:'.$ip, now()->addDay(), function () use ($ip) {
            try {
                $res = SecureHttp::send(fn ($http) => $http->timeout(4)->withHeaders(['User-Agent' => 'shop'])->get('https://ipapi.co/'.$ip.'/json/'));
                $j = $res->successful() ? $res->json() : [];
            } catch (\Throwable) {
                return [];
            }
            if (! empty($j['error'])) {
                return [];
            }

            return array_filter(['country' => isset($j['country_code']) ? strtoupper($j['country_code']) : null, 'region' => $j['region'] ?? null, 'city' => $j['city'] ?? null]);
        });
    }

    /** Saves the place on the order. Headers now; the look-up after the page has been sent (no wait at checkout). */
    public static function recordFor(\App\Models\Order $order, Request $request): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('orders', 'order_ip')) {
            return;
        }
        $ip = (string) $request->ip();
        $place = self::fromHeaders($request);
        $order->forceFill(['order_ip' => mb_substr($ip, 0, 45), 'order_country' => $place['country'] ?? null, 'order_region' => $place['region'] ?? null, 'order_city' => $place['city'] ?? null])->save();
        if (empty($place['city'])) {
            app()->terminating(function () use ($order, $ip, $place) {
                $found = self::lookup($ip);
                if ($found) {
                    $order->forceFill(['order_country' => $place['country'] ?? $found['country'] ?? null, 'order_region' => $found['region'] ?? null, 'order_city' => $found['city'] ?? null])->saveQuietly();
                }
            });
        }
    }

    public static function countryName(?string $code): ?string
    {
        if (! $code) {
            return null;
        }

        return class_exists(\Locale::class) ? (\Locale::getDisplayRegion('-'.$code, 'en') ?: $code) : $code;
    }

    /** "Sharjah, Sharjah, United Arab Emirates" (no repeats). */
    public static function label(\App\Models\Order $order): ?string
    {
        $parts = array_values(array_unique(array_filter([$order->order_city ?? null, $order->order_region ?? null, self::countryName($order->order_country ?? null)])));

        return $parts ? implode(', ', $parts) : null;
    }
}
