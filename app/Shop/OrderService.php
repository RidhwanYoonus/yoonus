<?php

namespace App\Shop;

use App\Jobs\SendOrderNotification;
use App\Jobs\SendServerSideConversion;
use App\Models\CouponRedemption;
use App\Models\GiftCardTransaction;
use App\Models\LoyaltyEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPriceOverride;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Pricing\Money;
use App\Promotions\LoyaltyService;
use App\Promotions\ReferralService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OrderService
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly LoyaltyService $loyalty,
        private readonly ReferralService $referrals,
    ) {}

    /**
     * Turn a priced quote into an order with a frozen price snapshot.
     *
     * @param  array{email:string,phone:string,name:string,buyer_trn?:?string,address?:?array,pickup_branch_id?:?int,notes?:?string,payment_method:string}  $data
     */
    public function place(Quote $quote, array $data, ?User $user): Order
    {
        if ($quote->isEmpty()) {
            throw new RuntimeException('Cart is empty.');
        }

        return DB::transaction(function () use ($quote, $data, $user) {
            // Lock stock rows so two shoppers can't buy the last piece.
            $products = Product::whereIn('id', array_map(fn ($l) => $l->product->id, $quote->lines))->lockForUpdate()->get()->keyBy('id');
            foreach ($quote->lines as $line) {
                if (($products[$line->product->id]->stock_qty ?? 0) < $line->qty) {
                    throw new OutOfStockException($line->product);
                }
            }

            $order = Order::create([
                'number' => $this->nextNumber(),
                'user_id' => $user?->id,
                'status' => 'pending',
                'payment_method' => $data['payment_method'],
                'payment_status' => $data['payment_method'] === 'cod' ? 'cod' : 'unpaid',
                'email' => strtolower($data['email']),
                'phone' => $data['phone'],
                'customer_name' => $data['name'],
                'buyer_trn' => $data['buyer_trn'] ?? null,
                'locale' => app()->getLocale(),
                'fulfilment' => $quote->fulfilment,
                'shipping_address' => $quote->fulfilment === 'delivery' ? ($data['address'] ?? null) : null,
                'shipping_zone_id' => $quote->fulfilment === 'delivery' ? $quote->zone?->id : null,
                'pickup_branch_id' => $quote->fulfilment === 'pickup' ? ($data['pickup_branch_id'] ?? null) : null,
                'items_subtotal' => Money::toAed($quote->itemsSubtotal),
                'discount_total' => Money::toAed($quote->discountTotal),
                'taxable_amount' => Money::toAed($quote->taxable),
                'vat_total' => Money::toAed($quote->vat),
                // shipping_total is stored incl. its VAT; the payment fee is stored excl. VAT with its VAT alongside.
                'shipping_total' => Money::toAed($quote->shipping + $quote->shippingVat),
                'shipping_vat' => Money::toAed($quote->shippingVat),
                'payment_fee' => Money::toAed($quote->paymentFee),
                'payment_fee_vat' => Money::toAed($quote->paymentFeeVat),
                'grand_total' => Money::toAed($quote->grandTotal),
                'gift_card_amount' => Money::toAed($quote->giftCardAmount),
                'amount_due' => Money::toAed($quote->amountDue),
                'loyalty_points_redeemed' => $quote->loyaltyPoints,
                'coupon_code' => implode(',', $quote->couponCodes()) ?: null,
                'gift_card_code' => $quote->giftCard?->code,
                'notes' => $data['notes'] ?? null,
                // Proof of identity (encrypted number, private file paths) when the order needed it.
                'id_type' => $data['id']['type'] ?? null,
                'id_number' => $data['id']['number'] ?? null,
                'id_last4' => $data['id']['last4'] ?? null,
                'id_expiry' => $data['id']['expiry'] ?? null,
                'id_front_path' => $data['id']['front'] ?? null,
                'id_back_path' => $data['id']['back'] ?? null,
                // Online payments must be completed quickly: the gold price is only held this long.
                'payment_expires_at' => $data['payment_method'] !== 'cod' && $quote->amountDue > 0 ? now()->addMinutes(self::paymentWindow()) : null,
            ]);

            foreach ($quote->lines as $i => $line) {
                $u = $line->unit;
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line->product->id,
                    'product_name' => $line->product->name,
                    'product_name_ar' => $line->product->name_ar,
                    'sku' => $line->product->sku,
                    'karat' => $line->product->goldCategory?->name ?? ($line->product->isFixedPrice() ? 'Fixed price' : '—'),
                    'weight_grams' => $line->product->weight_grams,
                    'qty' => $line->qty,
                    'gold_rate_snapshot' => Money::toAed($u->rateFils),
                    'gold_value' => Money::toAed($u->goldValue * $line->qty),
                    'making_fee' => Money::toAed($u->makingFee * $line->qty),
                    'stone_value' => Money::toAed($u->stoneValue * $line->qty),
                    'wastage' => Money::toAed($u->wastage * $line->qty),
                    'rounding' => Money::toAed($u->rounding * $line->qty),
                    'subtotal' => Money::toAed($line->subtotal()),
                    'discount_amount' => Money::toAed($line->discount),
                    'vat_percent' => $u->vatPercent(),
                    'vat_amount' => Money::toAed($quote->lineVat[$i]),
                    'line_total' => Money::toAed($quote->lineTotal($i)),
                    'coupon_code_applied' => implode(',', array_keys(array_filter($line->discountsByCode))) ?: null,
                ]);
                $products[$line->product->id]->decrement('stock_qty', $line->qty);
            }

            foreach ($quote->applied as $result) {
                $amount = array_sum(array_map(fn ($l) => $l->discountsByCode[$result->coupon->code] ?? 0, $quote->lines));
                CouponRedemption::create([
                    'coupon_id' => $result->coupon->id,
                    'order_id' => $order->id,
                    'user_id' => $user?->id,
                    'email' => $order->email,
                    'discount_amount' => Money::toAed($amount),
                ]);
                $result->coupon->increment('times_used');
            }

            if ($quote->loyaltyPoints > 0 && $user) {
                LoyaltyEntry::create(['user_id' => $user->id, 'order_id' => $order->id, 'points' => -$quote->loyaltyPoints, 'reason' => 'redeemed']);
            }

            if ($quote->giftCard && $quote->giftCardAmount > 0) {
                $card = $quote->giftCard->newQuery()->lockForUpdate()->find($quote->giftCard->id);
                $debit = min($quote->giftCardAmount, Money::toFils($card->balance));
                if ($debit !== $quote->giftCardAmount) {
                    throw new RuntimeException('Gift card balance changed; please review your order.');
                }
                $card->update(['balance' => Money::toAed(Money::toFils($card->balance) - $debit)]);
                GiftCardTransaction::create(['gift_card_id' => $card->id, 'order_id' => $order->id, 'amount' => Money::toAed(-$debit), 'channel' => 'online']);
            }

            $this->history($order, null, 'pending', null, 'Order placed');

            // Fully covered by a gift card: nothing left to collect.
            if ($quote->amountDue === 0) {
                DB::afterCommit(fn () => $this->markPaid($order->fresh(), 'giftcard', 'GC-'.$order->number));
            } elseif ($data['payment_method'] === 'cod') {
                DB::afterCommit(fn () => SendOrderNotification::dispatch($order->id, 'placed'));
            }
            // Online payments: no "order placed" message — the customer hears from us once it is paid.

            return $order;
        });
    }

    /** Minutes an unpaid online order keeps its gold price (Settings → Payments). */
    public static function paymentWindow(): int
    {
        return max(10, min(240, (int) \App\Support\Settings::get('payment_window_minutes', 30)));
    }

    /** Unpaid online orders whose payment time has run out (also older orders saved before the window existed). */
    public static function expiredUnpaid(): \Illuminate\Database\Eloquent\Builder
    {
        return Order::where('status', 'pending')->where('payment_status', 'unpaid')->where('payment_method', '!=', 'cod')
            ->where(fn ($q) => $q->where('payment_expires_at', '<', now())
                ->orWhere(fn ($q) => $q->whereNull('payment_expires_at')->where('created_at', '<', now()->subMinutes(self::paymentWindow()))));
    }

    /**
     * The customer did not pay in time: cancel the order and give back everything it was holding
     * (stock, coupon use, loyalty points, gift-card balance) so the price can't be claimed later.
     */
    public function expireUnpaid(Order $order): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('orders', 'payment_expires_at')) {
            return false;   // database update not run yet
        }

        return DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->with('items.product')->find($order->id);
            if (! $order || ! self::expiredUnpaid()->whereKey($order->id)->exists()) {
                return false;
            }
            $order->forceFill(['status' => 'cancelled', 'payment_status' => 'expired'])->save();
            $this->restock($order);
            foreach (CouponRedemption::where('order_id', $order->id)->with('coupon')->get() as $redemption) {
                $redemption->coupon?->decrement('times_used');
                $redemption->delete();
            }
            $points = -1 * (int) LoyaltyEntry::where('order_id', $order->id)->where('reason', 'redeemed')->sum('points');
            if ($points > 0 && $order->user_id) {
                LoyaltyEntry::create(['user_id' => $order->user_id, 'order_id' => $order->id, 'points' => $points, 'reason' => 'adjustment']);
            }
            foreach (GiftCardTransaction::where('order_id', $order->id)->where('amount', '<', 0)->get() as $tx) {
                $card = $tx->giftCard()->lockForUpdate()->first();
                if ($card) {
                    $card->update(['balance' => Money::toAed(Money::toFils($card->balance) - Money::toFils($tx->amount))]);
                    GiftCardTransaction::create(['gift_card_id' => $card->id, 'order_id' => $order->id, 'amount' => Money::toAed(-Money::toFils($tx->amount)), 'channel' => 'online']);
                }
            }
            $this->history($order, 'pending', 'cancelled', null, 'Payment not completed within '.self::paymentWindow().' minutes — order cancelled, gold price released, stock returned.');

            return true;
        });
    }

    public function nextNumber(): string
    {
        do {
            $n = 'JW'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (Order::where('number', $n)->exists());

        return $n;
    }

    /** Idempotent: webhook retries and duplicate return-URL hits do nothing. */
    public function markPaid(Order $order, string $gateway, ?string $reference, array $payload = []): Order
    {
        return DB::transaction(function () use ($order, $gateway, $reference, $payload) {
            $order = Order::lockForUpdate()->find($order->id);
            if ($order->isPaid()) {
                return $order;
            }
            $late = $order->payment_status === 'expired';
            if ($late) {
                // The bank paid after the time ran out (the customer was already on the payment page).
                // Take the money at the price they were shown, reserve the stock again and tell staff.
                foreach ($order->items()->with('product')->get() as $item) {
                    $item->product?->decrement('stock_qty', $item->qty);
                }
                $order->status = 'pending';
            }
            Payment::firstOrCreate(
                ['gateway' => $gateway, 'reference' => $reference ?? $order->number],
                ['order_id' => $order->id, 'status' => 'captured', 'amount' => $order->amount_due, 'payload' => $payload],
            );
            $from = $order->status;
            $order->forceFill([
                'payment_status' => 'paid',
                'payment_reference' => $reference,
                'paid_at' => now(),
                'status' => in_array($order->status, ['pending'], true) ? 'paid' : $order->status,
            ])->save();
            if ($from !== $order->status || $late) {
                $this->history($order, $late ? 'cancelled' : $from, $order->status, null, 'Payment confirmed via '.$gateway
                    .($late ? ' — after the payment time had run out. Stock was reserved again: please check it is still available.' : ''));
            }

            $this->invoices->issue($order);
            $this->loyalty->earn($order);
            $this->referrals->rewardFor($order);

            DB::afterCommit(function () use ($order) {
                SendOrderNotification::dispatch($order->id, 'paid');
                SendServerSideConversion::dispatch($order->id);
            });

            return $order;
        });
    }

    public function transition(Order $order, string $to, ?User $by = null, ?string $note = null): Order
    {
        if (! $order->canTransitionTo($to)) {
            throw new RuntimeException("Cannot move order from {$order->status} to {$to}.");
        }
        if ($to === 'paid') {
            return $this->markPaid($order, $order->payment_method, 'manual-'.$order->number);
        }
        if ($to === 'delivered' && HandoverOtp::required($order)) {
            throw new RuntimeException('Enter the customer\'s handover code first (or override it with a reason).');
        }

        return DB::transaction(function () use ($order, $to, $by, $note) {
            $from = $order->status;
            $order->update(['status' => $to]);
            $this->history($order, $from, $to, $by, $note);

            if ($to === 'cancelled') {
                $this->restock($order);
            }
            if ($to === 'delivered') {
                $this->hideSoldOut($order);
            }
            if ($to === 'delivered' && $order->payment_method === 'cod' && ! $order->isPaid()) {
                // Cash collected on delivery.
                $this->markPaid($order, 'cod', 'cod-'.$order->number);
                $order->refresh();
            }
            DB::afterCommit(fn () => SendOrderNotification::dispatch($order->id, $to));
            if (HandoverOtp::enabled() && HandoverOtp::sendsOn($order, $to) && ! $order->handover_verified_at) {
                DB::afterCommit(fn () => app(HandoverOtp::class)->issue($order));
            }

            return $order;
        });
    }

    /**
     * Once the last piece has been handed over, take it out of the shop so nobody can order
     * stock we no longer have. Adding stock in the admin shows it again (Product::booted).
     */
    private function hideSoldOut(Order $order): void
    {
        foreach ($order->items()->with('product')->get() as $item) {
            $p = $item->product;
            if ($p && $p->stock_qty <= 0 && ! $p->hidden_after_sale && ! $p->hasOpenOrders($order->id)) {
                $p->forceFill(['hidden_after_sale' => true])->saveQuietly();
            }
        }
        \App\Support\StorefrontCache::flushPriceDependent();
    }

    /** Manual price override (before payment), logged with reason and admin. */
    public function overrideLine(OrderItem $item, string $newLineTotal, string $reason, ?User $by): void
    {
        $order = $item->order;
        if ($order->isPaid() || $order->invoice_number) {
            throw new RuntimeException('Paid or invoiced orders cannot be re-priced. Issue a credit note instead.');
        }

        DB::transaction(function () use ($item, $order, $newLineTotal, $reason, $by) {
            $newTotal = Money::toFils($newLineTotal);
            $vatBp = Money::scaled($item->vat_percent, 2);
            $taxable = Money::divRound($newTotal * 10000, 10000 + $vatBp);
            $floor = Money::toFils($item->gold_value);
            if ($taxable < $floor) {
                throw new RuntimeException('Override would take the line below its gold value (AED '.$item->gold_value.').');
            }

            OrderPriceOverride::create([
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'old_line_total' => $item->line_total,
                'new_line_total' => Money::toAed($newTotal),
                'reason' => $reason,
                'changed_by' => $by?->id,
            ]);

            $item->update([
                'discount_amount' => Money::toAed(Money::toFils($item->subtotal) - $taxable),
                'vat_amount' => Money::toAed($newTotal - $taxable),
                'line_total' => Money::toAed($newTotal),
            ]);

            $this->retotal($order->fresh('items'));
        });
    }

    public function retotal(Order $order): void
    {
        $items = $order->items;
        $taxable = $items->sum(fn ($i) => Money::toFils($i->subtotal) - Money::toFils($i->discount_amount));
        $vat = $items->sum(fn ($i) => Money::toFils($i->vat_amount));
        $grand = $taxable + $vat + Money::toFils($order->shipping_total) + Money::toFils($order->payment_fee) + Money::toFils($order->payment_fee_vat);
        $order->update([
            'discount_total' => Money::toAed($items->sum(fn ($i) => Money::toFils($i->discount_amount))),
            'taxable_amount' => Money::toAed($taxable),
            'vat_total' => Money::toAed($vat),
            'grand_total' => Money::toAed($grand),
            'amount_due' => Money::toAed(max(0, $grand - Money::toFils($order->gift_card_amount))),
        ]);
    }

    private function restock(Order $order): void
    {
        foreach ($order->items as $item) {
            $item->product?->increment('stock_qty', $item->qty);
        }
    }

    public function history(Order $order, ?string $from, string $to, ?User $by, ?string $note): void
    {
        OrderStatusHistory::create(['order_id' => $order->id, 'from_status' => $from, 'to_status' => $to, 'changed_by' => $by?->id, 'note' => $note]);
    }
}
