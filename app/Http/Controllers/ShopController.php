<?php

namespace App\Http\Controllers;

use App\Support\Settings;

use App\Models\Campaign;
use App\Models\Category;
use App\Models\GoldCategory;
use App\Models\Product;
use App\Models\Wishlist;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ShopController extends Controller
{
    public function home()
    {
        return view('shop.home', [
            'seo' => Seo::page(__('shop.seo.home_title'), null, jsonLd: [Seo::organizationSchema()]),
            'campaigns' => Campaign::live()->orderBy('ends_at')->get(),
            'featured' => Product::with('goldCategory')->active()->where('is_featured', true)->latest()->take(8)->get(),
            'newArrivals' => Product::with('goldCategory')->active()->latest()->take(8)->get(),
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->take(6)->get(),
            'karats' => GoldCategory::where('is_active', true)->orderByDesc('karat')->get(),
        ]);
    }

    public function index(Request $request, ?Category $category = null)
    {
        $filters = $request->validate([
            'karat' => 'nullable|array', 'karat.*' => 'integer',
            'category' => 'nullable|integer',
            'stone' => 'nullable|string|max:64',
            'min_price' => 'nullable|numeric|min:0', 'max_price' => 'nullable|numeric|min:0',
            'min_weight' => 'nullable|numeric|min:0', 'max_weight' => 'nullable|numeric|min:0',
            'sort' => 'nullable|in:newest,price_asc,price_desc,weight_asc,weight_desc,popular,carat_desc',
            'q' => 'nullable|string|max:100',
            'available' => 'nullable|in:0,1',
            'diamond' => 'nullable|in:any,natural,lab_grown,none',
            'color' => 'nullable|array', 'color.*' => 'string|max:16',
            'clarity' => 'nullable|array', 'clarity.*' => 'string|max:16',
            'shape' => 'nullable|array', 'shape.*' => 'string|max:32',
            'min_carat' => 'nullable|numeric|min:0', 'max_carat' => 'nullable|numeric|min:0',
            'price_type' => 'nullable|in:GOLD_RATE,FIXED',
        ]);
        $categoryId = $category?->id ?? ($filters['category'] ?? null);
        // "Available only" starts ticked when Settings → Shop hides sold pieces; the shopper can untick it.
        $filters['available'] ??= Settings::bool('shop_hide_sold') ? '1' : '0';

        // Price filter/sort uses the cached price column, refreshed on every rate change.
        $query = Product::with('goldCategory', 'category')->active()
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($filters['karat'] ?? null, fn ($q, $k) => $q->whereIn('gold_category_id', $k))
            ->when($filters['stone'] ?? null, fn ($q, $s) => $s === 'none' ? $q->where(fn ($q) => $q->whereNull('stone_type')->orWhere('stone_type', '')) : $q->withStone($s))
            ->when($filters['min_price'] ?? null, fn ($q, $v) => $q->where('cached_subtotal', '>=', $v))
            ->when($filters['max_price'] ?? null, fn ($q, $v) => $q->where('cached_subtotal', '<=', $v))
            ->when($filters['min_weight'] ?? null, fn ($q, $v) => $q->where('weight_grams', '>=', $v))
            ->when($filters['max_weight'] ?? null, fn ($q, $v) => $q->where('weight_grams', '<=', $v))
            ->when($filters['q'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('name_ar', 'like', "%{$s}%")->orWhere('sku', $s)));
        $this->applyExtraFilters($query, $filters);

        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderBy('cached_subtotal'),
            'price_desc' => $query->orderByDesc('cached_subtotal'),
            'weight_asc' => $query->orderBy('weight_grams'),
            'weight_desc' => $query->orderByDesc('weight_grams'),
            'popular' => $query->orderByDesc('view_count'),
            'carat_desc' => $query->orderByDesc('diamond_carat'),
            default => $query->latest(),
        };

        return view('shop.index', [
            'seo' => $category ? Seo::forCategory($category) : Seo::page(__('shop.nav.shop')),
            'products' => $query->paginate(24)->withQueryString(),
            'category' => $category,
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(),
            'karats' => GoldCategory::where('is_active', true)->orderByDesc('karat')->get(),
            // Each stone once, even when pieces hold several ("Diamond, Emerald"); value = saved name, text = visitor's language.
            'stones' => Product::active()->whereNotNull('stone_type')->distinct()->pluck('stone_type')
                ->flatMap(fn ($v) => Product::splitStones($v))->unique()->sort()->values()
                ->mapWithKeys(fn ($s) => [$s => \App\Models\StoneType::label($s)]),
            'diamondOptions' => $this->diamondOptions($categoryId),
            'hasFixed' => Product::active()->where('pricing_type', 'FIXED')->exists(),
            'filters' => $filters,
        ]);
    }

    /** Availability, diamond type and diamond grade filters. */
    private function applyExtraFilters($query, array $f): void
    {
        $query
            ->when(($f['available'] ?? '0') === '1', fn ($q) => $q->where('stock_qty', '>', 0))
            ->when($f['diamond'] ?? null, fn ($q, $d) => match ($d) {
                'any' => $q->where(fn ($q) => $q->whereNotNull('diamond_origin')->orWhereNotNull('diamond_carat')->orWhere('stone_type', 'like', '%diamond%')),
                'none' => $q->whereNull('diamond_origin')->whereNull('diamond_carat')->where(fn ($q) => $q->whereNull('stone_type')->orWhere('stone_type', 'not like', '%diamond%')),
                default => $q->where('diamond_origin', $d),
            })
            ->when($f['color'] ?? null, fn ($q, $v) => $q->whereIn('diamond_color', $v))
            ->when($f['clarity'] ?? null, fn ($q, $v) => $q->whereIn('diamond_clarity', $v))
            ->when($f['shape'] ?? null, fn ($q, $v) => $q->whereIn('diamond_shape', $v))
            ->when($f['min_carat'] ?? null, fn ($q, $v) => $q->where('diamond_carat', '>=', $v))
            ->when($f['max_carat'] ?? null, fn ($q, $v) => $q->where('diamond_carat', '<=', $v))
            ->when($f['price_type'] ?? null, fn ($q, $v) => $q->where('pricing_type', $v));
    }

    /** Diamond choices that actually exist in this list, so the filter never offers an empty option. */
    private function diamondOptions(?int $categoryId): array
    {
        return rescue(function () use ($categoryId) {
            $base = fn () => Product::active()->when($categoryId, fn ($q) => $q->where('category_id', $categoryId));
            $order = fn (array $all, $have) => array_values(array_intersect($all, $have->filter()->all()));

            return [
                'origins' => $base()->whereNotNull('diamond_origin')->distinct()->pluck('diamond_origin')->all(),
                'colors' => $order(['D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'D-F', 'G-H', 'I-J', 'Fancy'], $base()->distinct()->pluck('diamond_color')),
                'clarities' => $order(['FL', 'IF', 'VVS1', 'VVS2', 'VVS', 'VS1', 'VS2', 'VS', 'SI1', 'SI2', 'SI', 'I1', 'I2', 'I3'], $base()->distinct()->pluck('diamond_clarity')),
                'shapes' => $base()->whereNotNull('diamond_shape')->distinct()->orderBy('diamond_shape')->pluck('diamond_shape')->all(),
                'has_carat' => $base()->whereNotNull('diamond_carat')->exists(),
                'any' => $base()->where(fn ($q) => $q->whereNotNull('diamond_origin')->orWhereNotNull('diamond_carat')->orWhere('stone_type', 'like', '%diamond%'))->exists(),
            ];
        }, ['origins' => [], 'colors' => [], 'clarities' => [], 'shapes' => [], 'has_carat' => false, 'any' => false], false);
    }

    public function category(Request $request, Category $category)
    {
        abort_unless($category->is_active, 404);

        return $this->index($request, $category);
    }

    /** Live rate ticker data for the homepage banner (polled; cached 60s). */
    public function rates()
    {
        return response()->json(Cache::remember('home.rates', 60, fn () => [
            'updated_at' => optional(GoldCategory::max('rate_updated_at'), fn ($d) => Carbon::parse($d)->toIso8601String()),
            'rates' => GoldCategory::where('is_active', true)->orderByDesc('karat')->get(['id', 'name', 'rate_per_gram'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'rate' => $c->rate_per_gram])->all(),
        ]));
    }

    public function wishlist(Request $request)
    {
        $ids = $this->wishlistIds($request);

        return view('shop.wishlist', [
            'seo' => Seo::page(__('shop.nav.wishlist')),
            'products' => Product::with('goldCategory')->active()->whereIn('id', $ids)->get(),
        ]);
    }

    public function toggleWishlist(Request $request, Product $product)
    {
        if ($user = $request->user()) {
            $existing = Wishlist::where('user_id', $user->id)->where('product_id', $product->id)->first();
            $existing ? $existing->delete() : Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);
            $added = ! $existing;
        } else {
            $ids = collect($request->session()->get('wishlist', []));
            $added = ! $ids->contains($product->id);
            $request->session()->put('wishlist', $added ? $ids->push($product->id)->unique()->values()->all() : $ids->reject(fn ($i) => $i === $product->id)->values()->all());
        }

        return $request->wantsJson() ? response()->json(['added' => $added]) : back()->with('status', __($added ? 'shop.wishlist.added' : 'shop.wishlist.removed'));
    }

    /** Saved product ids, looked up once per request (every product card asks). */
    public static function wishlistIds(Request $request): array
    {
        if (! $request->attributes->has('wishlist_ids')) {
            $request->attributes->set('wishlist_ids', array_map('intval', $request->user()
                ? $request->user()->wishlist()->pluck('product_id')->all()
                : ($request->hasSession() ? $request->session()->get('wishlist', []) : [])));
        }

        return $request->attributes->get('wishlist_ids');
    }

    public function compare(Request $request)
    {
        return view('shop.compare', [
            'seo' => Seo::page(__('shop.nav.compare')),
            'products' => Product::with('goldCategory', 'category')->active()->whereIn('id', $request->session()->get('compare', []))->get(),
        ]);
    }

    public function toggleCompare(Request $request, Product $product)
    {
        $ids = collect($request->session()->get('compare', []));
        if ($ids->contains($product->id)) {
            $ids = $ids->reject(fn ($i) => $i === $product->id);
        } else {
            $ids = $ids->push($product->id)->unique()->take(-4);   // compare up to 4
        }
        $request->session()->put('compare', $ids->values()->all());

        return back()->with('status', __('shop.compare.updated'));
    }
}
