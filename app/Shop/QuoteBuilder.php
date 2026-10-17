<?php

namespace App\Shop;

use App\Models\Coupon;
use App\Models\GiftCard;
use App\Models\ShippingZone;
use App\Models\User;
use App\Pricing\Money;
use App\Promotions\CouponEngine;
use App\Promotions\CouponResult;
use App\Support\Settings;

class QuoteBuilder
{
    public function __construct(private readonly CouponEngine $coupons) {}

    /**
     * @param  array<int,CartLine>  $lines
     */
    public function build(
        array $lines,
        ?User $user = null,
        ?string $email = null,
        ?string $couponCode = null,
        ?ShippingZone $zone = null,
        string $fulfilment = 'delivery',
        int $loyaltyPoints = 0,
        ?string $giftCardCode = null,
        ?string $paymentMethod = null,
    ): Quote {
        $q = new Quote($lines, $zone, $fulfilment);
        if ($q->isEmpty()) {
            return $q;
        }

        $baseShipping = $this->shippingFor($q, $zone, $fulfilment, array_sum(array_map(fn (CartLine $l) => $l->subtotal(), $lines)));
        $this->applyCoupons($q, $user, $email, $couponCode, $baseShipping);
        $this->applyLoyalty($q, $user, $loyaltyPoints);

        foreach ($lines as $i => $line) {
            $q->itemsSubtotal += $line->subtotal();
            $q->discountTotal += $line->discount;
            $taxable = $q->lineTaxable($i);
            $q->lineVat[$i] = Money::percentOf($taxable, $line->unit->vatBp);
            $q->taxable += $taxable;
            $q->vat += $q->lineVat[$i];
        }

        // Everything is priced before VAT; VAT is added once here, on goods, delivery and the payment fee.
        $vatBp = Money::scaled(Settings::get('default_vat_percent', 5), 2);
        $q->shipping = $q->freeShipping ? 0 : $this->shippingFor($q, $zone, $fulfilment, $q->taxable);
        $q->shippingVat = Money::percentOf($q->shipping, $vatBp);
        $beforeFee = $q->taxable + $q->vat + $q->shipping + $q->shippingVat;

        $this->applyGiftCard($q, $giftCardCode, $beforeFee);
        $q->amountBeforeFee = $beforeFee - $q->giftCardAmount;

        $q->paymentMethod = $paymentMethod;
        $q->paymentFee = $this->paymentFee($paymentMethod, $q->amountBeforeFee);
        $q->paymentFeeVat = Money::percentOf($q->paymentFee, $vatBp);

        $q->grandTotal = $beforeFee + $q->paymentFee + $q->paymentFeeVat;
        $q->amountDue = $q->grandTotal - $q->giftCardAmount;

        return $q;
    }

    /** Fee for the chosen payment method: % of the amount being paid + fixed amount (both from Settings). */
    public function paymentFee(?string $method, int $amount): int
    {
        if (! $method || $amount <= 0 || ! in_array($method, ['card', 'tabby', 'tamara', 'cod'], true)) {
            return 0;
        }

        return Money::percentOf($amount, Money::scaled(Settings::get("{$method}_fee_percent", 0), 2))
            + Money::toFils(Settings::get("{$method}_fee_fixed", 0));
    }

    private function shippingFor(Quote $q, ?ShippingZone $zone, string $fulfilment, int $itemsTotal): int
    {
        if ($fulfilment === 'pickup' || ! $zone) {
            return 0;
        }
        if ($zone->free_above !== null && $itemsTotal >= Money::toFils($zone->free_above)) {
            return 0;
        }

        return Money::toFils($zone->flat_rate);
    }

    private function applyCoupons(Quote $q, ?User $user, ?string $email, ?string $code, int $shipping): void
    {
        $candidates = Coupon::query()->currentlyValid()->where('auto_apply', true)->get();
        $entered = null;
        if ($code = strtoupper(trim((string) $code))) {
            $entered = Coupon::where('code', $code)->first();
            if (! $entered) {
                $q->messages[] = __('shop.coupon.invalid');
            } elseif (! $candidates->contains('id', $entered->id)) {
                $candidates->push($entered);
            }
        }

        $results = $candidates->map(fn (Coupon $c) => $this->coupons->evaluate($c, $q->lines, $user, $email));
        if ($entered && ($r = $results->first(fn ($r) => $r->coupon->id === $entered->id)) && ! $r->ok()) {
            $q->messages[] = $r->error;
        }
        $valid = $results->filter(fn (CouponResult $r) => $r->ok());

        // Stacking: combine all stackable offers, or use the single best non-stackable one — whichever saves more.
        $stackable = $valid->filter(fn ($r) => $r->coupon->stackable);
        $bestSolo = $valid->reject(fn ($r) => $r->coupon->stackable)->sortByDesc(fn ($r) => $r->value($shipping))->first();
        $chosen = $bestSolo && $bestSolo->value($shipping) > $stackable->sum(fn ($r) => $r->value($shipping))
            ? collect([$bestSolo]) : $stackable;

        if ($entered && $valid->contains(fn ($r) => $r->coupon->id === $entered->id) && ! $chosen->contains(fn ($r) => $r->coupon->id === $entered->id)) {
            $q->messages[] = __('shop.coupon.not_combinable');
        }

        foreach ($chosen as $result) {
            $this->allocate($q, $result);
            $q->applied[] = $result;
            $q->freeShipping = $q->freeShipping || $result->freeShipping;
        }
    }

    private function allocate(Quote $q, CouponResult $result): void
    {
        foreach ($result->lineDiscounts as $i => $amount) {
            $line = $q->lines[$i];
            $amount = min($amount, $line->maxDiscount());   // gold-cost floor
            $line->discount += $amount;
            $line->discountsByCode[$result->coupon->code] = ($line->discountsByCode[$result->coupon->code] ?? 0) + $amount;
        }
    }

    private function applyLoyalty(Quote $q, ?User $user, int $points): void
    {
        if (! $user || $points <= 0) {
            return;
        }
        $points = min($points, $user->loyaltyBalance());
        if ($points < (int) Settings::get('loyalty_min_redeem', 100)) {
            $q->messages[] = __('shop.loyalty.minimum', ['points' => Settings::get('loyalty_min_redeem', 100)]);

            return;
        }
        $pointValue = Money::toFils(Settings::get('loyalty_point_value', 0.10));
        $headroom = array_sum(array_map(fn (CartLine $l) => $l->maxDiscount(), $q->lines));
        $points = min($points, intdiv($headroom, max(1, $pointValue)));
        $value = $points * $pointValue;
        if ($value <= 0) {
            return;
        }
        $shares = $this->coupons->allocate(array_filter($q->lines, fn (CartLine $l) => $l->maxDiscount() > 0), $value);
        $applied = 0;
        foreach ($shares as $i => $amount) {
            $amount = min($amount, $q->lines[$i]->maxDiscount());
            $q->lines[$i]->discount += $amount;
            $applied += $amount;
        }
        $q->loyaltyPoints = intdiv($applied, max(1, $pointValue));
        $q->loyaltyDiscount = $applied;
    }

    private function applyGiftCard(Quote $q, ?string $code, int $payable): void
    {
        if (! $code = strtoupper(trim((string) $code))) {
            return;
        }
        $card = GiftCard::where('code', $code)->first();
        if (! $card || ! $card->isUsable()) {
            $q->messages[] = __('shop.gift_card.invalid');

            return;
        }
        $q->giftCard = $card;
        $q->giftCardAmount = min(Money::toFils($card->balance), $payable);
    }
}
