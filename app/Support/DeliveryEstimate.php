<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * "Arrives Tue 28 Oct – Thu 30 Oct" / "Ready to collect today": working days from Settings → Delivery & pickup.
 * Orders after the day's cut-off time start counting from the next working day.
 */
class DeliveryEstimate
{
    public static function enabled(): bool
    {
        return Settings::bool('delivery_estimate', true);
    }

    /** ISO weekdays the shop works (1 = Monday … 7 = Sunday). */
    public static function workingDays(): array
    {
        $v = Settings::get('working_days', [1, 2, 3, 4, 5, 6]);
        $v = array_map('intval', is_string($v) ? (json_decode($v, true) ?: []) : (array) $v);

        return $v ?: [1, 2, 3, 4, 5, 6, 7];
    }

    /** First working moment for an order placed at $at (after the cut-off: the next working day). */
    private static function start(Carbon $at): Carbon
    {
        $d = $at->copy()->timezone(config('app.timezone'));
        [$h, $m] = array_map('intval', explode(':', (string) Settings::get('order_cutoff', '17:00')) + [0, 0]);
        if ($d->hour * 60 + $d->minute >= $h * 60 + $m) {
            $d = $d->addDay()->startOfDay();
        }
        while (! in_array($d->dayOfWeekIso, self::workingDays(), true)) {
            $d = $d->addDay()->startOfDay();
        }

        return $d;
    }

    private static function addWorkingDays(Carbon $d, int $n): Carbon
    {
        $d = $d->copy();
        while ($n > 0) {
            $d = $d->addDay();
            if (in_array($d->dayOfWeekIso, self::workingDays(), true)) {
                $n--;
            }
        }

        return $d;
    }

    /** @return array{0: Carbon, 1: Carbon} earliest and latest delivery day */
    public static function deliveryRange(?Carbon $at = null): array
    {
        $start = self::start($at ?? now());
        $min = max(0, (int) Settings::get('delivery_days_min', 1));
        $max = max($min, (int) Settings::get('delivery_days_max', 3));

        return [self::addWorkingDays($start, $min), self::addWorkingDays($start, $max)];
    }

    public static function pickupDay(?Carbon $at = null): Carbon
    {
        return self::addWorkingDays(self::start($at ?? now()), max(0, (int) Settings::get('pickup_ready_days', 0)));
    }

    /** "today", "tomorrow" or "Tue 28 Oct", in the shop's language. */
    public static function day(Carbon $d, ?Carbon $from = null): string
    {
        $today = ($from ?? now())->copy()->timezone(config('app.timezone'))->startOfDay();
        $diff = (int) $today->diffInDays($d->copy()->startOfDay(), false);

        return match ($diff) {
            0 => __('shop.eta.today'),
            1 => __('shop.eta.tomorrow'),
            default => $d->locale(app()->getLocale())->isoFormat('ddd D MMM'),
        };
    }

    public static function deliveryText(?Carbon $at = null): string
    {
        [$a, $b] = self::deliveryRange($at);

        return $a->isSameDay($b) ? __('shop.eta.delivery_on', ['day' => self::day($a)]) : __('shop.eta.delivery_between', ['from' => self::day($a), 'to' => self::day($b)]);
    }

    public static function pickupText(?Carbon $at = null): string
    {
        return __('shop.eta.pickup_on', ['day' => self::day(self::pickupDay($at))]);
    }

    /** For an order already placed: the estimate from when it was placed (or paid). */
    public static function forOrder(\App\Models\Order $order): ?string
    {
        if (in_array($order->status, ['delivered', 'cancelled', 'refunded'], true)) {
            return null;
        }
        // A date staff gave the customer (Update status) comes first.
        if ($order->expected_at ?? null) {
            $day = self::day($order->expected_at);

            return $order->fulfilment === 'pickup' ? __('shop.eta.pickup_on', ['day' => $day]) : __('shop.eta.delivery_on', ['day' => $day]);
        }
        if (! self::enabled()) {
            return null;
        }
        $at = $order->paid_at ?? $order->created_at;
        if ($order->fulfilment === 'pickup') {
            return $order->status === 'ready' ? null : self::pickupText($at);
        }

        return $order->status === 'shipped' ? null : self::deliveryText($at);
    }
}
