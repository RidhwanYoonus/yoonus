<?php

namespace App\Http\Controllers;

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
            'sort' => 'nullable|in:newest,price_asc,price_desc,weight_asc,weight_desc,popular',
            'q' => 'nullable|string|max:100',
        ]);
        $categoryId = $category?->id ?? ($filters['category'] ?? null);

        // Price filter/sort uses the cached price column, refreshed on every rate change.
        $query = Product::with('goldCategory', 'category')->active()
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($filters['karat'] ?? null, fn ($q, $k) => $q->whereIn('gold_category_id', $k))
            ->when($filters['stone'] ?? null, fn ($q, $s) => $s === 'none' ? $q->whereNull('stone_type') : $q->where('stone_type', $s))
            ->when($filters['min_price'] ?? null, fn ($q, $v) => $q->where('cached_sales_price', '>=', $v))
            ->when($filters['max_price'] ?? null, fn ($q, $v) => $q->where('cached_sales_price', '<=', $v))
            ->when($filters['min_weight'] ?? null, fn ($q, $v) => $q->where('weight_grams', '>=', $v))
            ->when($filters['max_weight'] ?? null, fn ($q, $v) => $q->where('weight_grams', '<=', $v))
            ->when($filters['q'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('name_ar', 'like', "%{$s}%")->orWhere('sku', $s)));

        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderBy('cached_sales_price'),
            'price_desc' => $query->orderByDesc('cached_sales_price'),
            'weight_asc' => $query->orderBy('weight_grams'),
            'weight_desc' => $query->orderByDesc('weight_grams'),
            'popular' => $query->orderByDesc('view_count'),
            default => $query->latest(),
        };

        return view('shop.index', [
            'seo' => $category ? Seo::forCategory($category) : Seo::page(__('shop.nav.shop')),
            'products' => $query->paginate(24)->withQueryString(),
            'category' => $category,
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(),
            'karats' => GoldCategory::where('is_active', true)->orderByDesc('karat')->get(),
            'stones' => Product::active()->whereNotNull('stone_type')->distinct()->pluck('stone_type'),
            'filters' => $filters,
        ]);
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

    public static function wishlistIds(Request $request): array
    {
        return $request->user()
            ? $request->user()->wishlist()->pluck('product_id')->all()
            : $request->session()->get('wishlist', []);
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
