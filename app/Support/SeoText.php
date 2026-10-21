<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Writes search-engine titles and descriptions (English and Arabic) from a product's or category's details.
 * No prices: a saved text would go out of date when the gold rate moves.
 */
class SeoText
{
    public const FIELDS = ['meta_title', 'meta_description', 'meta_title_ar', 'meta_description_ar'];

    /** @return array{meta_title:string, meta_description:string, meta_title_ar:string, meta_description_ar:string} */
    public static function forProduct(Product $p): array
    {
        $p->loadMissing('goldCategory', 'category', 'certificateLab');

        return [
            'meta_title' => self::productTitle($p, 'en'), 'meta_description' => self::productDescription($p, 'en'),
            'meta_title_ar' => self::productTitle($p, 'ar'), 'meta_description_ar' => self::productDescription($p, 'ar'),
        ];
    }

    public static function forCategory(Category $c): array
    {
        $karats = \App\Models\GoldCategory::whereIn('id', Product::active()->where('category_id', $c->id)->whereNotNull('gold_category_id')->select('gold_category_id'))
            ->orderByDesc('karat')->pluck('name')->all();
        $diamonds = Product::active()->where('category_id', $c->id)->whereNotNull('diamond_origin')->distinct()->pluck('diamond_origin')->all();
        $out = [];
        foreach (['en' => '', 'ar' => '_ar'] as $locale => $suffix) {
            $name = self::pick($c->name, $c->name_ar, $locale);
            $k = $karats ? implode(self::t('list_sep', $locale), array_slice($karats, 0, 4)) : self::t('gold_default_karats', $locale);
            $out['meta_title'.$suffix] = self::fit(self::t('category_title', $locale, ['name' => $name, 'karats' => $k]), 60);
            $out['meta_description'.$suffix] = self::fit(self::t('category_description', $locale, [
                'name' => $name, 'karats' => $k,
                'diamonds' => $diamonds ? self::t(count($diamonds) > 1 ? 'category_both_diamonds' : 'category_'.$diamonds[0], $locale) : '',
                'store' => self::store($locale),
            ]), 158);
        }

        return $out;
    }

    /** Fills only the empty SEO fields. Returns the fields it set. */
    public static function fillBlanks(Product|Category $model, bool $save = true): array
    {
        $texts = $model instanceof Product ? self::forProduct($model) : self::forCategory($model);
        $set = [];
        foreach (self::FIELDS as $field) {
            if (blank($model->{$field}) && filled($texts[$field] ?? null)) {
                $model->{$field} = $texts[$field];
                $set[] = $field;
            }
        }
        if ($save && $set) {
            $model->saveQuietly();
        }

        return $set;
    }

    /** "Fill empty SEO" for the whole shop. @return array{products:int, categories:int} */
    public static function fillAllBlanks(): array
    {
        $n = ['products' => 0, 'categories' => 0];
        $blank = fn ($q) => $q->where(fn ($q) => collect(self::FIELDS)->each(fn ($f) => $q->orWhereNull($f)->orWhere($f, '')));
        Product::with('goldCategory', 'category', 'certificateLab')->tap($blank)->chunkById(200, function ($products) use (&$n) {
            foreach ($products as $p) {
                $n['products'] += (int) (bool) self::fillBlanks($p);
            }
        });
        Category::query()->tap($blank)->each(function (Category $c) use (&$n) {
            $n['categories'] += (int) (bool) self::fillBlanks($c);
        });

        return $n;
    }

    private static function productTitle(Product $p, string $locale): string
    {
        $parts = [self::pick($p->name, $p->name_ar, $locale)];
        if ($p->goldCategory) {
            $parts[] = self::t('gold', $locale, ['karat' => $p->goldCategory->name]);
        }
        if (filled($p->diamond_carat)) {
            $parts[] = self::t('diamond_ct', $locale, ['ct' => self::num($p->diamond_carat), 'origin' => self::origin($p, $locale)]);
        } elseif ($p->diamond_origin) {
            $parts[] = self::origin($p, $locale, true);
        } elseif ((float) $p->weight_grams > 0) {
            $parts[] = self::num($p->weight_grams).' '.self::t('g', $locale);
        }

        return self::fit(preg_replace('/\s+/u', ' ', implode(' – ', array_filter(array_map('trim', $parts)))), 60);
    }

    private static function productDescription(Product $p, string $locale): string
    {
        $name = self::pick($p->name, $p->name_ar, $locale);
        $s = self::t('p_intro', $locale, [
            'name' => $name,
            'karat' => $p->goldCategory?->name ?? '',
            'weight' => (float) $p->weight_grams > 0 ? self::t('p_weight', $locale, ['weight' => self::num($p->weight_grams)]) : '',
        ]);
        if ($p->goldCategory === null) {
            $s = $name;
        }
        if ($p->diamond_origin || filled($p->diamond_carat)) {
            $cuts = ['Excellent' => 'ممتاز', 'Ideal' => 'مثالي', 'Very Good' => 'جيد جداً', 'Good' => 'جيد', 'Fair' => 'مقبول'];
            $grades = array_filter([
                filled($p->diamond_color) ? self::t('color', $locale, ['v' => $p->diamond_color]) : null,
                filled($p->diamond_clarity) ? self::t('clarity', $locale, ['v' => $p->diamond_clarity]) : null,
                filled($p->diamond_cut) ? self::t('cut', $locale, ['v' => $locale === 'ar' ? ($cuts[$p->diamond_cut] ?? $p->diamond_cut) : strtolower($p->diamond_cut)]) : null,
            ]);
            $s .= self::t('p_diamond', $locale, [
                'ct' => filled($p->diamond_carat) ? self::t('ct', $locale, ['ct' => self::num($p->diamond_carat)]) : '',
                'origin' => self::origin($p, $locale, lower: true),
                'grades' => $grades ? self::t('list_sep', $locale).implode(self::t('list_sep', $locale), $grades) : '',
            ]);
            if ($p->certificateLab) {
                $s .= self::t('p_certified', $locale, ['lab' => $p->certificateLab->label()]);
            }
        } elseif (filled($p->stone_type)) {
            $s .= self::t('p_stone', $locale, ['stone' => $p->stone_type]);
        }
        $s .= '. ';
        $about = trim(Str::before(strip_tags((string) self::pick($p->description, $p->description_ar, $locale)), '.'));
        if ($about !== '' && mb_strlen($s.$about) < 110 && ! str_contains(mb_strtolower($s), mb_strtolower(Str::limit($about, 20, '')))) {
            $s .= $about.'. ';
        }
        // The closing line, shortened (or left out) so the description is not cut mid-sentence.
        $category = $p->category ? self::pick($p->category->name, $p->category->name_ar, $locale) : self::t('jewellery', $locale);
        $category = $locale === 'en' ? mb_strtolower($category) : $category;
        foreach (['p_close', 'p_close_short', null] as $key) {
            $close = $key ? self::t($key, $locale, ['store' => self::store($locale), 'category' => $category]) : '';
            if (mb_strlen(preg_replace('/\s+/u', ' ', $s.$close)) <= 158) {
                break;
            }
        }
        $s = rtrim($s).' '.$close;

        return self::fit(preg_replace('/\s+/u', ' ', $s), 158);
    }

    private static function origin(Product $p, string $locale, bool $long = false, bool $lower = false): string
    {
        $text = match ($p->diamond_origin) {
            'natural' => self::t($long ? 'natural_diamond' : 'natural', $locale),
            'lab_grown' => self::t($long ? 'lab_diamond' : 'lab', $locale),
            default => self::t('diamond', $locale),
        };

        return $lower && $locale === 'en' ? mb_strtolower($text) : $text;
    }

    private static function t(string $key, string $locale, array $replace = []): string
    {
        return (string) __('shop.seo.auto.'.$key, $replace, $locale);
    }

    private static function pick(?string $en, ?string $ar, string $locale): string
    {
        return (string) ($locale === 'ar' && filled($ar) ? $ar : $en);
    }

    private static function store(string $locale): string
    {
        return (string) ($locale === 'ar' ? (Settings::get('store_name_ar') ?: Settings::get('store_name')) : Settings::get('store_name'));
    }

    private static function num($v): string
    {
        return rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
    }

    /** Cut at a word boundary so the text never ends mid-word. */
    private static function fit(string $text, int $max): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space > $max * 0.6 ? mb_substr($cut, 0, $space) : $cut, " ,.–-|·");
    }
}
