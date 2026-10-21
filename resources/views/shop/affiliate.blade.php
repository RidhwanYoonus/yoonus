@extends('layouts.app')
@php use App\Pricing\Money; $fmt = fn ($v) => Money::format(is_int($v) ? $v : Money::toFils($v)); @endphp

@section('content')
<div class="container-x py-12" x-data="{ copied: null, copy(t, k) { navigator.clipboard?.writeText(t); this.copied = k; setTimeout(() => this.copied = null, 1500) } }">
    <p class="eyebrow">{{ __('shop.affiliate.eyebrow') }} · <span dir="ltr">{{ $affiliate->code }}</span></p>
    <h1 class="mt-2 font-display text-4xl">{{ __('shop.affiliate.title') }}</h1>
    <p class="mt-2 text-sm text-muted">{{ __('shop.affiliate.intro') }}</p>
    @unless ($affiliate->is_active)
        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">{{ __('shop.affiliate.paused') }}</div>
    @endunless

    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card p-5"><p class="label">{{ __('shop.affiliate.pending') }}</p><p class="price mt-1 text-2xl font-semibold">{{ $fmt($totals['pending']) }}</p><p class="text-xs text-muted">{{ __('shop.affiliate.pending_note') }}</p></div>
        <div class="card p-5"><p class="label">{{ __('shop.affiliate.earned') }}</p><p class="price mt-1 text-2xl font-semibold">{{ $fmt($totals['earned']) }}</p></div>
        <div class="card p-5"><p class="label">{{ __('shop.affiliate.collected') }}</p><p class="price mt-1 text-2xl font-semibold">{{ $fmt($totals['collected']) }}</p></div>
        <div class="card border-gold-400 p-5"><p class="label">{{ __('shop.affiliate.balance') }}</p><p class="price mt-1 text-2xl font-bold text-gold-700">{{ $fmt($totals['balance']) }}</p><p class="text-xs text-muted">{{ __('shop.affiliate.balance_note') }}</p></div>
    </div>

    {{-- Level + last 30 days --}}
    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div class="card p-5">
            <p class="label">{{ __('shop.affiliate.level') }}</p>
            <p class="mt-1 text-2xl font-semibold">{{ $level['name'] ?? __('shop.affiliate.level_none') }}
                @if ($level['extra_bp'] > 0)<span class="ms-2 badge bg-gold-300/30 text-gold-700">+{{ rtrim(rtrim(\App\Pricing\Money::toAed($level['extra_bp']), '0'), '.') }}%</span>@endif
            </p>
            @if ($level['next'])
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-sand"><div class="h-full rounded-full bg-gold-silver" style="width: {{ min(100, $level['progress']) }}%"></div></div>
                <p class="mt-2 text-xs text-muted">{{ __('shop.affiliate.level_next', ['amount' => $fmt($level['next']['needed']), 'name' => $level['next']['name']]) }}</p>
            @endif
        </div>
        <div class="card grid grid-cols-4 gap-2 p-5 text-center">
            <p class="col-span-4 text-start label">{{ __('shop.affiliate.last_30') }}</p>
            @foreach (['visits', 'views', 'orders'] as $k)
                <div><p class="text-2xl font-semibold">{{ number_format($stats[$k]) }}</p><p class="text-xs text-muted">{{ __('shop.affiliate.stat_'.$k) }}</p></div>
            @endforeach
            <div><p class="text-2xl font-semibold">{{ $stats['conversion'] }}%</p><p class="text-xs text-muted">{{ __('shop.affiliate.stat_conversion') }}</p></div>
        </div>
    </div>

    @if ((float) $affiliate->coupon_discount_percent > 0)
        <div class="card mt-4 flex flex-wrap items-center justify-between gap-3 p-5">
            <p class="text-sm">{{ __('shop.affiliate.coupon_note', ['pct' => rtrim(rtrim((string) $affiliate->coupon_discount_percent, '0'), '.')]) }}</p>
            <span class="rounded-xl border border-dashed border-gold-500 px-4 py-2 font-mono text-lg font-semibold tracking-widest" dir="ltr">{{ $affiliate->code }}</span>
        </div>
    @endif

    @if ($campaigns->isNotEmpty())
        <section class="mt-8">
            <h2 class="font-display text-2xl">{{ __('shop.affiliate.campaigns') }}</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @foreach ($campaigns as $c)
                    <div class="card border-gold-300 p-5">
                        <p class="font-semibold">{{ $c->name }}</p>
                        <p class="text-xs text-muted">{{ __('shop.affiliate.until', ['date' => $c->ends_at->locale(app()->getLocale())->isoFormat('ll')]) }}</p>
                        @if ($c->type === 'booster')
                            <p class="mt-2 text-sm">{{ __('shop.affiliate.booster_note', ['x' => rtrim(rtrim((string) $c->multiplier, '0'), '.'), 'what' => $c->coverageLabel()]) }}</p>
                        @else
                            @php($goal = (float) $c->target_value)
                            <p class="mt-2 text-sm">{{ __('shop.affiliate.target_note', ['goal' => $c->target_metric === 'sales' ? 'AED '.number_format($goal) : number_format($goal).' '.__('shop.affiliate.pieces'), 'bonus' => 'AED '.number_format((float) $c->bonus_amount)]) }}</p>
                            <div class="mt-3 h-2 overflow-hidden rounded-full bg-sand"><div class="h-full rounded-full bg-gold-silver" style="width: {{ $goal > 0 ? min(100, round(100 * (float) $c->progress / $goal)) : 0 }}%"></div></div>
                            <p class="mt-1 text-xs text-muted">{{ $c->reached ? '✓ '.__('shop.affiliate.target_reached') : number_format((float) $c->progress).' / '.number_format($goal) }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Ready-made posts --}}
    <section class="mt-10" x-data="affShare({{ $affiliate->white_page_enabled ? 'true' : 'false' }})">
        @include('partials.affiliate-share-chooser')
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl">{{ __('shop.affiliate.picks') }}</h2>
                <p class="mt-1 text-sm text-muted">{{ __('shop.affiliate.picks_note') }}</p>
            </div>
            <a href="{{ route('affiliate.share') }}" class="btn-dark px-5">{{ __('shop.affiliate.see_all', ['count' => $picksTotal]) }}</a>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($picks as $p)
                @include('partials.affiliate-post-card', ['p' => $p])
            @endforeach
        </div>
        @php($rateLine = $rates->map(fn ($r) => $r->name.': AED '.$r->rate_per_gram.'/g')->implode(' · '))
        @php($rateTexts = ['r' => __('shop.affiliate.rate_post', ['rates' => $rateLine, 'url' => $affiliate->referralLink()]), 'w' => __('shop.affiliate.rate_post', ['rates' => $rateLine, 'url' => $affiliate->whitePageLink()])])
        <div class="card mt-4 flex flex-wrap items-center justify-between gap-3 p-5">
            <div class="min-w-0"><p class="label">{{ __('shop.affiliate.rate_share') }}</p><p class="text-sm text-muted">{{ $rateTexts['r'] }}</p></div>
            <div class="flex gap-2">
                <button type="button" class="btn-whatsapp px-4" @click="share('wa', @js($rateTexts))">WhatsApp</button>
                <button type="button" class="btn-outline px-4" @click="share('copy', @js($rateTexts), 'rate')" x-text="done === 'rate' ? '✓' : @js(__('shop.affiliate.copy'))"></button>
            </div>
        </div>
        @include('partials.rate-card', ['backgrounds' => $rateBackgrounds])
        @if ($affiliate->white_page_enabled)
            <p class="mt-3 text-xs text-muted">{{ __('shop.affiliate.share_choice_note') }} <button type="button" class="underline" @click="forget()" x-text="done === 'forget' ? '✓' : @js(__('shop.affiliate.share_ask_again'))"></button></p>
        @endif
    </section>

    @if ($affiliate->can_lock_price)
        <section class="mt-10">
            <h2 class="font-display text-2xl">{{ __('shop.affiliate.lock_title') }}</h2>
            <p class="mt-1 text-sm text-muted">{{ __('shop.affiliate.lock_note', ['hours' => \App\Affiliates\PriceLocks::hours()]) }}</p>
            <form method="POST" action="{{ route('affiliate.lock.create') }}" class="card mt-4 flex flex-wrap gap-2 p-4">
                @csrf
                <select name="product_id" required class="input min-w-0 flex-1">
                    <option value="">{{ __('shop.affiliate.lock_choose') }}</option>
                    @foreach ($lockProducts as $lp)<option value="{{ $lp->id }}">{{ $lp->name }} · {{ $lp->sku }}</option>@endforeach
                </select>
                <button class="btn-gold px-6">{{ __('shop.affiliate.lock_create') }}</button>
            </form>
            @if (session('new_lock'))
                <div class="mt-3 flex gap-2"><input readonly value="{{ session('new_lock') }}" class="input text-xs" dir="ltr" onclick="this.select()">
                    <a class="btn-whatsapp px-4" target="_blank" rel="noopener" href="https://wa.me/?text={{ rawurlencode(session('new_lock')) }}">WhatsApp</a></div>
            @endif
            @if ($locks->isNotEmpty())
                <div class="card mt-3 divide-y divide-line text-sm">
                    @foreach ($locks as $l)
                        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                            <span>{{ $l->product?->localized('name') }} — <span class="price">{{ \App\Pricing\Money::format($l->breakdown()->taxable) }}</span></span>
                            <span class="text-xs {{ $l->expires_at->isPast() ? 'text-red-700' : 'text-muted' }}">{{ $l->expires_at->isPast() ? __('shop.affiliate.expired') : __('shop.affiliate.until', ['date' => $l->expires_at->locale(app()->getLocale())->isoFormat('lll')]) }} · {{ trans_choice('shop.affiliate.uses', $l->uses, ['count' => $l->uses]) }}</span>
                            @unless ($l->expires_at->isPast())<button type="button" class="btn-outline h-8 px-3 text-xs" @click="copy(@js($l->link()), 'l{{ $l->id }}')" x-text="copied === 'l{{ $l->id }}' ? '✓' : @js(__('shop.affiliate.copy'))"></button>@endunless
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    @php($teamOn = \App\Models\Affiliate::teamEnabled())
    <div @class(['mt-10 grid gap-4', 'lg:grid-cols-2' => $teamOn])>
        @if ($teamOn)
        <section class="card p-6">
            <h2 class="font-display text-2xl">{{ __('shop.affiliate.team') }}</h2>
            <p class="mt-1 text-sm text-muted">{{ __('shop.affiliate.team_note', ['pct' => rtrim(rtrim(number_format((float) \App\Support\Settings::get('affiliate_recruiter_percent', 10), 2), '0'), '.')]) }}</p>
            <div class="mt-3 flex gap-2">
                <input readonly value="{{ $affiliate->joinLink() }}" class="input text-xs" dir="ltr" onclick="this.select()">
                <button type="button" class="btn-outline px-4" @click="copy(@js($affiliate->joinLink()), 'j')" x-text="copied === 'j' ? '✓' : @js(__('shop.affiliate.copy'))"></button>
            </div>
            @if ($team->isNotEmpty())
                <ul class="mt-4 space-y-1 text-sm">@foreach ($team as $t)<li>• {{ $t->name() }} <span class="text-xs text-muted" dir="ltr">({{ $t->code }})</span></li>@endforeach</ul>
            @endif
        </section>
        @endif
        <section class="card p-6">
            <h2 class="font-display text-2xl">{{ __('shop.affiliate.leaderboard') }}</h2>
            <ol class="mt-3 space-y-1.5 text-sm">
                @forelse ($leaderboard as $i => $row)
                    <li @class(['flex justify-between rounded-lg px-3 py-1.5', 'bg-gold-300/25 font-semibold' => $row['affiliate']->id === $affiliate->id])>
                        <span>{{ ['🥇', '🥈', '🥉'][$i] ?? ($i + 1).'.' }} {{ $row['affiliate']->name() }}</span><span class="text-muted">{{ trans_choice('shop.affiliate.orders_count', $row['orders'], ['count' => $row['orders']]) }}</span>
                    </li>
                @empty
                    <li class="text-muted">{{ __('shop.affiliate.leaderboard_empty') }}</li>
                @endforelse
            </ol>
        </section>
    </div>

    @if ($visits->isNotEmpty())
        <section class="mt-10">
            <h2 class="font-display text-2xl">{{ __('shop.affiliate.visits') }}</h2>
            <div class="card mt-4 divide-y divide-line text-sm">
                @foreach ($visits as $v)
                    <div class="flex flex-wrap justify-between gap-2 px-4 py-3"><span>{{ $v->name }} · {{ $v->preferred_at?->locale(app()->getLocale())->isoFormat('lll') ?? '—' }}</span><span class="badge bg-sand text-muted">{{ __('shop.affiliate.visit_status.'.$v->status) }}</span></div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="mt-8 grid gap-4 lg:grid-cols-2">
        <div class="card p-6">
            <p class="label">{{ __('shop.affiliate.link') }}</p>
            <p class="text-sm text-muted">{{ __('shop.affiliate.link_note', ['rule' => $affiliate->commissionSummary()]) }}</p>
            <div class="mt-3 flex gap-2">
                <input readonly value="{{ $affiliate->referralLink() }}" class="input text-xs" dir="ltr" onclick="this.select()">
                <button type="button" class="btn-outline px-4" @click="copy(@js($affiliate->referralLink()), 'r')" x-text="copied === 'r' ? '✓' : @js(__('shop.affiliate.copy'))"></button>
                <a class="btn-whatsapp px-4" target="_blank" rel="noopener" href="https://wa.me/?text={{ rawurlencode($affiliate->referralLink()) }}">WhatsApp</a>
            </div>
        </div>
        @if ($affiliate->white_page_enabled)
            <form method="POST" action="{{ route('affiliate.update') }}" class="card p-6">
                @csrf
                <p class="label">{{ __('shop.affiliate.white_page') }}</p>
                <p class="text-sm text-muted">{{ __('shop.affiliate.white_note') }}</p>
                <div class="mt-3 flex gap-2">
                    <input readonly value="{{ $affiliate->whitePageLink() }}" class="input text-xs" dir="ltr" onclick="this.select()">
                    <button type="button" class="btn-outline px-4" @click="copy(@js($affiliate->whitePageLink()), 'w')" x-text="copied === 'w' ? '✓' : @js(__('shop.affiliate.copy'))"></button>
                </div>
                @php($num = fn ($v) => ((float) $v == (int) $v) ? (string) (int) $v : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'))
                <label class="mt-4 grid gap-1 text-sm">{{ __('shop.affiliate.display_name') }}
                    <input name="display_name" value="{{ old('display_name', $affiliate->display_name) }}" maxlength="80" class="input">
                </label>
                @php($chip = 'flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-xs')
                <div class="mt-4" x-data="{ mode: @js(old('markup_mode', $affiliate->markup_mode ?: 'percent')), gold: @js(old('making_markup_type', $affiliate->making_markup_type ?: 'percent')), fixed: @js(old('fixed_markup_type', $affiliate->fixed_markup_type ?: 'percent')) }">
                    <p class="text-sm">{{ __('shop.affiliate.markup') }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <label class="flex cursor-pointer items-center gap-2 rounded-full border px-4 py-2 text-sm" :class="mode === 'percent' ? 'border-gold-500 bg-gold-300/20' : 'border-line'"><input type="radio" name="markup_mode" value="percent" x-model="mode" class="accent-gold-600"> {{ __('shop.affiliate.mode_by_type') }}</label>
                        <label class="flex cursor-pointer items-center gap-2 rounded-full border px-4 py-2 text-sm" :class="mode === 'amount' ? 'border-gold-500 bg-gold-300/20' : 'border-line'"><input type="radio" name="markup_mode" value="amount" x-model="mode" class="accent-gold-600"> {{ __('shop.affiliate.mode_amount') }}</label>
                    </div>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2" x-show="mode === 'percent'">
                        <div class="rounded-xl border border-line p-3 text-sm">
                            <p class="font-medium">{{ __('shop.affiliate.gold_items') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <label class="{{ $chip }}" :class="gold === 'percent' ? 'border-gold-500 bg-gold-300/20' : 'border-line'"><input type="radio" name="making_markup_type" value="percent" x-model="gold" class="accent-gold-600"> {{ __('shop.affiliate.opt_making_pct') }}</label>
                                <label class="{{ $chip }}" :class="gold === 'per_gram' ? 'border-gold-500 bg-gold-300/20' : 'border-line'"><input type="radio" name="making_markup_type" value="per_gram" x-model="gold" class="accent-gold-600"> {{ __('shop.affiliate.opt_per_gram') }}</label>
                            </div>
                            <div class="mt-2" x-show="gold === 'percent'">@include('partials.markup-field', ['name' => 'making_markup_percent', 'max' => 'max_making_markup_percent', 'unit' => '%'])</div>
                            <div class="mt-2" x-show="gold === 'per_gram'" x-cloak>@include('partials.markup-field', ['name' => 'making_markup_per_gram', 'max' => 'max_making_markup_per_gram', 'unit' => 'AED'])<span class="text-xs text-muted">{{ __('shop.affiliate.per_gram_unit') }}</span></div>
                        </div>
                        <div class="rounded-xl border border-line p-3 text-sm">
                            <p class="font-medium">{{ __('shop.affiliate.fixed_items') }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <label class="{{ $chip }}" :class="fixed === 'percent' ? 'border-gold-500 bg-gold-300/20' : 'border-line'"><input type="radio" name="fixed_markup_type" value="percent" x-model="fixed" class="accent-gold-600"> {{ __('shop.affiliate.opt_price_pct') }}</label>
                                <label class="{{ $chip }}" :class="fixed === 'per_piece' ? 'border-gold-500 bg-gold-300/20' : 'border-line'"><input type="radio" name="fixed_markup_type" value="per_piece" x-model="fixed" class="accent-gold-600"> {{ __('shop.affiliate.opt_per_piece') }}</label>
                            </div>
                            <div class="mt-2" x-show="fixed === 'percent'">@include('partials.markup-field', ['name' => 'fixed_markup_percent', 'max' => 'max_fixed_markup_percent', 'unit' => '%'])</div>
                            <div class="mt-2" x-show="fixed === 'per_piece'" x-cloak>@include('partials.markup-field', ['name' => 'fixed_markup_amount', 'max' => 'max_fixed_markup_amount', 'unit' => 'AED'])</div>
                        </div>
                        <p class="text-xs text-muted sm:col-span-2">{{ __('shop.affiliate.percent_example') }}</p>
                    </div>
                    <div class="mt-3" x-show="mode === 'amount'" x-cloak>
                        <div class="text-sm sm:w-1/2"><p>{{ __('shop.affiliate.amount_markup') }}</p>@include('partials.markup-field', ['name' => 'markup_amount', 'max' => 'max_markup_amount', 'unit' => 'AED'])</div>
                        <p class="mt-2 text-xs text-muted">{{ __('shop.affiliate.amount_example') }}</p>
                    </div>
                </div>
                <button class="btn-gold mt-4 h-10 px-6">{{ __('shop.affiliate.save') }}</button>
            </form>
        @endif
    </div>

    <section class="mt-10">
        <h2 class="font-display text-2xl">{{ __('shop.affiliate.sales') }}</h2>
        <div class="card mt-4 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-sand/60 text-xs uppercase tracking-wider text-muted"><tr>
                    <th class="px-4 py-3 text-start">{{ __('shop.account.date') }}</th><th class="px-4 py-3 text-start">#</th><th class="px-4 py-3 text-start">{{ __('shop.affiliate.item') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('shop.account.status') }}</th><th class="px-4 py-3 text-end">{{ __('shop.affiliate.commission') }}</th>
                </tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($commissions as $c)
                        <tr>
                            <td class="px-4 py-3">{{ $c->created_at->locale(app()->getLocale())->isoFormat('ll') }}</td>
                            <td class="px-4 py-3" dir="ltr">{{ $c->order?->number }}</td>
                            <td class="px-4 py-3">{{ $c->product_name }}</td>
                            <td class="px-4 py-3"><span @class(['badge', 'bg-green-100 text-green-800' => $c->status === 'available', 'bg-red-50 text-red-700' => $c->status === 'void', 'bg-sand text-muted' => $c->status === 'pending'])>{{ __('shop.affiliate.status.'.$c->status) }}</span></td>
                            <td class="price px-4 py-3 text-end font-medium">{{ $fmt($c->amount) }}@if ((float) $c->markup_amount > 0)<span class="block text-xs text-muted">{{ __('shop.affiliate.incl_markup', ['amount' => $fmt($c->markup_amount)]) }}</span>@endif
                                @if ((float) ($c->coupon_deduction ?? 0) > 0)<span class="block text-xs text-red-700">{{ __('shop.affiliate.incl_coupon', ['amount' => $fmt($c->coupon_deduction)]) }}</span>@endif
                                @if ((float) ($c->bonus_amount ?? 0) > 0)<span class="block text-xs text-gold-700">{{ __('shop.affiliate.incl_bonus', ['amount' => $fmt($c->bonus_amount), 'why' => $c->bonus_note]) }}</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-muted">{{ __('shop.affiliate.no_sales') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($adjustments->isNotEmpty())
        <section class="mt-10">
            <h2 class="font-display text-2xl">{{ __('shop.affiliate.extras') }}</h2>
            <div class="card mt-4 divide-y divide-line text-sm">
                @foreach ($adjustments as $a)
                    <div class="flex flex-wrap justify-between gap-2 px-4 py-3"><span>{{ $a->created_at->locale(app()->getLocale())->isoFormat('ll') }} · {{ $a->description }}</span><span class="price font-medium">{{ $fmt($a->amount) }}</span></div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-10">
        <h2 class="font-display text-2xl">{{ __('shop.affiliate.collections') }}</h2>
        <div class="card mt-4 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-sand/60 text-xs uppercase tracking-wider text-muted"><tr><th class="px-4 py-3 text-start">{{ __('shop.account.date') }}</th><th class="px-4 py-3 text-start">{{ __('shop.affiliate.store') }}</th><th class="px-4 py-3 text-start">{{ __('shop.affiliate.receipt') }}</th><th class="px-4 py-3 text-end">{{ __('shop.affiliate.amount') }}</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($payouts as $p)
                        <tr><td class="px-4 py-3">{{ $p->paid_at->locale(app()->getLocale())->isoFormat('lll') }}</td><td class="px-4 py-3">{{ $p->branch?->localized('name') ?? '—' }}</td><td class="px-4 py-3" dir="ltr">{{ $p->reference ?: '—' }}</td><td class="price px-4 py-3 text-end font-medium">{{ $fmt($p->amount) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-muted">{{ __('shop.affiliate.no_collections') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
