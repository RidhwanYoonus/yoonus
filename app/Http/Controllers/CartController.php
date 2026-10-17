<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use App\Shop\CartService;
use App\Support\Seo;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function show()
    {
        return view('shop.cart', [
            'seo' => Seo::page(__('shop.nav.cart')),
            'lines' => $this->cart->lines(),
            'changes' => $this->cart->revalidate(),
        ]);
    }

    public function add(Request $request, Product $product)
    {
        abort_unless($product->is_active, 404);
        if (! $product->inStock()) {
            return back()->withErrors(['cart' => __('shop.product.out_of_stock')]);
        }
        $this->cart->add($product, (int) $request->input('qty', 1));

        if ($request->wantsJson()) {
            return response()->json(['count' => $this->cart->count()]);
        }

        return back()->with('status', __('shop.cart.added', ['name' => $product->localized('name')]))
            ->with('analytics_event', ['name' => 'add_to_cart', 'sku' => $product->sku, 'value' => $product->displayPrice()->total / 100, 'item_name' => $product->name]);
    }

    public function update(Request $request, int $productId)
    {
        $this->cart->update($productId, max(0, min(20, (int) $request->input('qty', 1))));

        return back();
    }

    public function remove(int $productId)
    {
        $this->cart->remove($productId);

        return back()->with('status', __('shop.cart.removed'));
    }

    /** Link from abandoned-cart emails/WhatsApp: restore the cart in this browser. */
    public function recover(Request $request, string $token)
    {
        $cart = Cart::where('token', $token)->whereNull('converted_at')->first();
        if (! $cart) {
            return redirect()->route('shop.index');
        }
        $request->session()->put('cart_token', $cart->token);
        app()->setLocale($cart->locale);

        return redirect()->route('cart.show', ['lang' => $cart->locale]);
    }
}
