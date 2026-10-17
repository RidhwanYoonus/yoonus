<?php

namespace App\Pricing;

use App\Models\GoldCategory;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Reads the day's Dubai gold rates (AED per gram) from a public rates page,
 * by default https://dubaicityofgold.com. The page is parsed as text, so it
 * copes with tables, cards or plain text like "24K 386.75" / "22 Carat: AED 358.00".
 */
class WebsiteGoldRates
{
    public const KARATS = [24, 22, 21, 18];

    /** Karats to look for: every active karat set up in the Gold rate manager (e.g. 24, 22, 21, 18, 14). */
    public static function karats(): array
    {
        $karats = GoldCategory::where('is_active', true)->pluck('karat')->map(fn ($k) => (int) $k)->filter()->unique()->sortDesc()->values()->all();

        return $karats ?: self::KARATS;
    }

    public static function url(): string
    {
        return (string) Settings::get('gold_rate_source_url', 'https://dubaicityofgold.com/');
    }

    /** @return array<string,string> e.g. ['24K' => '386.75', '22K' => '358.00'] */
    public function fetch(?string $url = null): array
    {
        $url ??= self::url();
        $html = $this->download($url);

        $rates = $this->parse($html);
        if (! isset($rates['24K']) && ! isset($rates['22K'])) {
            throw new RuntimeException("No gold rates found on {$url}. The page layout may have changed or the rates load with JavaScript — send the page source (Ctrl+U) to your developer.");
        }

        return $rates;
    }

    /**
     * Try the Windows/OS certificate store first (the one Chrome trusts, including antivirus HTTPS
     * scanners), then the Mozilla list shipped in resources/certs. Both verify the certificate.
     */
    private function download(string $url): string
    {
        $attempts = [];
        if (defined('CURLSSLOPT_NATIVE_CA')) {
            $attempts['system certificate store'] = ['verify' => true, 'curl' => [CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA]];
        }
        $attempts['bundled certificate list'] = ['verify' => resource_path('certs/cacert.pem')];

        $errors = [];
        foreach ($attempts as $label => $options) {
            try {
                return Http::timeout(20)
                    ->withOptions($options)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36',
                        'Accept' => 'text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8',
                        'Accept-Language' => 'en-AE,en;q=0.9',
                    ])
                    ->get($url)->throw()->body();
            } catch (Throwable $e) {
                $errors[] = "{$label}: ".$e->getMessage();
            }
        }

        throw new RuntimeException("Could not open {$url} (".implode(' | ', $errors).')');
    }

    /** @return array<string,string> */
    public function parse(string $html, ?array $karats = null): array
    {
        $karats ??= self::karats();
        // First the visible text, then (for pages that ship rates as JSON in a script) everything.
        $visible = $this->toText(preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', $html));
        $rates = $this->extract($visible, $karats);
        if (count($rates) < 2) {
            $rates += $this->extract($this->toText($html), $karats);
        }

        return $this->sane($rates);
    }

    private function toText(string $html): string
    {
        $text = html_entity_decode(strip_tags(preg_replace('/<[^>]+>/', ' $0 ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strtr($text, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.']);

        return preg_replace('/\s+/u', ' ', $text);
    }

    private function extract(string $text, array $karats): array
    {
        $rates = [];
        foreach ($karats as $k) {
            // "24K", "24 KT", "24 Karat", "24 Carat", "24ct", "Gold 24", "\"gold24\":" … then the first price-like number.
            $label = "(?:(?<![\\d.]){$k}\\s*(?:k\\b|kt\\b|karat|carat|ct\\b|ق|عيار)|gold[_\\s-]?{$k}(?!\\d)|عيار\\s*{$k}(?!\\d))";
            $pattern = "/{$label}[^\\d]{0,80}?(\\d{2,4}(?:,\\d{3})*(?:\\.\\d{1,3})?)/iu";
            if (preg_match_all($pattern, $text, $m)) {
                foreach ($m[1] as $raw) {
                    $value = (float) str_replace(',', '', $raw);
                    if ($value >= 50 && $value <= 2000) {   // plausible AED per gram
                        $rates["{$k}K"] = number_format($value, 2, '.', '');
                        break;
                    }
                }
            }
        }

        return $rates;
    }

    /** Drop values that break the karat ordering (24K > 22K > 21K > 18K) or purity ratio. */
    private function sane(array $rates): array
    {
        $base = isset($rates['24K']) ? (float) $rates['24K'] : (isset($rates['22K']) ? (float) $rates['22K'] * 24 / 22 : null);
        if (! $base) {
            return $rates;
        }

        return array_filter($rates, function ($value, $karat) use ($base) {
            $expected = $base * ((int) $karat) / 24;

            return abs((float) $value - $expected) / $expected <= 0.15;
        }, ARRAY_FILTER_USE_BOTH);
    }
}
