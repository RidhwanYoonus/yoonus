<?php

namespace App\Shop;

use App\Models\Cart;
use App\Models\Product;
use App\Pricing\Money;
use App\Pricing\PricingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Session cart backed by a `carts` row (for abandoned-cart recovery).
 * Each item remembers the unit price the shopper last saw, so checkout can flag changes.
 */
class CartService
{
    private ?Cart $cart = null;

    public function __construct(private readonly PricingService $pricing) {}

    public function model(bool $create = false): ?Cart
    {
        if ($this->cart) {
            return $this->cart;
        }
        $token = session('cart_token');
        $this->cart = $token ? Cart::where('token', $token)->whereNull('converted_at')->first() : null;

        if (! $this->cart && $create) {
            $this->cart = Cart::create([
                'token' => (string) Str::uuid(),
                'user_id' => Auth::id(),
                'email' => Auth::user()?->email,
                'phone' => Auth::user()?->phone,
                'locale' => app()->getLocale(),
                'items' => [],
                'last_activity_at' => now(),
            ]);
            session(['cart_token' => $this->cart->token]);
        }

        return $this->cart;
    }

    public function add(Product $product, int $qty = 1): void
    {
        $cart = $this->model(true);
        $items = collect($cart->items)->keyBy('product_id');
        $current = $items->get($product->id)['qty'] ?? 0;
        $items[$product->id] = [
            'product_id' => $product->id,
            'qty' => min($current + max(1, $qty), max(1, $product->stock_qty)),
            'price_seen' => $this->pricing->displayPriceFor($product)->total,
        ];
        $this->save($items->values()->all());
    }

    public function update(int $productId, int $qty): void
    {
        $cart = $this->model();
        if (! $cart) {
            return;
        }
        $items = collect($cart->items)->map(function ($i) use ($productId, $qty) {
            if ($i['product_id'] === $productId) {
                $i['qty'] = $qty;
            }

            return $i;
        })->filter(fn ($i) => $i['qty'] > 0);
        $this->save($items->values()->all());
    }

    public function remove(int $productId): void
    {
        $this->update($productId, 0);
    }

    public function count(): int
    {
        return (int) collect($this->model()?->items ?? [])->sum('qty');
    }

    /** @return array<int,CartLine> live-priced lines (inactive/out-of-stock products dropped) */
    public function lines(): array
    {
        $items = collect($this->model()?->items ?? []);
        if ($items->isEmpty()) {
            return [];
        }
        $products = Product::with('goldCategory', 'category')->active()->whereIn('id', $items->pluck('product_id'))->get()->keyBy('id');

        return $items->filter(fn ($i) => $products->has($i['product_id']))
            ->map(function ($i) use ($products) {
                $p = $products[$i['product_id']];

                return new CartLine($p, min((int) $i['qty'], max(0, $p->stock_qty)), $this->pricing->forProduct($p), $i['price_seen'] ?? null);
            })
            ->filter(fn (CartLine $l) => $l->qty > 0)
            ->values()->all();
    }

    /** Re-price against the live rate; returns lines whose price changed since the shopper saw it. */
    public function revalidate(): array
    {
        $changed = [];
        $cart = $this->model();
        if (! $cart) {
            return [];
        }
        $items = collect($cart->items)->map(function ($i) use (&$changed) {
            $p = Product::find($i['product_id']);
            if ($p) {
                $now = $this->pricing->displayPriceFor($p)->total;
                if (isset($i['price_seen']) && $i['price_seen'] !== $now) {
                    $changed[] = ['product' => $p, 'old' => $i['price_seen'], 'new' => $now];
                }
                $i['price_seen'] = $now;
            }

            return $i;
        });
        $this->save($items->values()->all());

        return $changed;
    }

    public function attachContact(?string $email, ?string $phone): void
    {
        $cart = $this->model();
        $cart?->forceFill(array_filter([
            'email' => $email ? strtolower($email) : null,
            'phone' => $phone,
            'user_id' => Auth::id(),
            'checkout_started_at' => $cart->checkout_started_at ?? now(),
        ]))->save();
    }

    public function markConverted(int $orderId): void
    {
        $this->model()?->forceFill(['order_id' => $orderId, 'converted_at' => now()])->save();
        session()->forget('cart_token');
        $this->cart = null;
    }

    private function save(array $items): void
    {
        $cart = $this->model(true);
        $cart->forceFill([
            'items' => $items,
            'last_activity_at' => now(),
            'reminders_sent' => 0,
            'locale' => app()->getLocale(),
        ]);
        $cart->last_known_total = Money::toAed(array_sum(array_map(fn (CartLine $l) => $l->gross(), $this->linesFor($items))));
        $cart->save();
    }

    private function linesFor(array $items): array
    {
        $this->cart->items = $items;

        return $this->lines();
    }
}
