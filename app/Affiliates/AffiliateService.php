<?php

namespace App\Affiliates;

use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\Order;
use App\Models\User;
use App\Pricing\Money;
use Illuminate\Support\Facades\DB;
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
        foreach ($order->items()->with('product')->get() as $item) {
            $markup = Money::toFils($item->affiliate_markup);
            $fixed = $item->product ? $item->product->isFixedPrice() : $item->karat === 'Fixed price';
            [$basis, $baseShown, $rateShown, $commission] = $this->line($affiliate, $item, $fixed, $markup);
            if ($commission + $markup <= 0) {
                continue;
            }
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
                'amount' => Money::toAed($commission + $markup),
                'status' => 'pending',
            ]);
        }
        $this->sync($order);
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
        AffiliateCommission::where('order_id', $order->id)->where('status', '!=', $status)->get()
            ->each(fn (AffiliateCommission $c) => $c->update([
                'status' => $status,
                'available_at' => $status === 'available' ? ($c->available_at ?? now()) : null,
            ]));
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
