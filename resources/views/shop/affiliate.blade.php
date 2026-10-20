@extends('layouts.app')
@php use App\Pricing\Money; $fmt = fn ($v) => Money::format(is_int($v) ? $v : Money::toFils($v)); @endphp

@section('content')
<div class="container-x py-12" x-data="{ copied: null, copy(t, k) { navigator.clipboard?.writeText(t); this.copied = k; setTimeout(() => this.copied = null, 1500) } }">
    <p class="eyebrow">{{ __('shop.affiliate.eyebrow') }} · <span dir="ltr">{{ $affiliate->code }}</span></p>
    <h1 class="mt-2 font-display text-4xl">{{ __('shop.affiliate.title') }}</h1>
    <p class="mt-2 text-sm text-muted">{{ __('shop.affiliate.intro') }}</p>
    @unless ($affiliate->is_active)<p class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800">{{ __('shop.affiliate.inactive') }}</p>@endunless

    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card p-5"><p class="label">{{ __('shop.affiliate.pending') }}</p><p class="price mt-1 text-2xl font-semibold">{{ $fmt($totals['pending']) }}</p><p class="text-xs text-muted">{{ __('shop.affiliate.pending_note') }}</p></div>
        <div class="card p-5"><p class="label">{{ __('shop.affiliate.earned') }}</p><p class="price mt-1 text-2xl font-semibold">{{ $fmt($totals['earned']) }}</p></div>
        <div class="card p-5"><p class="label">{{ __('shop.affiliate.collected') }}</p><p class="price mt-1 text-2xl font-semibold">{{ $fmt($totals['collected']) }}</p></div>
        <div class="card border-gold-400 p-5"><p class="label">{{ __('shop.affiliate.balance') }}</p><p class="price mt-1 text-2xl font-bold text-gold-700">{{ $fmt($totals['balance']) }}</p><p class="text-xs text-muted">{{ __('shop.affiliate.balance_note') }}</p></div>
    </div>

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
                @php($canPercent = (float) $affiliate->max_making_markup_percent > 0 || (float) $affiliate->max_fixed_markup_percent > 0)
                @php($canAmount = (float) $affiliate->max_markup_amount > 0)
                <label class="mt-4 grid gap-1 text-sm">{{ __('shop.affiliate.display_name') }}
                    <input name="display_name" value="{{ old('display_name', $affiliate->display_name) }}" maxlength="80" class="input">
                </label>
                <div class="mt-4" x-data="{ mode: @js(old('markup_mode', $canAmount && ! $canPercent ? 'amount' : ($canPercent && ! $canAmount ? 'percent' : ($affiliate->markup_mode ?: 'percent')))) }">
                    <p class="text-sm">{{ __('shop.affiliate.markup') }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @if ($canPercent)<label class="flex cursor-pointer items-center gap-2 rounded-full border px-4 py-2 text-sm" :class="mode === 'percent' ? 'border-gold-500 bg-gold-300/20' : 'border-line'"><input type="radio" name="markup_mode" value="percent" x-model="mode" class="accent-gold-600"> {{ __('shop.affiliate.mode_percent') }}</label>@endif
                        @if ($canAmount)<label class="flex cursor-pointer items-center gap-2 rounded-full border px-4 py-2 text-sm" :class="mode === 'amount' ? 'border-gold-500 bg-gold-300/20' : 'border-line'"><input type="radio" name="markup_mode" value="amount" x-model="mode" class="accent-gold-600"> {{ __('shop.affiliate.mode_amount') }}</label>@endif
                        @unless ($canPercent || $canAmount)<p class="text-sm text-muted">{{ __('shop.affiliate.no_markup_allowed') }}</p>@endunless
                    </div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2" x-show="mode === 'percent'">
                        <label class="grid gap-1 text-sm">{{ __('shop.affiliate.making_markup') }} <span class="text-xs text-muted">{{ __('shop.affiliate.max', ['max' => $num($affiliate->max_making_markup_percent).'%']) }}</span>
                            <span class="flex items-center gap-2"><input name="making_markup_percent" @disabled((float) $affiliate->max_making_markup_percent <= 0) type="number" step="0.01" min="0" max="{{ (float) $affiliate->max_making_markup_percent }}" value="{{ old('making_markup_percent', (float) $affiliate->making_markup_percent) }}" class="input" dir="ltr"> %</span>
                        </label>
                        <label class="grid gap-1 text-sm">{{ __('shop.affiliate.fixed_markup') }} <span class="text-xs text-muted">{{ __('shop.affiliate.max', ['max' => $num($affiliate->max_fixed_markup_percent).'%']) }}</span>
                            <span class="flex items-center gap-2"><input name="fixed_markup_percent" @disabled((float) $affiliate->max_fixed_markup_percent <= 0) type="number" step="0.01" min="0" max="{{ (float) $affiliate->max_fixed_markup_percent }}" value="{{ old('fixed_markup_percent', (float) $affiliate->fixed_markup_percent) }}" class="input" dir="ltr"> %</span>
                        </label>
                        <p class="text-xs text-muted sm:col-span-2">{{ __('shop.affiliate.percent_example') }}</p>
                    </div>
                    <div class="mt-3" x-show="mode === 'amount'" x-cloak>
                        <label class="grid gap-1 text-sm sm:w-1/2">{{ __('shop.affiliate.amount_markup') }} <span class="text-xs text-muted">{{ __('shop.affiliate.max', ['max' => 'AED '.$num($affiliate->max_markup_amount)]) }}</span>
                            <span class="flex items-center gap-2">AED <input name="markup_amount" type="number" step="0.01" min="0" max="{{ (float) $affiliate->max_markup_amount }}" value="{{ old('markup_amount', (float) $affiliate->markup_amount) }}" class="input" dir="ltr"></span>
                        </label>
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
                            <td class="price px-4 py-3 text-end font-medium">{{ $fmt($c->amount) }}@if ((float) $c->markup_amount > 0)<span class="block text-xs text-muted">{{ __('shop.affiliate.incl_markup', ['amount' => $fmt($c->markup_amount)]) }}</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-muted">{{ __('shop.affiliate.no_sales') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

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
