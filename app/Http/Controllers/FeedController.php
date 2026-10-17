<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Pricing\Money;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FeedController extends Controller
{
    public function sitemap()
    {
        $xml = Cache::remember('sitemap.xml', 3600, function () {
            $urls = collect([
                ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'daily'],
                ['loc' => route('shop.index'), 'priority' => '0.9', 'changefreq' => 'daily'],
            ]);
            Category::where('is_active', true)->get()->each(fn ($c) => $urls->push(['loc' => route('category.show', $c), 'lastmod' => $c->updated_at, 'priority' => '0.8', 'changefreq' => 'weekly']));
            Product::active()->select('slug', 'updated_at', 'price_calculated_at')->cursor()->each(fn ($p) => $urls->push([
                'loc' => route('product.show', $p), 'lastmod' => max($p->updated_at, $p->price_calculated_at ?? $p->updated_at), 'priority' => '0.7', 'changefreq' => 'daily',
            ]));

            return view('feeds.sitemap', ['urls' => $urls])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots()
    {
        $body = implode("\n", [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /checkout',
            'Disallow: /cart',
            'Disallow: /account',
            'Disallow: /orders/',
            'Disallow: /payments/',
            'Allow: /',
            '',
            'Sitemap: '.route('sitemap'),
        ]);

        return response($body, 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Google Merchant Center feed. Cached for an hour and flushed on every gold-rate
     * change, so Shopping prices follow the rate immediately.
     */
    public function google()
    {
        return $this->feed('feed.google');
    }

    /** Same feed format, reusable as a Meta (Facebook/Instagram) catalog source. */
    public function meta()
    {
        return $this->feed('feed.meta');
    }

    private function feed(string $key)
    {
        $xml = Cache::remember($key, 3600, function () {
            $items = Product::with('goldCategory', 'category')->active()->get()->map(function (Product $p) {
                $price = $p->price();
                $display = $p->displayPrice();

                return [
                    'id' => $p->sku,
                    'title' => Str::limit($p->goldCategory ? $p->name.' — '.$p->goldCategory->name.' Gold '.rtrim(rtrim((string) $p->weight_grams, '0'), '.').'g' : $p->name, 150, ''),
                    'description' => Str::limit(strip_tags((string) $p->description) ?: $p->name, 4900, ''),
                    'link' => route('product.show', $p),
                    'image' => url($p->primaryImage()),
                    'extra_images' => array_slice(array_map(fn ($u) => url($u), $p->imageUrls()), 1, 9),
                    'availability' => $p->inStock() ? 'in_stock' : 'out_of_stock',
                    'price' => Money::toAed($price->total).' AED',
                    'sale_price' => $display->hasDiscount() ? Money::toAed($display->total).' AED' : null,
                    'brand' => Settings::get('store_name'),
                    'material' => $p->goldCategory ? $p->goldCategory->name.' Gold' : null,
                    'product_type' => $p->category?->name ?? 'Jewelry',
                    'weight' => $p->weight_grams.' g',
                ];
            });

            return view('feeds.google', ['items' => $items])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
