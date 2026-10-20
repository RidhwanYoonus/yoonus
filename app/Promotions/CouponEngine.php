<?php

namespace App\Promotions;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\User;
use App\Pricing\Money;
use App\Shop\CartLine;

/**
 * Evaluates one coupon against cart lines. Discounts are on the pre-VAT subtotal;
 * the quote applies VAT on the discounted amount and enforces the gold-cost floor.
 */
class CouponEngine
{
    /** @param array<int,CartLine> $lines */
    public function evaluate(Coupon $coupon, array $lines, ?User $user, ?string $email): CouponResult
    {
        if (! $coupon->isWithinSchedule()) {
            return CouponResult::invalid($coupon, __('shop.coupon.expired'));
        }
        if ($coupon->issued_to_user_id && $coupon->issued_to_user_id !== $user?->id) {
            return CouponResult::invalid($coupon, __('shop.coupon.not_yours'));
        }
        if ($coupon->max_uses !== null && $coupon->times_used >= $coupon->max_uses) {
            return CouponResult::invalid($coupon, __('shop.coupon.used_up'));
        }
        if ($coupon->max_uses_per_customer !== null && ($user || $email)) {
            $used = CouponRedemption::where('coupon_id', $coupon->id)
                ->where(fn ($q) => $q->when($user, fn ($q) => $q->where('user_id', $user->id))
                    ->when($email, fn ($q) => $q->orWhere('email', strtolower($email))))
                ->count();
            if ($used >= $coupon->max_uses_per_customer) {
                return CouponResult::invalid($coupon, __('shop.coupon.used_up'));
            }
        }
        if ($coupon->first_order_only && $this->hasOrdered($user, $email)) {
            return CouponResult::invalid($coupon, __('shop.coupon.first_order'));
        }

        $gross = array_sum(array_map(fn (CartLine $l) => $l->gross(), $lines));
        if ($coupon->min_cart_value !== null && $gross < Money::toFils($coupon->min_cart_value)) {
            return CouponResult::invalid($coupon, __('shop.coupon.min_cart', ['amount' => Money::format(Money::toFils($coupon->min_cart_value))]));
        }

        $eligible = array_filter($lines, fn (CartLine $l) => $coupon->covers($l->product));
        if ($eligible === [] && $coupon->type !== 'free_shipping') {
            return CouponResult::invalid($coupon, __('shop.coupon.not_applicable'));
        }

        // An affiliate's own code: the customer gets X% of the affiliate's commission on the cart
        // (which is then taken from that commission), not X% of the cart.
        if ($coupon->affiliate_id && ($affiliate = \App\Models\Affiliate::find($coupon->affiliate_id))) {
            $service = app(\App\Affiliates\AffiliateService::class);
            $pct = Money::scaled($coupon->value, 2);
            $discounts = [];
            foreach ($eligible as $i => $line) {
                $discounts[$i] = min(Money::percentOf($service->commissionForCartLine($affiliate, $line), $pct), $line->maxDiscount());
            }

            return array_sum($discounts) > 0
                ? new CouponResult($coupon, array_filter($discounts))
                : CouponResult::invalid($coupon, __('shop.coupon.not_applicable'));
        }

        return match ($coupon->type) {
            'percentage' => new CouponResult($coupon, $this->percent($eligible, Money::scaled($coupon->value, 2))),
            'fixed' => new CouponResult($coupon, $this->allocate($eligible, Money::toFils($coupon->value))),
            'tiered' => $this->tiered($coupon, $eligible),
            'bogo' => new CouponResult($coupon, $this->bogo($coupon, $eligible)),
            'free_shipping' => new CouponResult($coupon, freeShipping: true),
        };
    }

    private function hasOrdered(?User $user, ?string $email): bool
    {
        return Order::query()->whereNotIn('status', ['cancelled'])
            ->where(fn ($q) => $q->when($user, fn ($q) => $q->where('user_id', $user->id))
                ->when($email, fn ($q) => $q->orWhere('email', strtolower($email))))
            ->when(! $user && ! $email, fn ($q) => $q->whereRaw('1 = 0'))
            ->exists();
    }

    private function percent(array $lines, int $bp): array
    {
        return array_map(fn (CartLine $l) => Money::percentOf($l->subtotal(), $bp), $lines);
    }

    /** Split a fixed amount across lines in proportion to their subtotal. */
    public function allocate(array $lines, int $amount): array
    {
        $total = array_sum(array_map(fn (CartLine $l) => $l->subtotal(), $lines));
        if ($total === 0 || $amount <= 0) {
            return [];
        }
        $amount = min($amount, $total);
        $out = [];
        $left = $amount;
        $keys = array_keys($lines);
        foreach ($keys as $i => $k) {
            $share = $i === array_key_last($keys) ? $left : Money::divRound($amount * $lines[$k]->subtotal(), $total);
            $share = min($share, $left);
            $out[$k] = $share;
            $left -= $share;
        }

        return $out;
    }

    private function tiered(Coupon $coupon, array $lines): CouponResult
    {
        $gross = array_sum(array_map(fn (CartLine $l) => $l->gross(), $lines));
        $tier = collect($coupon->rules ?? [])->sortByDesc('min')
            ->first(fn ($t) => $gross >= Money::toFils($t['min'] ?? 0));
        if (! $tier) {
            $lowest = collect($coupon->rules ?? [])->min('min');

            return CouponResult::invalid($coupon, __('shop.coupon.min_cart', ['amount' => Money::format(Money::toFils($lowest))]));
        }

        return new CouponResult($coupon, $this->percent($lines, Money::scaled($tier['percent'], 2)));
    }

    /** Buy N get M: in each block of N+M units (priciest first), the cheapest M get X% off. */
    private function bogo(Coupon $coupon, array $lines): array
    {
        $buy = max(1, (int) ($coupon->rules['buy'] ?? 1));
        $get = max(1, (int) ($coupon->rules['get'] ?? 1));
        $bp = Money::scaled($coupon->rules['get_percent'] ?? 100, 2);

        $units = [];
        foreach ($lines as $k => $l) {
            for ($i = 0; $i < $l->qty; $i++) {
                $units[] = [$k, $l->unit->taxable];
            }
        }
        usort($units, fn ($a, $b) => $b[1] <=> $a[1]);

        $out = [];
        foreach (array_chunk($units, $buy + $get) as $block) {
            if (count($block) < $buy + $get) {
                break;
            }
            foreach (array_slice($block, $buy) as [$k, $price]) {
                $out[$k] = ($out[$k] ?? 0) + Money::percentOf($price, $bp);
            }
        }

        return $out;
    }
}
