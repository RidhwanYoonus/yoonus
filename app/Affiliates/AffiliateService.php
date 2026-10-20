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
 *   by-gram (gold-rate) item → making charge × affiliate's making %, or grams × fixed AED per gram
 *   fixed-price-per-piece item → item total (excl. VAT, after discount) × affiliate's fixed %
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
            if ($fixed) {
                $base = max(0, Money::toFils($item->subtotal) - Money::toFils($item->discount_amount) - $markup);
                $rate = Money::scaled($affiliate->fixed_commission_percent, 2);
            } elseif ($affiliate->making_commission_type === 'per_gram') {
                $grams = Money::scaled($item->weight_grams, 3) * $item->qty;          // milligrams
                $base = $grams;
                $rate = Money::toFils($affiliate->commission_per_gram);                // fils per gram
            } else {
                $base = max(0, Money::toFils($item->making_fee) - $markup);
                $rate = Money::scaled($affiliate->making_commission_percent, 2);
            }
            $perGram = ! $fixed && $affiliate->making_commission_type === 'per_gram';
            $commission = $perGram ? Money::divRound($base * $rate, 1000) : Money::percentOf($base, $rate);
            if ($commission + $markup <= 0) {
                continue;
            }
            AffiliateCommission::create([
                'affiliate_id' => $affiliate->id,
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'product_name' => $item->product_name.($item->qty > 1 ? ' × '.$item->qty : ''),
                'basis' => $fixed ? 'total' : ($perGram ? 'gram' : 'making'),
                // per gram: base = grams, rate = AED per gram; otherwise base = AED, rate = %
                'base_amount' => $perGram ? number_format($base / 1000, 2, '.', '') : Money::toAed($base),
                'rate_percent' => Money::toAed($rate),
                'commission_amount' => Money::toAed($commission),
                'markup_amount' => Money::toAed($markup),
                'amount' => Money::toAed($commission + $markup),
                'status' => 'pending',
            ]);
        }
        $this->sync($order);
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
