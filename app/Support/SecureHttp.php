<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTPS calls that survive Windows PHP installs without a CA list. Tries certificate setups in turn
 * (Windows store + bundled Mozilla list, Windows store, bundled list, PHP default) and remembers the
 * first that connects. Certificates are always verified.
 */
class SecureHttp
{
    private const CACHE_KEY = 'secure_http.working_setup';

    private const DOWN_KEY = 'secure_http.down';

    /** @return array<string,array> */
    public static function setups(): array
    {
        $bundle = resource_path('certs/cacert.pem');
        $native = defined('CURLSSLOPT_NATIVE_CA') ? [CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA] : null;

        return array_filter([
            'windows store + bundled list' => $native ? ['verify' => $bundle, 'curl' => $native] : null,
            'windows store' => $native ? ['verify' => true, 'curl' => $native] : null,
            'bundled list' => ['verify' => $bundle],
            'php default' => ['verify' => true],
        ]);
    }

    /** @param callable(PendingRequest): mixed $send */
    /** True for 10 minutes after every setup failed, so callers skip straight to their fallback. */
    public static function isDown(): bool
    {
        return (bool) Cache::get(self::DOWN_KEY);
    }

    public static function send(callable $send): mixed
    {
        if (self::isDown()) {
            throw new RuntimeException('Secure connection failed — it did not work a few minutes ago, so it was skipped.');
        }
        $setups = self::setups();
        $remembered = Cache::get(self::CACHE_KEY);
        if ($remembered && isset($setups[$remembered])) {
            $setups = [$remembered => $setups[$remembered]] + $setups;
        }

        $errors = [];
        foreach ($setups as $label => $options) {
            try {
                $result = $send(Http::connectTimeout(4)->timeout(8)->withOptions($options));
                Cache::forever(self::CACHE_KEY, $label);

                return $result;
            } catch (ConnectionException $e) {
                $errors[] = $label.': '.self::short($e->getMessage());
            }
        }

        Cache::put(self::DOWN_KEY, true, now()->addMinutes(10));
        throw new RuntimeException('Secure connection failed — '.implode(' | ', $errors));
    }

    private static function short(string $message): string
    {
        return preg_match('/cURL error \d+: [^(]+/', $message, $m) ? trim($m[0]) : mb_substr($message, 0, 120);
    }
}
