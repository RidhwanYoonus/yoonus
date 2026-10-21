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
        // Shop wording: labels on products and banners
        'exclusive product' => 'منتج حصري', 'exclusive products' => 'منتجات حصرية', 'exclusive item' => 'قطعة حصرية', 'exclusive items' => 'قطع حصرية',
        'exclusive' => 'حصري', 'exclusive design' => 'تصميم حصري', 'exclusive designs' => 'تصاميم حصرية', 'exclusive collection' => 'مجموعة حصرية',
        'special product' => 'منتج مميز', 'special products' => 'منتجات مميزة', 'special item' => 'قطعة مميزة', 'special items' => 'قطع مميزة',
        'special offer' => 'عرض خاص', 'special offers' => 'عروض خاصة', 'special edition' => 'إصدار خاص', 'limited edition' => 'إصدار محدود',
        'item' => 'قطعة', 'items' => 'قطع', 'piece' => 'قطعة', 'pieces' => 'قطع', 'product' => 'منتج', 'products' => 'منتجات',
        'one piece' => 'قطعة واحدة', 'single piece' => 'قطعة واحدة', 'only one piece' => 'قطعة واحدة فقط', 'unique piece' => 'قطعة فريدة', 'unique pieces' => 'قطع فريدة',
        'royal design' => 'تصميم ملكي', 'royal designs' => 'تصاميم ملكية', 'royal collection' => 'المجموعة الملكية', 'royal' => 'ملكي',
        'luxury' => 'فاخر', 'luxury collection' => 'المجموعة الفاخرة', 'premium' => 'فاخر', 'premium collection' => 'المجموعة المميزة',
        'handmade' => 'مصنوع يدوياً', 'handcrafted' => 'مصنوع يدوياً', 'hand made' => 'مصنوع يدوياً',
        'new' => 'جديد', 'new collection' => 'مجموعة جديدة', 'latest designs' => 'أحدث التصاميم', 'trending' => 'رائج', 'popular' => 'الأكثر طلباً',
        'featured' => 'مختارات', 'top picks' => 'أفضل الاختيارات', 'daily wear' => 'للاستخدام اليومي', 'party wear' => 'للحفلات', 'light weight' => 'خفيف الوزن', 'lightweight' => 'خفيف الوزن',
        'classic' => 'كلاسيكي', 'traditional' => 'تقليدي', 'modern' => 'عصري', 'antique' => 'أنتيك', 'vintage' => 'كلاسيكي قديم', 'elegant' => 'أنيق',
        'arabic design' => 'تصميم عربي', 'italian design' => 'تصميم إيطالي', 'italian gold' => 'ذهب إيطالي', 'turkish design' => 'تصميم تركي',
        'indian design' => 'تصميم هندي', 'bahraini design' => 'تصميم بحريني', 'kuwaiti design' => 'تصميم كويتي', 'emirati design' => 'تصميم إماراتي',
        'gift for her' => 'هدية لها', 'gift for him' => 'هدية له', 'eid collection' => 'مجموعة العيد', 'ramadan collection' => 'مجموعة رمضان',
        'sold' => 'تم البيع', 'sold out' => 'نفدت الكمية', 'in stock' => 'متوفر', 'out of stock' => 'غير متوفر', 'coming soon' => 'قريباً',
        // Pieces and styles
        'kada' => 'كادا', 'kadas' => 'كادات', 'jhumka' => 'جمكا', 'jhumkas' => 'جمكات', 'jhumki' => 'جمكا',
        'twisted bangle pair' => 'زوج أساور ملتوية', 'bangle pair' => 'زوج أساور', 'pair of bangles' => 'زوج أساور', 'kada bangle' => 'إسورة كادا',
        'choker' => 'شوكر', 'chokers' => 'شوكر', 'locket' => 'قلادة صورة', 'lockets' => 'قلادات صور', 'mangalsutra' => 'منجلسوترا',
        'maang tikka' => 'تيكا للرأس', 'tikka' => 'تيكا', 'nath' => 'زمام', 'haar' => 'عقد هندي', 'rani haar' => 'عقد راني هار',
        'necklace set' => 'طقم قلادة', 'necklace sets' => 'أطقم قلادات', 'half set' => 'نصف طقم', 'full set' => 'طقم كامل',
        'toe ring' => 'خاتم إصبع القدم', 'toe rings' => 'خواتم أصابع القدم', 'signet ring' => 'خاتم ختم', 'solitaire' => 'سوليتير', 'solitaire ring' => 'خاتم سوليتير',
        'eternity ring' => 'خاتم إيترنيتي', 'band' => 'دبلة', 'bands' => 'دبل', 'band ring' => 'دبلة', 'couple rings' => 'خواتم أزواج', 'couple ring' => 'خاتم أزواج',
        'stud earrings' => 'أقراط صغيرة', 'studs' => 'أقراط صغيرة', 'hoop earrings' => 'أقراط حلقية', 'hoops' => 'أقراط حلقية',
        'drop earrings' => 'أقراط متدلية', 'chandelier earrings' => 'أقراط ثريا', 'ear cuff' => 'كفة أذن', 'ear cuffs' => 'كفات أذن',
        'tennis bracelet' => 'سوار تنس', 'charm bracelet' => 'سوار بدلايات', 'cuff' => 'سوار مفتوح', 'cuff bracelet' => 'سوار مفتوح',
        'rope chain' => 'سلسلة حبل', 'box chain' => 'سلسلة صندوق', 'curb chain' => 'سلسلة كارتير', 'figaro chain' => 'سلسلة فيغارو', 'cuban chain' => 'سلسلة كوبية',
        'bead' => 'خرزة', 'beads' => 'خرز', 'filigree' => 'مخرمات', 'enamel' => 'مينا', 'meenakari' => 'مينا ملونة', 'kundan' => 'كوندان', 'polki' => 'بولكي',
        'hamsa' => 'خمسة', 'evil eye' => 'عين زرقاء', 'allah pendant' => 'دلاية لفظ الجلالة', 'name necklace' => 'قلادة بالاسم', 'name pendant' => 'دلاية بالاسم',
        'initial pendant' => 'دلاية حرف', 'heart pendant' => 'دلاية قلب', 'cross pendant' => 'دلاية صليب', 'baby bangle' => 'إسورة أطفال', 'baby bangles' => 'أساور أطفال',
        'watch' => 'ساعة', 'watches' => 'ساعات', 'gold watch' => 'ساعة ذهب', 'tiara' => 'تاج', 'crown' => 'تاج', 'belt' => 'حزام', 'gold belt' => 'حزام ذهب',
        // Metal and stones
        'plain gold' => 'ذهب سادة', 'yellow gold' => 'ذهب أصفر', 'two tone' => 'لونين', 'two-tone' => 'لونين', 'tri color' => 'ثلاثة ألوان', 'three tone' => 'ثلاثة ألوان',
        '24k gold' => 'ذهب عيار 24', '22k gold' => 'ذهب عيار 22', '21k gold' => 'ذهب عيار 21', '18k gold' => 'ذهب عيار 18', '14k gold' => 'ذهب عيار 14',
        'natural diamond' => 'ألماس طبيعي', 'natural diamonds' => 'ألماس طبيعي', 'lab grown diamond' => 'ألماس مصنّع في المختبر', 'lab-grown diamond' => 'ألماس مصنّع في المختبر',
        'lab grown diamonds' => 'ألماس مصنّع في المختبر', 'lab-grown diamonds' => 'ألماس مصنّع في المختبر', 'certified diamond' => 'ألماس معتمد', 'polki diamond' => 'ألماس بولكي',
        'cubic zirconia' => 'زركون', 'zircon' => 'زركون', 'cz' => 'زركون', 'crystal' => 'كريستال', 'opal' => 'أوبال', 'topaz' => 'توباز', 'amethyst' => 'جمشت',
        'turquoise' => 'فيروز', 'coral' => 'مرجان', 'onyx' => 'عقيق أسود', 'agate' => 'عقيق', 'garnet' => 'عقيق أحمر', 'mother of pearl' => 'صدف',
        'hallmarked' => 'مدموغ', 'certified' => 'معتمد', 'certificate' => 'شهادة', 'with certificate' => 'مع شهادة',
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
