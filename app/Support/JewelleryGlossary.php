<?php

namespace App\Support;

/**
 * Fixed Arabic for common jewellery-shop words and names. Free machine translators often pick
 * the wrong sense for short labels ("Rings" → phone calls), so exact matches are answered here
 * first. Shop owners add or override entries in Settings → Store & tax (one "English = Arabic" per line).
 */
class JewelleryGlossary
{
    public const BUILT_IN = [
        'ring' => 'خاتم', 'rings' => 'خواتم',
        'necklace' => 'قلادة', 'necklaces' => 'قلائد',
        'bangle' => 'إسورة', 'bangles' => 'أساور',
        'bracelet' => 'سوار', 'bracelets' => 'أساور',
        'earring' => 'قرط', 'earrings' => 'أقراط',
        'pendant' => 'دلاية', 'pendants' => 'دلايات',
        'set' => 'طقم', 'sets' => 'أطقم', 'jewellery set' => 'طقم مجوهرات', 'jewelry set' => 'طقم مجوهرات',
        'chain' => 'سلسلة', 'chains' => 'سلاسل',
        'anklet' => 'خلخال', 'anklets' => 'خلاخيل',
        'nose pin' => 'زمام', 'nose ring' => 'زمام',
        'brooch' => 'بروش', 'cufflinks' => 'أزرار أكمام',
        'coin' => 'عملة ذهبية', 'coins' => 'عملات ذهبية', 'gold coin' => 'عملة ذهبية', 'gold coins' => 'عملات ذهبية',
        'gold bar' => 'سبيكة ذهب', 'gold bars' => 'سبائك ذهب', 'bar' => 'سبيكة', 'bars' => 'سبائك',
        'gold' => 'ذهب', 'silver' => 'فضة', 'platinum' => 'بلاتين', 'white gold' => 'ذهب أبيض', 'rose gold' => 'ذهب وردي',
        'diamond' => 'ألماس', 'diamonds' => 'ألماس', 'pearl' => 'لؤلؤ', 'pearls' => 'لؤلؤ',
        'emerald' => 'زمرد', 'ruby' => 'ياقوت', 'sapphire' => 'ياقوت أزرق', 'gemstones' => 'أحجار كريمة', 'gemstone' => 'حجر كريم',
        'jewellery' => 'مجوهرات', 'jewelry' => 'مجوهرات', 'fine jewellery' => 'مجوهرات راقية',
        'gold jewellery' => 'مجوهرات ذهبية', 'diamond jewellery' => 'مجوهرات ألماس',
        'bridal' => 'مجوهرات العروس', 'bridal set' => 'طقم عروس', 'bridal sets' => 'أطقم عروس', 'wedding' => 'زفاف', 'engagement' => 'خطوبة',
        'wedding ring' => 'خاتم زواج', 'wedding rings' => 'خواتم زواج', 'engagement ring' => 'خاتم خطوبة', 'engagement rings' => 'خواتم خطوبة',
        'men' => 'رجالي', 'mens' => 'رجالي', "men's" => 'رجالي', 'women' => 'نسائي', "women's" => 'نسائي', 'kids' => 'أطفال', 'children' => 'أطفال',
        'gift cards' => 'بطاقات الهدايا', 'gift card' => 'بطاقة هدايا', 'gifts' => 'هدايا', 'new arrivals' => 'وصل حديثاً',
        'best sellers' => 'الأكثر مبيعاً', 'offers' => 'العروض', 'sale' => 'تخفيضات', 'collections' => 'المجموعات', 'collection' => 'مجموعة',
        'store pickup' => 'الاستلام من المتجر', 'home delivery' => 'التوصيل للمنزل',
        'dubai' => 'دبي', 'abu dhabi' => 'أبوظبي', 'sharjah' => 'الشارقة', 'ajman' => 'عجمان', 'umm al quwain' => 'أم القيوين',
        'ras al khaimah' => 'رأس الخيمة', 'fujairah' => 'الفجيرة', 'al ain' => 'العين', 'other emirates' => 'الإمارات الأخرى', 'gold souk' => 'سوق الذهب',
    ];

    /** @return array<string,string> lower-case English => Arabic, owner entries last so they win. */
    public static function all(): array
    {
        $custom = [];
        foreach (preg_split('/\R/u', (string) Settings::get('translation_glossary', '')) as $line) {
            if (str_contains($line, '=')) {
                [$en, $ar] = array_map('trim', explode('=', $line, 2));
                if ($en !== '' && $ar !== '') {
                    $custom[self::key($en)] = $ar;
                }
            }
        }

        return array_merge(self::BUILT_IN, $custom);
    }

    public static function key(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', rtrim(trim($text), '.!:;,'))));
    }

    public static function lookup(string $text): ?string
    {
        return self::all()[self::key($text)] ?? null;
    }
}
