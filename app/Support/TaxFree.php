<?php

namespace App\Support;

/**
 * UAE VAT refund for tourists: VAT is paid as usual; visitors who do not live in the UAE claim it back at
 * departure using the tax-free tag the shop issues (passport needed) when the piece is handed over.
 */
class TaxFree
{
    public static function enabled(): bool
    {
        return Settings::bool('tax_free_enabled', true);
    }

    /** Minimum purchase (incl. VAT, AED) for a tax-free tag. */
    public static function minimum(): float
    {
        return (float) Settings::get('tax_free_min', 250);
    }

    /** Passport nationalities (ISO 3166 alpha-2), UAE left out: residents cannot claim. */
    public static function countries(): array
    {
        $list = ['IN' => 'India', 'PK' => 'Pakistan', 'BD' => 'Bangladesh', 'LK' => 'Sri Lanka', 'NP' => 'Nepal', 'PH' => 'Philippines', 'CN' => 'China',
            'RU' => 'Russia', 'GB' => 'United Kingdom', 'US' => 'United States', 'SA' => 'Saudi Arabia', 'OM' => 'Oman', 'KW' => 'Kuwait', 'QA' => 'Qatar',
            'BH' => 'Bahrain', 'EG' => 'Egypt', 'JO' => 'Jordan', 'LB' => 'Lebanon', 'IQ' => 'Iraq', 'IR' => 'Iran', 'TR' => 'Türkiye', 'DE' => 'Germany',
            'FR' => 'France', 'IT' => 'Italy', 'ES' => 'Spain', 'NL' => 'Netherlands', 'CH' => 'Switzerland', 'SE' => 'Sweden', 'PL' => 'Poland',
            'UA' => 'Ukraine', 'KZ' => 'Kazakhstan', 'UZ' => 'Uzbekistan', 'AF' => 'Afghanistan', 'ID' => 'Indonesia', 'MY' => 'Malaysia', 'SG' => 'Singapore',
            'TH' => 'Thailand', 'VN' => 'Vietnam', 'KR' => 'South Korea', 'JP' => 'Japan', 'AU' => 'Australia', 'NZ' => 'New Zealand', 'CA' => 'Canada',
            'BR' => 'Brazil', 'MX' => 'Mexico', 'ZA' => 'South Africa', 'NG' => 'Nigeria', 'KE' => 'Kenya', 'ET' => 'Ethiopia', 'SD' => 'Sudan',
            'MA' => 'Morocco', 'DZ' => 'Algeria', 'TN' => 'Tunisia', 'SY' => 'Syria', 'YE' => 'Yemen', 'IE' => 'Ireland', 'BE' => 'Belgium', 'AT' => 'Austria',
            'PT' => 'Portugal', 'GR' => 'Greece', 'RO' => 'Romania', 'AZ' => 'Azerbaijan', 'AM' => 'Armenia', 'GE' => 'Georgia'];
        asort($list);

        return $list + ['XX' => 'Other'];
    }

    public static function countryName(?string $code): ?string
    {
        return $code ? (self::countries()[$code] ?? $code) : null;
    }
}
