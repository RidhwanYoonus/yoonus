<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * English → Arabic for the admin "Translate from English" buttons.
 * Google Cloud Translation or DeepL when a key is set in Settings; otherwise the free
 * MyMemory service (no account, a few thousand characters a day).
 */
class Translator
{
    public static function provider(): string
    {
        $choice = (string) Settings::get('translation_provider', 'auto');

        return match (true) {
            $choice === 'google' || ($choice === 'auto' && Settings::get('google_translate_key')) => 'google',
            $choice === 'deepl' || ($choice === 'auto' && Settings::get('deepl_key')) => 'deepl',
            default => 'mymemory',
        };
    }

    public static function toArabic(?string $english): string
    {
        $text = trim((string) $english);
        if ($text === '') {
            throw new RuntimeException('Type the English text first.');
        }

        try {
            return Cache::remember('tr.ar.'.self::provider().'.'.md5($text), now()->addDays(30), fn () => match (self::provider()) {
                'google' => self::google($text),
                'deepl' => self::deepl($text),
                default => self::myMemory($text),
            });
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            $reason = str_contains($e->getMessage(), 'cURL error 60') || str_contains($e->getMessage(), 'SSL')
                ? 'secure connection (SSL certificate) problem on this PC'
                : mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 160);
            throw new RuntimeException('Could not reach the translation service ('.$reason.').', 0, $e);
        }
    }

    private static function google(string $text): string
    {
        $key = Settings::get('google_translate_key') ?: throw new RuntimeException('Add a Google Translate API key in Settings → Store & tax.');
        $res = Http::timeout(15)->asForm()->post('https://translation.googleapis.com/language/translate/v2?key='.urlencode($key), [
            'q' => $text, 'source' => 'en', 'target' => 'ar', 'format' => 'text',
        ]);
        self::fail($res, $res->json('error.message'));

        return html_entity_decode((string) $res->json('data.translations.0.translatedText'), ENT_QUOTES);
    }

    private static function deepl(string $text): string
    {
        $key = (string) Settings::get('deepl_key') ?: throw new RuntimeException('Add a DeepL API key in Settings → Store & tax.');
        $host = str_ends_with($key, ':fx') ? 'https://api-free.deepl.com' : 'https://api.deepl.com';
        $res = Http::timeout(15)->withHeaders(['Authorization' => 'DeepL-Auth-Key '.$key])
            ->post($host.'/v2/translate', ['text' => [$text], 'source_lang' => 'EN', 'target_lang' => 'AR']);
        self::fail($res, $res->json('message'));

        return (string) $res->json('translations.0.text');
    }

    /** Free service: up to 500 characters per call, so long descriptions go line by line. */
    private static function myMemory(string $text): string
    {
        $lines = preg_split('/\R/', $text);
        foreach ($lines as $n => $line) {
            if (trim($line) === '') {
                continue;
            }
            $lines[$n] = implode(' ', array_map(fn ($chunk) => self::myMemoryCall($chunk), self::chunks($line, 450)));
        }

        return implode("\n", $lines);
    }

    private static function myMemoryCall(string $chunk): string
    {
        $res = Http::timeout(15)->get('https://api.mymemory.translated.net/get', array_filter([
            'q' => $chunk, 'langpair' => 'en|ar', 'de' => Settings::get('mymemory_email') ?: Settings::get('store_email'),
        ]));
        self::fail($res, $res->json('responseDetails'));
        if ((int) $res->json('responseStatus') !== 200) {
            throw new RuntimeException('Free translator: '.($res->json('responseDetails') ?: 'not available right now').' — try again later or add a Google/DeepL key.');
        }

        return html_entity_decode((string) $res->json('responseData.translatedText'), ENT_QUOTES);
    }

    private static function chunks(string $line, int $max): array
    {
        if (mb_strlen($line) <= $max) {
            return [$line];
        }
        $out = [];
        $buf = '';
        foreach (preg_split('/(?<=[.!?;,])\s+/', $line) as $sentence) {
            if ($buf !== '' && mb_strlen($buf.' '.$sentence) > $max) {
                $out[] = $buf;
                $buf = '';
            }
            $buf = trim($buf.' '.$sentence);
        }

        return array_merge($out, $buf !== '' ? str_split($buf, $max) : []);
    }

    private static function fail($res, ?string $reason): void
    {
        if ($res->failed()) {
            throw new RuntimeException('Translation failed: '.($reason ?: 'HTTP '.$res->status()));
        }
    }
}
