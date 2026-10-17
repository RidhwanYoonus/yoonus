<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Support\Images;
use App\Support\Seo;
use App\Support\Settings;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function show(Request $request, Product $product)
    {
        abort_unless($product->is_active, 404);
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

    /** Elegant SVG stand-in until real product photography is uploaded. */
    public function placeholder(string $product)
    {
        $p = Product::where('slug', $product)->first();
        $label = e($p?->goldCategory?->name ?? 'Gold');
        $hue = $p ? (crc32($p->slug) % 30) + 35 : 45;
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 600">
  <defs>
    <radialGradient id="bg" cx="50%" cy="40%" r="75%"><stop offset="0" stop-color="#fffaf0"/><stop offset="1" stop-color="#efe3c8"/></radialGradient>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="hsl({$hue},75%,62%)"/><stop offset=".5" stop-color="hsl({$hue},80%,45%)"/><stop offset="1" stop-color="hsl({$hue},70%,30%)"/></linearGradient>
  </defs>
  <rect width="600" height="600" fill="url(#bg)"/>
  <ellipse cx="300" cy="300" rx="150" ry="150" fill="none" stroke="url(#g)" stroke-width="34"/>
  <ellipse cx="300" cy="300" rx="150" ry="150" fill="none" stroke="#fff" stroke-opacity=".35" stroke-width="4" transform="translate(-6,-6)"/>
  <path d="M300 112 l26 40 h-52z" fill="#dfeef7" stroke="#9fb9c9" stroke-width="2"/>
  <text x="300" y="540" font-family="Georgia,serif" font-size="28" fill="#8a6d2b" text-anchor="middle" letter-spacing="6">{$label}</text>
</svg>
SVG;

        return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=86400']);
    }
}
