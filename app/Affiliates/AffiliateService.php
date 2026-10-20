<?php

namespace App\Affiliates;

use App\Models\Affiliate;
use App\Models\AffiliateAdjustment;
use App\Models\AffiliateCampaign;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Pricing\Money;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Commission per order line:
 *   same AED per piece for every item (one simple rate), or per item type:
 *   by-gram (gold-rate) item → making charge × affiliate's making %, or grams × fixed AED per gram
 *   fixed-price-per-piece item → item total (excl. VAT, after discount) × affiliate's fixed %, or AED per piece
 * plus, on the white page, the full mark-up the affiliate added. It is "pending" until the
 * order is delivered / collected, then "available" to collect at the store; cancelled or
 * refunded orders void it.
 */
class AffiliateService
{
    /** Called when the order is placed. */
    public function record(Order $order): void
    {
        $affiliate = $order->affiliate;
        if (! $affiliate || AffiliateCommission::where('order_id', $order->id)->exists()) {
            return;
        }
        // Level from this month's sales before this order, and campaign boosters running now.
        $level = $affiliate->level(max(0, $affiliate->monthlySales() - Money::toFils($order->taxable_amount)));
        $boosters = rescue(fn () => AffiliateCampaign::running()->where('type', 'booster')->get()
            ->filter(fn (AffiliateCampaign $c) => $c->appliesTo($affiliate)), collect(), false);
        $earned = 0;
        $items = $order->items()->with('product')->get();
        $couponShares = $this->couponShares($order, $affiliate, $items);
        foreach ($items as $item) {
            $markup = Money::toFils($item->affiliate_markup);
            $fixed = $item->product ? $item->product->isFixedPrice() : $item->karat === 'Fixed price';
            // The affiliate's own code: commission is worked out on the price before that discount,
            // then the discount is taken from the affiliate's money on this line.
            $share = $couponShares[$item->id] ?? 0;
            $calc = clone $item;
            $calc->discount_amount = Money::toAed(max(0, Money::toFils($item->discount_amount) - $share));
            [$basis, $baseShown, $rateShown, $commission] = $this->line($affiliate, $calc, $fixed, $markup);
            [$bonus, $bonusNote] = $this->extras($commission, $level, $boosters, $item->product, $fixed);
            $deduction = min($share, $commission + $bonus + $markup);
            if ($commission + $bonus + $markup <= 0) {
                continue;
            }
            $earned += max(0, $commission + $bonus - $deduction);
            AffiliateCommission::create([
                'affiliate_id' => $affiliate->id,
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'product_name' => $item->product_name.($item->qty > 1 ? ' × '.$item->qty : ''),
                'basis' => $basis,
                'base_amount' => $baseShown,
                'rate_percent' => $rateShown,
                'commission_amount' => Money::toAed($commission),
                'markup_amount' => Money::toAed($markup),
                ...(Schema::hasColumn('affiliate_commissions', 'bonus_amount') ? ['bonus_amount' => Money::toAed($bonus), 'bonus_note' => $bonusNote] : []),
                ...(Schema::hasColumn('affiliate_commissions', 'coupon_deduction') ? ['coupon_deduction' => Money::toAed($deduction)] : []),
                'amount' => Money::toAed($commission + $bonus + $markup - $deduction),
                'status' => 'pending',
            ]);
        }
        $this->recruiterShare($order, $affiliate, $earned);
        $this->sync($order);
        DB::afterCommit(fn () => $this->notify($affiliate, 'new_sale', $order));
    }

    /**
     * Extra on top of the commission: level % (Settings → Affiliates) and campaign boosters (× commission).
     *
     * @return array{0: int, 1: ?string}
     */
    private function extras(int $commission, array $level, $boosters, $product, bool $fixed = false): array
    {
        if ($commission <= 0) {
            return [0, null];
        }
        $bonus = 0;
        $notes = [];
        if ($level['extra_bp'] > 0) {
            $bonus += Money::percentOf($commission, $level['extra_bp']);
            $notes[] = $level['name'].' +'.rtrim(rtrim(Money::toAed($level['extra_bp']), '0'), '.').'%';
        }
        foreach ($boosters as $b) {
            if (! $b->covers($product, $fixed)) {
                continue;
            }
            $bonus += Money::percentOf($commission, Money::scaled(max(0, (float) $b->multiplier - 1) * 100, 2));
            $notes[] = $b->name.' ×'.rtrim(rtrim((string) $b->multiplier, '0'), '.');
        }

        return [$bonus, $notes ? implode(' · ', $notes) : null];
    }

    /**
     * Discount the customer got with this affiliate's own coupon code, per order line (fils),
     * split over the lines in proportion to their discount.
     *
     * @return array<int, int>
     */
    private function couponShares(Order $order, Affiliate $affiliate, $items): array
    {
        $redemption = \App\Models\CouponRedemption::where('order_id', $order->id)
            ->whereHas('coupon', fn ($q) => $q->where('affiliate_id', $affiliate->id))->with('coupon')->first();
        if (! $redemption) {
            return [];
        }
        $total = Money::toFils($redemption->discount_amount);
        $lines = $items->filter(fn ($i) => str_contains((string) $i->coupon_code_applied, $redemption->coupon->code) && Money::toFils($i->discount_amount) > 0);
        $weight = $lines->sum(fn ($i) => Money::toFils($i->discount_amount));
        $shares = [];
        $left = $total;
        foreach ($lines->values() as $n => $i) {
            $part = $n === $lines->count() - 1 ? $left : intdiv($total * Money::toFils($i->discount_amount), max(1, $weight));
            $shares[$i->id] = min($part, Money::toFils($i->discount_amount));
            $left -= $shares[$i->id];
        }

        return $shares;
    }

    /** The affiliate who recruited this one gets a share of the commission (paid by the shop, not deducted). */
    private function recruiterShare(Order $order, Affiliate $affiliate, int $earned): void
    {
        $pct = Money::scaled(Settings::get('affiliate_recruiter_percent', 10), 2);
        $recruiter = $affiliate->recruited_by_id ? $affiliate->recruitedBy : null;
        if (! Affiliate::teamEnabled() || ! $recruiter || ! $recruiter->is_active || $pct <= 0 || $earned <= 0) {
            return;
        }
        $share = Money::percentOf($earned, $pct);
        AffiliateCommission::create([
            'affiliate_id' => $recruiter->id, 'order_id' => $order->id, 'order_item_id' => null,
            'product_name' => 'Team sale by '.$affiliate->code, 'basis' => 'team',
            'base_amount' => Money::toAed($earned), 'rate_percent' => Money::toAed($pct),
            'commission_amount' => Money::toAed($share), 'markup_amount' => 0, 'amount' => Money::toAed($share), 'status' => 'pending',
        ]);
    }

    /** Commission for a sale made at the counter (customer sent by the affiliate). Ready to collect at once. */
    public function storeSale(Affiliate $affiliate, array $data, ?User $by): AffiliateAdjustment
    {
        $fixed = ($data['item_type'] ?? 'gold') === 'fixed';
        $qty = max(1, (int) ($data['pieces'] ?? 1));
        $item = (object) [
            'qty' => $qty,
            'weight_grams' => (float) ($data['weight_grams'] ?? 0) / $qty,
            'making_fee' => (string) ($data['making_charge'] ?? 0),
            'subtotal' => (string) ($data['price'] ?? 0),
            'discount_amount' => '0',
        ];
        [$basis, $base, $rate, $commission] = $this->line($affiliate, $item, $fixed, 0);
        if ($commission <= 0) {
            throw new RuntimeException('This sale gives no commission with the affiliate\'s current rates.');
        }
        $line = AffiliateCommission::make(['basis' => $basis, 'base_amount' => $base, 'rate_percent' => $rate]);
        $adjustment = AffiliateAdjustment::create([
            'affiliate_id' => $affiliate->id, 'type' => 'store_sale', 'amount' => Money::toAed($commission), 'status' => 'available',
            'description' => 'In-store sale'.(filled($data['receipt'] ?? null) ? ' · receipt '.$data['receipt'] : '').' — '.$line->calculation(),
            'details' => $data, 'created_by' => $by?->id,
        ]);
        $this->notify($affiliate, 'store_sale', null, $commission);

        return $adjustment;
    }

    /** Target campaigns: once delivered sales in the period reach the target, add the bonus (only once). */
    public function evaluateTargets(Affiliate $affiliate): void
    {
        $targets = rescue(fn () => AffiliateCampaign::where('type', 'target')->where('is_active', true)
            ->where('starts_at', '<=', now())->where('ends_at', '>=', now()->subDays(30))->get(), collect(), false);
        foreach ($targets as $t) {
            if (! $t->appliesTo($affiliate) || AffiliateAdjustment::where('affiliate_id', $affiliate->id)->where('campaign_id', $t->id)->exists()) {
                continue;
            }
            [$pieces, $sales] = $this->deliveredIn($affiliate, $t->starts_at, $t->ends_at);
            $reached = $t->target_metric === 'sales' ? $sales >= Money::toFils($t->target_value) : $pieces >= (float) $t->target_value;
            if ($reached && (float) $t->bonus_amount > 0) {
                AffiliateAdjustment::create([
                    'affiliate_id' => $affiliate->id, 'type' => 'bonus', 'amount' => $t->bonus_amount, 'status' => 'available',
                    'description' => 'Target reached: '.$t->name, 'campaign_id' => $t->id,
                ]);
                $this->notify($affiliate, 'bonus', null, Money::toFils($t->bonus_amount), $t->name);
            }
        }
    }

    /** @return array{0: int, 1: int} pieces, sales in fils */
    public function deliveredIn(Affiliate $affiliate, $from, $to): array
    {
        $orders = Order::where('affiliate_id', $affiliate->id)->where('status', 'delivered')->whereBetween('created_at', [$from, $to]);

        return [
            (int) OrderItem::whereIn('order_id', (clone $orders)->select('id'))->sum('qty'),
            Money::toFils((string) (clone $orders)->sum('taxable_amount')),
        ];
    }

    /** WhatsApp + email to the affiliate (new sale, ready to collect, bonus). */
    public function notify(Affiliate $affiliate, string $event, ?Order $order = null, int $amount = 0, string $extra = ''): void
    {
        if (! Settings::bool('affiliate_notifications')) {
            return;
        }
        try {
            $user = $affiliate->user;
            $locale = $user?->locale === 'ar' ? 'ar' : 'en';
            $amount = $amount ?: ($order ? Money::toFils((string) AffiliateCommission::where('order_id', $order->id)->where('affiliate_id', $affiliate->id)->sum('amount')) : 0);
            if ($amount <= 0) {
                return;
            }
            $text = __('shop.affiliate.notify.'.$event, ['amount' => Money::format($amount), 'order' => $order?->number, 'name' => $extra], $locale);
            $messenger = app(\App\Messaging\Messenger::class);
            $messenger->email($user?->email, new \App\Mail\ShopMessage($text, [$text], __('shop.affiliate.title', [], $locale), route('affiliate.dashboard'), $locale), 'affiliate_'.$event);
            if ($user?->phone) {
                $messenger->whatsapp($user->phone, $text.' '.route('affiliate.dashboard'), 'affiliate_'.$event);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * The affiliate's commission (fils) on a cart line before any discount, worked out on the
     * shop's own price (without white-page mark-up). Used to size the discount of their coupon code.
     */
    public function commissionForCartLine(Affiliate $affiliate, \App\Shop\CartLine $line): int
    {
        $base = app(\App\Pricing\PricingService::class)->base($line->product);
        $item = (object) [
            'qty' => $line->qty,
            'weight_grams' => (string) $line->product->weight_grams,
            'making_fee' => Money::toAed($base->makingFee * $line->qty),
            'subtotal' => Money::toAed($base->subtotal * $line->qty),
            'discount_amount' => '0',
        ];

        return $this->line($affiliate, $item, $line->product->isFixedPrice(), 0)[3];
    }

    /**
     * One order line → [basis, base shown, rate shown, commission in fils].
     *   piece  : base = pieces,  rate = AED per piece
     *   gram   : base = grams,   rate = AED per gram
     *   making : base = AED making charge (without mark-up), rate = %
     *   total  : base = AED item price (after discount, without mark-up), rate = %
     */
    private function line(Affiliate $a, $item, bool $fixed, int $markup): array
    {
        $perPiece = $a->commission_mode === 'per_piece' ? $a->commission_per_piece
            : ($fixed && $a->fixed_commission_type === 'per_piece' ? $a->fixed_commission_amount : null);
        if ($perPiece !== null) {
            $rate = Money::toFils($perPiece);

            return ['piece', (string) $item->qty, Money::toAed($rate), $rate * $item->qty];
        }
        if ($fixed) {
            $base = max(0, Money::toFils($item->subtotal) - Money::toFils($item->discount_amount) - $markup);
            $rate = Money::scaled($a->fixed_commission_percent, 2);

            return ['total', Money::toAed($base), Money::toAed($rate), Money::percentOf($base, $rate)];
        }
        if ($a->making_commission_type === 'per_gram') {
            $mg = Money::scaled($item->weight_grams, 3) * $item->qty;
            $rate = Money::toFils($a->commission_per_gram);

            return ['gram', number_format($mg / 1000, 2, '.', ''), Money::toAed($rate), Money::divRound($mg * $rate, 1000)];
        }
        $base = max(0, Money::toFils($item->making_fee) - $markup);
        $rate = Money::scaled($a->making_commission_percent, 2);

        return ['making', Money::toAed($base), Money::toAed($rate), Money::percentOf($base, $rate)];
    }

    /** Follow the order: delivered → available, cancelled / refunded → void, otherwise pending. */
    public function sync(Order $order): void
    {
        if (! $order->affiliate_id) {
            return;
        }
        $status = match (true) {
            in_array($order->status, ['cancelled', 'refunded'], true) => 'void',
            $order->status === 'delivered' => 'available',
            default => 'pending',
        };
        $changed = AffiliateCommission::where('order_id', $order->id)->where('status', '!=', $status)->get()
            ->each(fn (AffiliateCommission $c) => $c->update([
                'status' => $status,
                'available_at' => $status === 'available' ? ($c->available_at ?? now()) : null,
            ]));
        if ($status === 'available' && $changed->isNotEmpty()) {
            DB::afterCommit(function () use ($changed, $order) {
                foreach ($changed->groupBy('affiliate_id') as $rows) {
                    $affiliate = $rows->first()->affiliate;
                    $this->notify($affiliate, 'ready', $order, Money::toFils((string) $rows->sum('amount')));
                    $this->evaluateTargets($affiliate);
                }
            });
        }
    }

    /** Staff hand over cash at the store: it is taken off the affiliate's balance. */
    public function payout(Affiliate $affiliate, string $amount, ?User $by, ?int $branchId = null, ?string $reference = null, ?string $note = null): AffiliatePayout
    {
        return DB::transaction(function () use ($affiliate, $amount, $by, $branchId, $reference, $note) {
            $affiliate = Affiliate::lockForUpdate()->findOrFail($affiliate->id);
            $fils = Money::toFils($amount);
            if ($fils <= 0) {
                throw new RuntimeException('Enter an amount above zero.');
            }
            if ($fils > $affiliate->totals()['balance']) {
                throw new RuntimeException('That is more than the balance ready to collect ('.Money::format($affiliate->totals()['balance']).').');
            }

            return AffiliatePayout::create([
                'affiliate_id' => $affiliate->id, 'amount' => Money::toAed($fils), 'paid_at' => now(),
                'paid_by' => $by?->id, 'branch_id' => $branchId, 'reference' => $reference, 'note' => $note,
            ]);
        });
    }

    public static function newCode(string $name): string
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', \Illuminate\Support\Str::ascii($name)) ?: 'AFF', 0, 8));
        do {
            $code = $base.random_int(10, 99);
        } while (Affiliate::where('code', $code)->exists());

        return $code;
    }
}
