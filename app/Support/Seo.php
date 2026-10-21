<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use App\Pricing\Money;
use Illuminate\Support\Str;

/** Auto-generated meta, Open Graph and Schema.org markup. */
class Seo
{
    public static function storeName(): string
    {
        return app()->getLocale() === 'ar' ? Settings::get('store_name_ar') : Settings::get('store_name');
    }

    public static function page(string $title, ?string $description = null, ?string $image = null, ?string $canonical = null, string $type = 'website', array $jsonLd = []): array
    {
        return [
            'title' => Str::limit($title, 60, '').' | '.self::storeName(),
            'description' => Str::limit($description ?? __('shop.seo.default_description'), 158),
            'image' => $image ?? asset('images/og-default.svg'),
            'canonical' => $canonical ?? url()->current(),
            'type' => $type,
            'json_ld' => $jsonLd,
        ];
    }

    public static function forProduct(Product $p): array
    {
        $price = $p->displayPrice();
        $name = $p->localized('name');
        $title = $p->localized('meta_title') ?: ($p->goldCategory
            ? __('shop.seo.product_title', ['name' => $name, 'karat' => $p->goldCategory->name, 'weight' => rtrim(rtrim((string) $p->weight_grams, '0'), '.')])
            : $name);
        $description = $p->localized('meta_description') ?: __('shop.seo.product_description', [
            'name' => $name,
            'karat' => $p->goldCategory?->name ?? '',
            'weight' => rtrim(rtrim((string) $p->weight_grams, '0'), '.'),
            'price' => Money::format($price->taxable),
        ]);

        return self::page($title, $description, $p->primaryImage(), route('product.show', $p), 'product', [self::productSchema($p)]);
    }

    public static function forCategory(Category $c): array
    {
        return self::page(
            $c->localized('meta_title') ?: __('shop.seo.category_title', ['name' => $c->localized('name')]),
            $c->localized('meta_description') ?: __('shop.seo.category_description', ['name' => $c->localized('name')]),
            canonical: route('category.show', $c),
        );
    }

    /** Schema.org Product with Offer — what Google Shopping rich results read. */
    public static function productSchema(Product $p): array
    {
        $price = $p->displayPrice();
        $reviews = $p->approvedReviews()->get(['rating']);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $p->localized('name'),
            'description' => Str::limit(strip_tags((string) $p->localized('description')) ?: $p->localized('name'), 5000),
            'sku' => $p->sku,
            'mpn' => $p->sku,
            'image' => array_map(fn ($u) => url($u), $p->imageUrls() ?: [$p->primaryImage()]),
            'brand' => ['@type' => 'Brand', 'name' => Settings::get('store_name')],
            'category' => $p->category?->name ?? 'Jewelry',
            'material' => $p->goldCategory ? $p->goldCategory->name.' Gold' : null,
            'weight' => ['@type' => 'QuantitativeValue', 'value' => (float) $p->weight_grams, 'unitCode' => 'GRM'],
            'offers' => [
                '@type' => 'Offer',
                'url' => route('product.show', $p),
                'priceCurrency' => 'AED',
                'price' => Money::toAed($price->taxable),
                'priceValidUntil' => now()->addDay()->toDateString(),
                'availability' => $p->inStock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => ['@type' => 'Organization', 'name' => Settings::get('store_legal_name')],
                'priceSpecification' => [
                    '@type' => 'UnitPriceSpecification',
                    'price' => Money::toAed($price->taxable),
                    'priceCurrency' => 'AED',
                    'valueAddedTaxIncluded' => false,   // matches the price shown on the page; VAT is added at checkout
                ],
            ],
        ];

        if ($reviews->isNotEmpty()) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round($reviews->avg('rating'), 1),
                'reviewCount' => $reviews->count(),
            ];
        }

        return $schema;
    }

    public static function organizationSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'JewelryStore',
            'name' => Settings::get('store_name'),
            'url' => url('/'),
            'telephone' => Settings::get('store_phone'),
            'address' => ['@type' => 'PostalAddress', 'streetAddress' => Settings::get('store_address'), 'addressCountry' => 'AE'],
            'currenciesAccepted' => 'AED',
        ];
    }
}
