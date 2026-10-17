<?php

namespace App\Support;

use App\Models\Cart;
use App\Models\GoldCategory;
use App\Models\GoldRateHistory;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

/** Sales analytics over paid orders (status not cancelled/refunded). */
class Reports
{
    public function __construct(public Carbon $from, public Carbon $to) {}

    public static function lastDays(int $days): self
    {
        return new self(now()->subDays($days - 1)->startOfDay(), now()->endOfDay());
    }

    private function paid()
    {
        return Order::query()->where('payment_status', 'paid')->whereNotIn('status', ['cancelled', 'refunded'])
            ->whereBetween('paid_at', [$this->from, $this->to]);
    }

    public function kpis(): array
    {
        $q = $this->paid();
        $revenue = (float) (clone $q)->sum('grand_total');
        $orders = (clone $q)->count();
        $carts = Cart::whereBetween('created_at', [$this->from, $this->to])->count();
        $abandoned = Cart::whereNull('converted_at')->whereBetween('created_at', [$this->from, $this->to])->where('last_activity_at', '<', now()->subHour());

        return [
            'revenue' => $revenue,
            'orders' => $orders,
            'aov' => $orders ? $revenue / $orders : 0,
            'vat' => (float) (clone $q)->sum('vat_total') + (float) (clone $q)->sum('shipping_vat'),
            'discounts' => (float) (clone $q)->sum('discount_total'),
            'carts' => $carts,
            'conversion' => $carts ? round($orders / $carts * 100, 1) : 0,
            'abandoned_count' => (clone $abandoned)->count(),
            'abandoned_value' => (float) (clone $abandoned)->sum('last_known_total'),
            'recovered' => Cart::whereNotNull('converted_at')->where('reminders_sent', '>', 0)->whereBetween('converted_at', [$this->from, $this->to])->count(),
        ];
    }

    public function byProduct(int $limit = 10)
    {
        return OrderItem::query()->whereIn('order_id', $this->paid()->select('id'))
            ->selectRaw('product_name, sku, karat, SUM(qty) as units, SUM(line_total) as revenue, SUM(weight_grams * qty) as grams')
            ->groupBy('product_name', 'sku', 'karat')->orderByDesc('revenue')->limit($limit)->get();
    }

    public function byCategory()
    {
        return OrderItem::query()->whereIn('order_items.order_id', $this->paid()->select('id'))
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->selectRaw("COALESCE(categories.name, 'Uncategorised') as category, SUM(order_items.qty) as units, SUM(order_items.line_total) as revenue")
            ->groupBy('category')->orderByDesc('revenue')->get();
    }

    public function byKarat()
    {
        return OrderItem::query()->whereIn('order_id', $this->paid()->select('id'))
            ->selectRaw('karat, SUM(qty) as units, SUM(weight_grams * qty) as grams, SUM(line_total) as revenue')
            ->groupBy('karat')->orderByDesc('revenue')->get();
    }

    /** Daily revenue alongside the closing rate of a karat, plus the Pearson correlation. */
    public function rateVsSales(?int $goldCategoryId = null): array
    {
        $goldCategoryId ??= GoldCategory::where('name', '22K')->value('id') ?? GoldCategory::value('id');
        $sales = $this->paid()->selectRaw('DATE(paid_at) as d, SUM(grand_total) as revenue')->groupBy('d')->pluck('revenue', 'd');
        $history = GoldRateHistory::where('gold_category_id', $goldCategoryId)->where('effective_from', '<=', $this->to)->orderBy('effective_from')->get(['rate_per_gram', 'effective_from']);

        $days = [];
        $rate = null;
        $i = 0;
        foreach (CarbonPeriod::create($this->from, $this->to) as $day) {
            $end = $day->copy()->endOfDay();
            while ($i < $history->count() && $history[$i]->effective_from->lte($end)) {
                $rate = (float) $history[$i]->rate_per_gram;
                $i++;
            }
            $days[] = ['date' => $day->toDateString(), 'rate' => $rate, 'revenue' => (float) ($sales[$day->toDateString()] ?? 0)];
        }

        return ['days' => $days, 'correlation' => self::pearson(array_column(array_filter($days, fn ($d) => $d['rate'] !== null), 'rate'), array_column(array_filter($days, fn ($d) => $d['rate'] !== null), 'revenue'))];
    }

    public static function pearson(array $x, array $y): ?float
    {
        $n = count($x);
        if ($n < 3) {
            return null;
        }
        $mx = array_sum($x) / $n;
        $my = array_sum($y) / $n;
        $num = $dx = $dy = 0;
        for ($i = 0; $i < $n; $i++) {
            $num += ($x[$i] - $mx) * ($y[$i] - $my);
            $dx += ($x[$i] - $mx) ** 2;
            $dy += ($y[$i] - $my) ** 2;
        }

        return $dx && $dy ? round($num / sqrt($dx * $dy), 2) : null;
    }
}
