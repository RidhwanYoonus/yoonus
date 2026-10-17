@extends('layouts.app')
@php use App\Pricing\Money; @endphp

@section('content')
<div class="container-x py-12">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><p class="eyebrow">{{ __('shop.nav.account') }}</p><h1 class="font-display mt-2 text-4xl">{{ __('shop.account.hello', ['name' => $user->name]) }}</h1></div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-outline">{{ __('shop.auth.logout') }}</button></form>
    </div>

    <div class="mt-8 grid gap-4 md:grid-cols-3">
        <div class="card p-6"><p class="label">{{ __('shop.loyalty.balance') }}</p><p class="font-display text-4xl text-gold-700">{{ number_format($points) }}</p><p class="text-xs text-muted">{{ __('shop.loyalty.worth', ['amount' => Money::format(Money::toFils($points * $pointValue))]) }}</p></div>
        <div class="card p-6 md:col-span-2">
            <p class="label">{{ __('shop.referral.title') }}</p>
            <p class="text-sm text-muted">{{ __('shop.referral.explain', ['amount' => \App\Support\Settings::get('referral_reward')]) }}</p>
            <div class="mt-3 flex gap-2"><input readonly value="{{ $referralLink }}" class="input text-xs" dir="ltr" onclick="this.select()"><a class="btn-whatsapp px-4" target="_blank" rel="noopener" href="https://wa.me/?text={{ rawurlencode(__('shop.referral.share', ['amount' => \App\Support\Settings::get('referral_reward'), 'url' => $referralLink])) }}">WhatsApp</a></div>
            @foreach ($coupons as $c)<p class="mt-2 text-xs text-green-700">{{ __('shop.referral.your_coupon', ['code' => $c->code, 'amount' => $c->value]) }}</p>@endforeach
        </div>
    </div>

    <section class="mt-10">
        <h2 class="font-display text-2xl">{{ __('shop.account.orders') }}</h2>
        <div class="card mt-4 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-sand/60 text-xs uppercase tracking-wider text-muted"><tr><th class="px-4 py-3 text-start">#</th><th class="px-4 py-3 text-start">{{ __('shop.account.date') }}</th><th class="px-4 py-3 text-start">{{ __('shop.account.status') }}</th><th class="px-4 py-3 text-end">{{ __('shop.order.total') }}</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($orders as $o)
                        <tr><td class="px-4 py-3"><a href="{{ route('order.show', $o->number) }}" class="text-gold-700 underline">{{ $o->number }}</a></td><td class="px-4 py-3">{{ $o->created_at->locale(app()->getLocale())->isoFormat('ll') }}</td><td class="px-4 py-3">{{ $o->statusLabel() }}</td><td class="price px-4 py-3 text-end">{{ Money::format(Money::toFils($o->grand_total)) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-muted">{{ __('shop.account.no_orders') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-10 grid gap-8 lg:grid-cols-2">
        <div>
            <h2 class="font-display text-2xl">{{ __('shop.account.addresses') }}</h2>
            <ul class="mt-4 space-y-3">
                @foreach ($addresses as $a)
                    <li class="card flex justify-between gap-4 p-4 text-sm"><span>{{ $a->name }} · {{ $a->line1 }}, {{ $a->city }}</span>
                        <form method="POST" action="{{ route('account.addresses.delete', $a) }}">@csrf @method('DELETE')<button class="text-xs text-muted underline">{{ __('shop.cart.remove') }}</button></form></li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('account.addresses.store') }}" class="card mt-4 grid gap-3 p-4 sm:grid-cols-2">
                @csrf
                <input name="name" placeholder="{{ __('shop.checkout.name') }}" required class="input" value="{{ $user->name }}">
                <input name="phone" placeholder="{{ __('shop.checkout.phone') }}" required class="input" value="{{ $user->phone }}" dir="ltr">
                <input name="line1" placeholder="{{ __('shop.checkout.address') }}" required class="input sm:col-span-2">
                <input name="city" placeholder="{{ __('shop.checkout.city') }}" required class="input">
                <select name="zone_code" class="input">@foreach ($zones as $z)<option value="{{ $z->code }}">{{ $z->localized('name') }}</option>@endforeach</select>
                <button class="btn-outline sm:col-span-2">{{ __('shop.account.add_address') }}</button>
            </form>
        </div>
        <div>
            <h2 class="font-display text-2xl">{{ __('shop.alerts.title') }}</h2>
            <form method="POST" action="{{ route('alerts.store') }}" class="card mt-4 grid gap-3 p-4 sm:grid-cols-2">
                @csrf
                <input type="hidden" name="email" value="{{ $user->email }}">
                <select name="gold_category_id" class="input">@foreach ($karats as $k)<option value="{{ $k->id }}">{{ $k->name }} — AED {{ $k->rate_per_gram }}/g</option>@endforeach</select>
                <input type="number" step="0.01" name="target_rate" placeholder="{{ __('shop.alerts.target') }}" class="input">
                <button class="btn-gold sm:col-span-2">{{ __('shop.alerts.cta_subscribe') }}</button>
            </form>
            <h3 class="mt-8 font-medium">{{ __('shop.loyalty.history') }}</h3>
            <ul class="mt-2 divide-y divide-line text-sm">
                @foreach ($loyalty as $e)<li class="flex justify-between py-2"><span>{{ __('shop.loyalty.reason_'.$e->reason) }} · {{ $e->created_at->locale(app()->getLocale())->isoFormat('ll') }}</span><span class="{{ $e->points > 0 ? 'text-green-700' : 'text-red-700' }}">{{ $e->points > 0 ? '+' : '' }}{{ $e->points }}</span></li>@endforeach
            </ul>
        </div>
    </section>
</div>
@endsection
