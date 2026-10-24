<?php

namespace App\Support;

/**
 * Tidies the capitals in English product text as it is saved:
 * "GOLD RING WITH diamond 18k" → "Gold Ring with Diamond 18K"; descriptions get sentence case.
 */
class TextStyle
{
    /** Always written in capitals. */
    private const ACRONYMS = ['gia', 'igi', 'hrd', 'idt', 'agl', 'ags', 'egl', 'gra', 'vvs', 'vvs1', 'vvs2', 'vs', 'vs1', 'vs2', 'si', 'si1', 'si2', 'i1', 'cz', 'uae', 'aed', 'vat', 'usa', 'uk', 'dgcx', 'pvd', 'ip', 'tv', 'ksa', 'pcs'];

    /** Kept lower-case inside a name ("Ring with Diamond"). */
    private const SMALL = ['a', 'an', 'and', 'as', 'at', 'by', 'for', 'from', 'in', 'into', 'of', 'on', 'or', 'per', 'the', 'to', 'with', 'x'];

    public static function enabled(): bool
    {
        return Settings::bool('product_text_style', true);
    }

    public static function name(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return $text;
        }
        $words = preg_split('/\s+/u', trim($text));
        foreach ($words as $i => $word) {
            $words[$i] = implode('-', array_map(fn ($part) => self::word($part, $i === 0), explode('-', $word)));
        }

        return implode(' ', $words);
    }

    public static function description(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return $text;
        }
        // Typed in capitals? Start from lower case.
        $letters = preg_replace('/[^\p{L}]/u', '', $text);
        $upper = preg_replace('/[^\p{Lu}]/u', '', $text);
        if (mb_strlen($letters) >= 8 && mb_strlen($upper) / mb_strlen($letters) > 0.6) {
            $text = mb_strtolower($text);
        }
        $lines = array_map(function ($line) {
            $line = preg_replace('/[ \t]+/u', ' ', trim($line));
            $line = preg_replace('/\s+([,.;:!?])/u', '$1', $line);
            $line = preg_replace('/([,;:!?])(?=\p{L})/u', '$1 ', $line);
            $line = preg_replace('/(?<=\p{L}),(?=\d)/u', ', ', $line);   // "diamond,0.5ct" but not "1,000"
            $line = preg_replace('/\.(?=\p{L}{2,})/u', '. ', $line);
            $line = preg_replace_callback('/\b(\p{L}[\p{L}\d]*|\d+(?:k|kt|K|KT))\b/u', fn ($m) => self::fixed($m[1]) ?? $m[1], $line);
            $line = preg_replace('/\bi\b/u', 'I', $line);
            // Capital letter at the start of every sentence.
            return preg_replace_callback('/(^|[.!?]\s+)(\p{Ll})/u', fn ($m) => $m[1].mb_strtoupper($m[2]), $line);
        }, preg_split('/\R/u', trim($text)));

        return implode("\n", $lines);
    }

    /** Fixed spellings: acronyms in capitals, karats as 18K / 21KT; null when the word has no fixed form. */
    private static function fixed(string $word): ?string
    {
        $lower = mb_strtolower($word);
        if (in_array($lower, self::ACRONYMS, true)) {
            return mb_strtoupper($word);
        }
        if (preg_match('/^\d+(k|kt)$/', $lower)) {
            return mb_strtoupper($word);
        }
        if (preg_match('/^\d+(?:\.\d+)?(ct|mm|cm|g|gm|gms)$/', $lower)) {
            return $lower;
        }

        return null;
    }

    private static function word(string $word, bool $first): string
    {
        if ($word === '') {
            return $word;
        }
        // Keep the letters around a word (brackets, quotes) and style the word itself.
        if (! preg_match('/^([^\p{L}\d]*)(.*?)([^\p{L}\d]*)$/u', $word, $m) || $m[2] === '') {
            return $word;
        }
        [, $lead, $core, $tail] = $m;
        if (($fixed = self::fixed($core)) !== null) {
            return $lead.$fixed.$tail;
        }
        // Deliberate mixed case such as "McLaren" or "iPhone" stays as typed.
        if (preg_match('/\p{Ll}/u', $core) && preg_match('/.\p{Lu}/u', $core)) {
            return $word;
        }
        $lower = mb_strtolower($core);
        if (! $first && in_array($lower, self::SMALL, true)) {
            return $lead.$lower.$tail;
        }

        return $lead.mb_strtoupper(mb_substr($lower, 0, 1)).mb_substr($lower, 1).$tail;
    }
}
