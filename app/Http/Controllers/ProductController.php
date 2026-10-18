<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Support\Images;
use App\Support\JewelleryArt;
use App\Support\Seo;
use App\Support\Settings;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function show(Request $request, Product $product)
    {
        abort_unless($product->isListed(), 404);
        $product->load('goldCategory', 'category');
        Product::whereKey($product->id)->increment('view_count');

        $related = Product::with('goldCategory')->active()->whereKeyNot($product->id)
            ->where(fn ($q) => $q->where('category_id', $product->category_id)->when($product->gold_category_id, fn ($q) => $q->orWhere('gold_category_id', $product->gold_category_id)))
            ->inRandomOrder()->take(4)->get();

        return view('shop.product', [
            'seo' => Seo::forProduct($product),
            'product' => $product,
            'price' => $product->displayPrice(),
            'reviews' => $product->approvedReviews()->take(20)->get(),
            'related' => $related,
            'inWishlist' => in_array($product->id, ShopController::wishlistIds($request)),
            'whatsapp' => Settings::get('whatsapp_number'),
            'deliveryEnabled' => CheckoutController::deliveryEnabled(),
            'storeBranches' => Settings::bool('product_store_map') ? CheckoutController::pickupBranches() : collect(),
        ]);
    }

    public function review(Request $request, Product $product)
    {
        $data = $request->validate([
            'author_name' => 'required|string|max:80',
            'rating' => 'required|integer|between:1,5',
            'body' => 'required|string|min:10|max:2000',
            'photos' => 'nullable|array|max:4',
            'photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $photos = collect($request->file('photos', []))->map(fn ($f) => Images::storeWebp($f, 'reviews'))->all();
        $user = $request->user();

        Review::create([
            'product_id' => $product->id,
            'user_id' => $user?->id,
            'author_name' => $data['author_name'],
            'rating' => $data['rating'],
            'body' => $data['body'],
            'photos' => $photos,
            'status' => 'pending',     // moderated before it is shown
            'verified_purchase' => $user && OrderItem::where('product_id', $product->id)
                ->whereHas('order', fn ($q) => $q->where('user_id', $user->id)->where('payment_status', 'paid'))->exists(),
        ]);

        return back()->with('status', __('shop.reviews.thanks'));
    }

    /** Drawn stand-in (ring, necklace, bangle…) until real product photography is uploaded. */
    public function placeholder(string $product)
    {
        $p = Product::with('category', 'goldCategory')->where('slug', $product)->first();
        $type = JewelleryArt::typeFor($p?->category?->slug, $p?->name);
        $hue = $p ? 38 + (crc32($p->slug) % 12) : 42;
        $svg = JewelleryArt::product($type, $p?->stone_type, $hue, $p?->goldCategory?->name ?? '');

        return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=86400']);
    }
}
