@extends('layouts.app')
@section('content')
<div class="container-x max-w-2xl py-16 text-center">
    <p class="eyebrow">{{ __('shop.nav.gift_cards') }}</p>
    <h1 class="font-display mt-3 text-4xl">{{ __('shop.gift_card.title') }}</h1>
    <p class="mt-3 text-muted">{{ __('shop.gift_card.intro') }}</p>
    <form method="GET" class="card mx-auto mt-8 flex max-w-md gap-2 p-4">
        <input name="code" value="{{ request('code') }}" placeholder="GC-XXXX-XXXX-XXXX" class="input uppercase" dir="ltr" aria-label="{{ __('shop.checkout.gift_card') }}">
        <button class="btn-dark">{{ __('shop.gift_card.check') }}</button>
    </form>
    @if ($checked)
        <p class="mt-6 text-lg">
            @if ($card && $card->isUsable()) {{ __('shop.gift_card.balance', ['amount' => \App\Pricing\Money::format(\App\Pricing\Money::toFils($card->balance))]) }}
            @else {{ __('shop.gift_card.invalid') }} @endif
        </p>
    @endif
    <p class="mt-10 text-sm text-muted">{{ __('shop.gift_card.buy_in_store') }}</p>
</div>
@endsection
