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
            <p class="text-sm text-muted">{{ __('shop.affiliate.link_note', ['making' => rtrim(rtrim((string) $affiliate->making_commission_percent, '0'), '.'), 'fixed' => rtrim(rtrim((string) $affiliate->fixed_commission_percent, '0'), '.')]) }}</p>
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
                <p class="text-sm text-muted">{{ __('shop.affiliate.white_note', ['max' => rtrim(rtrim((string) $affiliate->max_markup_percent, '0'), '.')]) }}</p>
                <div class="mt-3 flex gap-2">
                    <input readonly value="{{ $affiliate->whitePageLink() }}" class="input text-xs" dir="ltr" onclick="this.select()">
                    <button type="button" class="btn-outline px-4" @click="copy(@js($affiliate->whitePageLink()), 'w')" x-text="copied === 'w' ? '✓' : @js(__('shop.affiliate.copy'))"></button>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="grid gap-1 text-sm">{{ __('shop.affiliate.display_name') }}
                        <input name="display_name" value="{{ old('display_name', $affiliate->display_name) }}" maxlength="80" class="input">
                    </label>
                    <label class="grid gap-1 text-sm">{{ __('shop.affiliate.markup') }}
                        <span class="flex items-center gap-2"><input name="markup_percent" type="number" step="0.01" min="0" max="{{ (float) $affiliate->max_markup_percent }}" value="{{ old('markup_percent', (float) $affiliate->markup_percent) }}" class="input" dir="ltr"> %</span>
                    </label>
                </div>
                <p class="mt-2 text-xs text-muted">{{ __('shop.affiliate.markup_example') }}</p>
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
