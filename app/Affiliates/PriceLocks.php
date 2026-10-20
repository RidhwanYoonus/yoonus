<?php

namespace App\Affiliates;

use App\Models\Affiliate;
use App\Models\AffiliatePriceLock;
use App\Models\Order;
use App\Models\Product;
use App\Pricing\PriceBreakdown;
use App\Pricing\PricingService;
use App\Support\Settings;
use Illuminate\Support\Str;
use Throwable;

/**
 * Price-lock links: an affiliate (when the admin allows it) freezes today's price of a piece for a
 * customer for a few hours. A customer who opens the link sees and pays that price until it expires.
 */
class PriceLocks
{
    public const SESSION = 'price_locks';

    /** @var array<int, ?AffiliatePriceLock> */
    private static array $loaded = [];

    public static function hours(): int
    {
        return max(1, min(168, (int) Settings::get('affiliate_price_lock_hours', 24)));
    }

    public static function create(Affiliate $affiliate, Product $product): AffiliatePriceLock
    {
        $pricing = app(PricingService::class);
        $saved = $pricing->markup();
        $pricing->setMarkup($affiliate->effectiveMarkup());   // the price their customers see
        try {
            $price = $pricing->compute($product)->raw();
        } finally {
            $pricing->setMarkup($saved);
        }

        return AffiliatePriceLock::create([
            'token' => Str::random(32), 'affiliate_id' => $affiliate->id, 'product_id' => $product->id,
            'price' => $price, 'expires_at' => now()->addHours(self::hours()),
        ]);
    }

    /** Customer opened the link: remember it for this visitor. */
    public static function remember(AffiliatePriceLock $lock): void
    {
        $locks = (array) session(self::SESSION, []);
        $locks[$lock->product_id] = $lock->token;
        session([self::SESSION => $locks]);
        self::$loaded = [];
    }

    public static function forProduct(int $productId): ?AffiliatePriceLock
    {
        if (! app()->bound('session') || ! session()->isStarted()) {
            return null;
        }
        $token = ((array) session(self::SESSION, []))[$productId] ?? null;
        if (! $token) {
            return null;
        }
        if (! array_key_exists($productId, self::$loaded)) {
            try {
                $lock = AffiliatePriceLock::with('affiliate')->where('token', $token)->first();
                self::$loaded[$productId] = $lock && $lock->isValid() ? $lock : null;
            } catch (Throwable) {
                self::$loaded[$productId] = null;
            }
        }

        return self::$loaded[$productId];
    }

    public static function lockedPrice(int $productId): ?PriceBreakdown
    {
        return self::forProduct($productId)?->breakdown();
    }

    /** After checkout: count the use and forget the lock for this visitor. */
    public static function used(Order $order): void
    {
        $locks = (array) session(self::SESSION, []);
        foreach ($order->items as $item) {
            if (isset($locks[$item->product_id])) {
                AffiliatePriceLock::where('token', $locks[$item->product_id])->increment('uses');
                unset($locks[$item->product_id]);
            }
        }
        session([self::SESSION => $locks]);
        self::$loaded = [];
    }

    public static function flush(): void
    {
        self::$loaded = [];
    }
}
