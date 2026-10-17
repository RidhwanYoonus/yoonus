<?php

namespace App\Pricing;

/**
 * All money maths runs on integer fils (1 AED = 100 fils) so totals are exact.
 */
final class Money
{
    public static function toFils(string|int|float|null $aed): int
    {
        return self::scaled($aed, 2);
    }

    public static function toAed(int $fils): string
    {
        $sign = $fils < 0 ? '-' : '';
        $fils = abs($fils);

        return $sign.intdiv($fils, 100).'.'.str_pad((string) ($fils % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function format(int $fils, ?string $locale = null): string
    {
        $amount = number_format($fils / 100, 2, '.', ',');
        $locale ??= app()->getLocale();

        return $locale === 'ar' ? $amount.' د.إ' : 'AED '.$amount;
    }

    /** Decimal string → integer at the given scale, e.g. ("5.000", 3) → 5000. */
    public static function scaled(string|int|float|null $value, int $scale): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_string($value) && preg_match('/^(-)?(\d*)(?:\.(\d*))?$/', trim($value), $m)) {
            $int = (int) ($m[2] ?: 0);
            $frac = $m[3] ?? '';
            $kept = (int) str_pad(substr($frac, 0, $scale), $scale, '0');
            $result = $int * 10 ** $scale + $kept;
            if (strlen($frac) > $scale && (int) $frac[$scale] >= 5) {
                $result++;
            }

            return ($m[1] ?? '') === '-' ? -$result : $result;
        }

        return (int) round((float) $value * 10 ** $scale);
    }

    /** Integer division rounded half away from zero. */
    public static function divRound(int $numerator, int $denominator): int
    {
        $q = intdiv($numerator, $denominator);
        $r = $numerator % $denominator;
        if (abs($r) * 2 >= abs($denominator)) {
            $q += ($numerator < 0) !== ($denominator < 0) ? -1 : 1;
        }

        return $q;
    }

    /** Apply basis points (1% = 100 bp) to an amount in fils. */
    public static function percentOf(int $fils, int $basisPoints): int
    {
        return self::divRound($fils * $basisPoints, 10000);
    }
}
