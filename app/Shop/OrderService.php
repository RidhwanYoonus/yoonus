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

            // Affiliate who sent this customer (never the customer themselves).
            $affiliate = $data['affiliate'] ?? null;
            // An affiliate's coupon code counts for that affiliate, even without their link.
            $couponAffiliate = collect($quote->applied)->map(fn ($r) => $r->coupon->affiliate_id ?? null)->filter()->first();
            if ($couponAffiliate && $couponAffiliate !== $affiliate?->id) {
                $affiliate = \App\Models\Affiliate::where('is_active', true)->find($couponAffiliate);
                $data['affiliate_white'] = false;
            }
            if ($affiliate && ($affiliate->user_id === $user?->id || strtolower((string) $affiliate->user?->email) === strtolower($data['email']))) {
                $affiliate = null;
            }
            $white = $affiliate && ! empty($data['affiliate_white']);
            $pricing = app(\App\Pricing\PricingService::class);

            $order = Order::create([
                'number' => $this->nextNumber(),
                // Only written for affiliate orders, so checkout keeps working before the database update.
                ...($affiliate ? ['affiliate_id' => $affiliate->id, 'affiliate_white_page' => $white] : []),
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
                ...(isset($data['location']) && \Illuminate\Support\Facades\Schema::hasColumn('orders', 'delivery_lat') ? ['delivery_lat' => $data['location'][0], 'delivery_lng' => $data['location'][1]] : []),
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
                // Written only once "Update database now" has added the columns.
                ...(self::hasColumn('tax_free_requested') ? ['id_country' => $data['id']['country'] ?? null, 'tax_free_requested' => (bool) ($data['tax_free'] ?? false)] : []),
                // Online payments must be completed quickly: the gold price is only held this long.
                // (Settings → Payments → "No time limit": the order simply waits for payment.)
                'payment_expires_at' => $data['payment_method'] !== 'cod' && $quote->amountDue > 0 && self::paymentWindow() > 0 ? now()->addMinutes(self::paymentWindow()) : null,
            ]);

            foreach ($quote->lines as $i => $line) {
                $u = $line->unit;
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line->product->id,
                    'product_name' => $line->product->name,
                    'product_name_ar' => $line->product->name_ar,
                    'options' => $line->options ?: null,
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
                    // White page: the part of the price the affiliate added on top of the shop price.
                    ...($white && $pricing->markup()->isActive()
                        ? ['affiliate_markup' => Money::toAed(max(0, $u->subtotal - $pricing->base($line->product)->subtotal) * $line->qty),
                            ...(self::hasColumn('affiliate_stone_markup', 'order_items') ? ['affiliate_stone_markup' => Money::toAed(max(0, $u->stoneValue - $pricing->base($line->product)->stoneValue) * $line->qty)] : [])] : []),
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
            app(\App\Affiliates\AffiliateService::class)->record($order);

            // Fully covered by a gift card: nothing left to collect.
            if ($quote->amountDue === 0) {
                DB::afterCommit(fn () => $this->markPaid($order->fresh(), 'giftcard', 'GC-'.$order->number));
            } elseif ($data['payment_method'] === 'cod') {
                DB::afterCommit(function () use ($order) {
                    SendOrderNotification::dispatch($order->id, 'placed');
                    rescue(fn () => \App\Support\StaffAlerts::newOrder($order->fresh(), 'cash on delivery'), null, false);
                });
            }
            // Online payments: no "order placed" message — the customer hears from us once it is paid.

            return $order;
        });
    }

    /** Minutes an unpaid online order keeps its gold price (Settings → Payments); 0 = no time limit. */
    public static function paymentWindow(): int
    {
        $minutes = (int) \App\Support\Settings::get('payment_window_minutes', 30);

        return $minutes === 0 ? 0 : max(10, min(240, $minutes));
    }

    /** Unpaid online orders whose payment time has run out (also older orders saved before the window existed). */
    public static function expiredUnpaid(): \Illuminate\Database\Eloquent\Builder
    {
        return Order::where('status', 'pending')->where('payment_status', 'unpaid')->where('payment_method', '!=', 'cod')
            ->where(fn ($q) => $q->where('payment_expires_at', '<', now())
                ->when(self::paymentWindow() > 0, fn ($q) => $q->orWhere(fn ($q) => $q->whereNull('payment_expires_at')->where('created_at', '<', now()->subMinutes(self::paymentWindow())))));
    }

    /**
     * The customer did not pay in time: cancel the order and give back everything it was holding
     * (stock, coupon use, loyalty points, gift-card balance) so the price can't be claimed later.
     */
    public function expireUnpaid(Order $order, ?string $reason = null): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('orders', 'payment_expires_at')) {
            return false;   // database update not run yet
        }

        return DB::transaction(function () use ($order, $reason) {
            $order = Order::lockForUpdate()->with('items.product')->find($order->id);
            $waiting = $order && $order->status === 'pending' && $order->payment_status === 'unpaid' && $order->payment_method !== 'cod';
            if (! $waiting || (! $reason && ! self::expiredUnpaid()->whereKey($order->id)->exists())) {
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
            app(\App\Affiliates\AffiliateService::class)->sync($order);
            $this->history($order, 'pending', 'cancelled', null, $reason ?? 'Payment not completed within '.self::paymentWindow().' minutes — order cancelled, gold price released, stock returned.');

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
    /** Paid too late and the piece is no longer available: record the money, keep the order cancelled, ask staff to refund. */
    private function paidButSold(Order $order, string $gateway, ?string $reference, array $payload, array $names): Order
    {
        Payment::firstOrCreate(
            ['gateway' => $gateway, 'reference' => $reference ?? $order->number],
            ['order_id' => $order->id, 'status' => 'captured', 'amount' => $order->amount_due, 'payload' => $payload],
        );
        $order->forceFill(['payment_status' => 'paid', 'payment_reference' => $reference, 'paid_at' => now(), 'status' => 'cancelled'])->save();
        $this->history($order, 'cancelled', 'cancelled', null, 'REFUND NEEDED: paid via '.$gateway.' after the payment time ran out, but '
            .implode(', ', $names).' was sold to another customer meanwhile. Nothing was sold twice; please refund the customer.');
        DB::afterCommit(function () use ($order, $names) {
            rescue(fn () => \App\Support\StaffAlerts::refundNeeded($order->fresh(), $names), null, false);
        });

        return $order;
    }

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
                // If every piece is still here: take the order at the price they were shown and reserve the stock again.
                // If a piece was sold to someone else meanwhile: never sell it twice — the order stays cancelled and
                // staff are told to refund the payment.
                $items = $order->items()->get();
                $stock = Product::whereIn('id', $items->pluck('product_id')->filter())->lockForUpdate()->get()->keyBy('id');
                $gone = $items->filter(fn ($i) => $i->product_id && ($stock[$i->product_id]->stock_qty ?? 0) < $i->qty);
                if ($gone->isNotEmpty()) {
                    return $this->paidButSold($order, $gateway, $reference, $payload, $gone->pluck('product_name')->all());
                }
                foreach ($items as $item) {
                    $stock[$item->product_id]?->decrement('stock_qty', $item->qty);
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

            // The bag was kept while the customer paid; now it is done.
            \App\Models\Cart::where('order_id', $order->id)->whereNull('converted_at')->update(['converted_at' => now()]);
            app(\App\Affiliates\AffiliateService::class)->sync($order);
            $this->invoices->issue($order);
            $this->loyalty->earn($order);
            $this->referrals->rewardFor($order);

            DB::afterCommit(function () use ($order, $gateway) {
                SendOrderNotification::dispatch($order->id, 'paid');
                rescue(fn () => \App\Support\StaffAlerts::newOrder($order->fresh(), 'paid · '.$gateway), null, false);
                SendServerSideConversion::dispatch($order->id);
            });

            return $order;
        });
    }

    /** $at: when it really happened (staff may enter a past date/time, e.g. delivered yesterday); now when empty. */
    public function transition(Order $order, string $to, ?User $by = null, ?string $note = null, ?\Illuminate\Support\Carbon $at = null): Order
    {
        $at = $at && $at->lessThan(now()) ? $at : null;
        if (! $order->canTransitionTo($to)) {
            throw new RuntimeException("Cannot move order from {$order->status} to {$to}.");
        }
        if ($to === 'paid') {
            return $this->markPaid($order, $order->payment_method, 'manual-'.$order->number);
        }
        if ($to === 'delivered' && HandoverOtp::required($order)) {
            throw new RuntimeException('Enter the customer\'s handover code first (or override it with a reason).');
        }

        return DB::transaction(function () use ($order, $to, $by, $note, $at) {
            $from = $order->status;
            $order->update(['status' => $to]);
            $this->history($order, $from, $to, $by, $note, $at);

            if ($to === 'cancelled') {
                $this->restock($order);
            }
            app(\App\Affiliates\AffiliateService::class)->sync($order);
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
     * Status the order can go back to with "Undo last status" (the one before the latest change),
     * or null when undoing is not allowed: never back to unpaid, never out of a refund, and not for
     * orders cancelled automatically because payment time ran out.
     */
    public function revertTarget(Order $order): ?string
    {
        // The latest real change into the current status (skipping earlier undos, so undo can go back step by step).
        $last = $order->statusHistory()->where('to_status', $order->status)
            ->where(fn ($q) => $q->whereNull('note')->orWhere('note', 'not like', 'Undone%'))->first();
        $to = $last?->from_status;
        if (! $to || $to === $order->status || $to === 'pending' || $order->status === 'refunded' || $order->payment_status === 'expired') {
            return null;
        }

        return $to;
    }

    /** Admin fixes a status changed by mistake, undoing what that change did. */
    public function revertStatus(Order $order, User $by, string $reason): Order
    {
        $to = $this->revertTarget($order);
        if (! $to) {
            throw new RuntimeException('This status change cannot be undone here.');
        }

        return DB::transaction(function () use ($order, $to, $by, $reason) {
            $order = Order::lockForUpdate()->with('items.product')->find($order->id);
            $from = $order->status;
            if ($from === 'cancelled') {
                // Cancelling put the stock back; take it again (refuse if it has been sold meanwhile).
                foreach ($order->items as $item) {
                    if ($item->product && $item->product->stock_qty < $item->qty) {
                        throw new RuntimeException("Not enough stock of {$item->product_name} to restore this order.");
                    }
                }
                foreach ($order->items as $item) {
                    $item->product?->decrement('stock_qty', $item->qty);
                }
            }
            $changes = ['status' => $to];
            if ($from === 'delivered') {
                // Not handed over after all: the handover code is needed again and sold-out pieces come back.
                $changes += ['handover_verified_at' => null, 'handover_verified_via' => null];
                foreach ($order->items as $item) {
                    $item->product?->forceFill(['hidden_after_sale' => false])->saveQuietly();
                }
                if ($order->payment_method === 'cod') {
                    // Cash was recorded at the door; it was not collected after all.
                    Payment::where('order_id', $order->id)->where('gateway', 'cod')->delete();
                    $changes += ['payment_status' => 'cod', 'paid_at' => null];
                }
                \App\Support\StorefrontCache::flushPriceDependent();
            }
            $order->forceFill($changes)->save();
            $this->history($order, $from, $to, $by, 'Undone (status set by mistake): '.$reason);
            app(\App\Affiliates\AffiliateService::class)->sync($order);

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

    public function history(Order $order, ?string $from, string $to, ?User $by, ?string $note, ?\Illuminate\Support\Carbon $at = null): void
    {
        $row = new OrderStatusHistory(['order_id' => $order->id, 'from_status' => $from, 'to_status' => $to, 'changed_by' => $by?->id, 'note' => $note]);
        if ($at) {
            $row->created_at = $at;
        }
        $row->save();
    }

    private static function hasColumn(string $column, string $table = 'orders'): bool
    {
        static $known = [];

        return $known[$table.'.'.$column] ??= rescue(fn () => \Illuminate\Support\Facades\Schema::hasColumn($table, $column), false, false);
    }
}
