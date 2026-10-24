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
        // A piece in the bag was sold to someone else meanwhile: take it out and say so.
        if ($notes = $this->cart->dropUnavailable()) {
            return redirect()->route('cart.show')->withErrors(['cart' => implode(' ', $notes)]);
        }

        return view('shop.cart', [
            'seo' => Seo::page(__('shop.nav.cart')),
            'lines' => $this->cart->lines(),
            'changes' => $this->cart->revalidate(),
        ]);
    }

    public function add(Request $request, Product $product)
    {
        abort_unless($product->isListed(), 404);
        if (! $product->inStock()) {
            return back()->withErrors(['cart' => __('shop.product.out_of_stock')]);
        }
        // Size / gold colour: required when the piece offers them, and only from its own list.
        $options = [];
        foreach (['size' => $product->sizeList(), 'colour' => $product->colourList()] as $key => $allowed) {
            if (! $allowed) {
                continue;
            }
            $value = (string) $request->input($key, '');
            if (! in_array($value, $allowed, true)) {
                if ($request->wantsJson()) {
                    return response()->json(['message' => __('shop.product.choose_'.$key)], 422);
                }

                return redirect()->route('product.show', $product)->withErrors(['cart' => __('shop.product.choose_'.$key)]);
            }
            $options[$key] = $value;
        }
        $this->cart->add($product, (int) $request->input('qty', 1), $options);

        if ($request->wantsJson()) {
            return response()->json(['count' => $this->cart->count()]);
        }

        // Straight to the bag, so the customer sees what is in it and can check out.
        return redirect()->route('cart.show')->with('status', __('shop.cart.added', ['name' => $product->localized('name')]))
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
