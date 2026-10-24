<?php

namespace App\Affiliates;

use App\Models\Affiliate;
use App\Models\Order;
use App\Models\User;
use App\Pricing\Money;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** The affiliate's own report: every money movement, and the customers who came through them. */
class AffiliateReport
{
    public function __construct(private readonly Affiliate $affiliate) {}

    /**
     * Commissions per item, bonuses and collections in one list, newest first.
     *
     * @return Collection<int, array{date: Carbon, type: string, order: ?string, customer: ?string, item: string, details: string, status: string, amount: int}>
     */
    public function transactions(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $range = fn ($q, string $col = 'created_at') => $q->when($from, fn ($q) => $q->where($col, '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where($col, '<=', $to->copy()->endOfDay()));

        $rows = collect();
        foreach ($range($this->affiliate->commissions()->with('order'))->get() as $c) {
            $extra = array_filter([
                (float) $c->markup_amount > 0 ? __('shop.affiliate.incl_markup', ['amount' => Money::format(Money::toFils($c->markup_amount))]) : null,
                (float) ($c->coupon_deduction ?? 0) > 0 ? __('shop.affiliate.incl_coupon', ['amount' => Money::format(Money::toFils($c->coupon_deduction))]) : null,
                (float) ($c->bonus_amount ?? 0) > 0 ? __('shop.affiliate.incl_bonus', ['amount' => Money::format(Money::toFils($c->bonus_amount)), 'why' => $c->bonus_note]) : null,
            ]);
            $rows->push([
                'date' => $c->created_at, 'type' => 'commission', 'order' => $c->order?->number, 'customer' => $c->order?->customer_name,
                'item' => (string) $c->product_name, 'details' => trim($c->calculation().($extra ? ' · '.implode(' · ', $extra) : '')),
                'status' => $c->status, 'amount' => Money::toFils((string) $c->amount),
            ]);
        }
        foreach (rescue(fn () => $range($this->affiliate->adjustments())->get(), collect(), false) as $a) {
            $rows->push([
                'date' => $a->created_at, 'type' => 'bonus', 'order' => null, 'customer' => null,
                'item' => (string) $a->description, 'details' => '', 'status' => $a->status, 'amount' => Money::toFils((string) $a->amount),
            ]);
        }
        foreach ($range($this->affiliate->payouts()->with('branch'), 'paid_at')->get() as $p) {
            $rows->push([
                'date' => $p->paid_at, 'type' => 'collected', 'order' => null, 'customer' => null,
                'item' => __('shop.affiliate.report.collected_at', ['store' => $p->branch?->localized('name') ?? '—']),
                'details' => (string) ($p->reference ?: ''), 'status' => 'paid', 'amount' => -Money::toFils((string) $p->amount),
            ]);
        }

        return $rows->sortByDesc(fn ($r) => $r['date']?->timestamp ?? 0)->values();
    }

    /** True when the shop lets affiliates see their customers' phone and email (Settings → Affiliates). */
    public static function showsContacts(): bool
    {
        return Settings::bool('affiliate_customer_contacts', true);
    }

    /**
     * Customers linked to this affiliate: signed up with their link, or ordered through them.
     *
     * @return Collection<int, array{name: string, phone: ?string, email: ?string, how: string, joined: ?Carbon, orders: int, spent: int, last_order: ?Carbon, offers_ok: bool}>
     */
    public function customers(): Collection
    {
        $people = [];
        $key = fn (?int $userId, ?string $email, ?string $phone) => $userId ? 'u'.$userId : ($email ? 'e'.strtolower($email) : 'p'.preg_replace('/\D/', '', (string) $phone));

        // Signed up with the affiliate's link (unless the shop stopped their commission for that customer).
        $signups = User::where('signup_affiliate_id', $this->affiliate->id)->where(fn ($q) => $q->whereNull('affiliate_commission_off')->orWhere('affiliate_commission_off', false))->get();
        foreach ($signups as $u) {
            $people[$key($u->id, $u->email, $u->phone)] = [
                'name' => (string) $u->name, 'phone' => $u->phone, 'email' => $u->email, 'how' => 'signup', 'joined' => $u->created_at,
                'orders' => 0, 'spent' => 0, 'last_order' => null, 'offers_ok' => (bool) $u->marketing_opt_in,
            ];
        }

        // Ordered through the affiliate (link, white page or their code). Only real sales count towards orders / spent.
        $orders = Order::with('user')->where('affiliate_id', $this->affiliate->id)->where('payment_status', '!=', 'expired')->oldest()->get();
        foreach ($orders as $o) {
            $k = $key($o->user_id, $o->email, $o->phone);
            $p = $people[$k] ?? [
                'name' => (string) ($o->user?->name ?: $o->customer_name), 'phone' => $o->phone ?: $o->user?->phone, 'email' => $o->email ?: $o->user?->email,
                'how' => 'order', 'joined' => $o->created_at, 'orders' => 0, 'spent' => 0, 'last_order' => null, 'offers_ok' => (bool) $o->user?->marketing_opt_in,
            ];
            $p['phone'] = $p['phone'] ?: $o->phone;
            $p['email'] = $p['email'] ?: $o->email;
            $counted = in_array($o->payment_status, ['paid', 'cod'], true) && ! in_array($o->status, ['cancelled', 'refunded'], true);
            if ($counted) {
                $p['orders']++;
                $p['spent'] += Money::toFils((string) $o->grand_total);
            }
            $p['last_order'] = $o->created_at;
            $people[$k] = $p;
        }

        return collect($people)->sortByDesc(fn ($p) => ($p['last_order'] ?? $p['joined'])?->timestamp ?? 0)->values();
    }

    /** "+971 50 *** 4567" when the shop hides contact details. */
    public static function mask(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        if (str_contains($value, '@')) {
            [$name, $domain] = explode('@', $value, 2);

            return mb_substr($name, 0, 1).'***@'.$domain;
        }

        return mb_substr($value, 0, 6).' *** '.mb_substr($value, -3);
    }
}
