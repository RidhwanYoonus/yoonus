@extends('layouts.app')
@php use App\Pricing\Money; @endphp

@section('content')
<div class="container-x py-10">
    <h1 class="font-display text-4xl">{{ __('shop.cart.title') }}</h1>

    @if ($changes)
        <div class="mt-6 rounded-xl border border-gold-400 bg-gold-300/20 p-4 text-sm" role="alert">
            <p class="font-medium">{{ __('shop.cart.prices_updated') }}</p>
            <ul class="mt-2 space-y-1">
                @foreach ($changes as $c)
                    <li>{{ $c['product']->localized('name') }}: <s>{{ Money::format($c['old']) }}</s> → <strong>{{ Money::format($c['new']) }}</strong></li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs text-muted">{{ __('shop.cart.prices_reason') }}</p>
        </div>
    @endif

    @if (! $lines)
        <div class="py-24 text-center">
            <p class="text-muted">{{ __('shop.cart.empty') }}</p>
            <a href="{{ route('shop.index') }}" class="btn-dark mt-6">{{ __('shop.home.cta') }}</a>
        </div>
    @else
        <div class="mt-8 grid gap-10 lg:grid-cols-[1fr_360px]">
            <ul class="divide-y divide-line border-y border-line">
                @foreach ($lines as $line)
                    <li class="flex gap-4 py-5">
                        <img src="{{ $line->product->primaryImage() }}" alt="" class="h-24 w-24 rounded-xl border border-line object-cover">
                        <div class="flex-1">
                            <a href="{{ route('product.show', $line->product) }}" class="font-medium hover:text-gold-700">{{ $line->product->localized('name') }}</a>
                            <p class="text-xs text-muted">{{ $line->product->specLine() }} · {{ Money::format($line->unit->taxable) }} {{ __('shop.cart.each') }}</p>
                            <div class="mt-3 flex items-center gap-3">
                                <form method="POST" action="{{ route('cart.update', $line->product->id) }}" class="flex items-center gap-2">
                                    @csrf @method('PATCH')
                                    <input type="number" name="qty" value="{{ $line->qty }}" min="0" max="{{ $line->product->stock_qty }}" class="input w-20 py-1.5 text-center" aria-label="{{ __('shop.cart.qty') }}" onchange="this.form.submit()">
                                </form>
                                <form method="POST" action="{{ route('cart.remove', $line->product->id) }}">@csrf @method('DELETE')<button class="text-xs text-muted underline">{{ __('shop.cart.remove') }}</button></form>
                            </div>
                        </div>
                        <p class="price font-semibold">{{ Money::format($line->subtotal()) }}</p>
                    </li>
                @endforeach
            </ul>
            @php($subtotal = array_sum(array_map(fn ($l) => $l->subtotal(), $lines)))
            <aside class="card h-fit p-6">
                <dl class="space-y-2 text-sm price">
                    <div class="flex justify-between text-base font-semibold"><dt>{{ __('shop.checkout.subtotal_ex_vat') }}</dt><dd class="text-gold-700">{{ Money::format($subtotal) }}</dd></div>
                </dl>
                <p class="mt-2 text-xs text-muted">{{ __('shop.cart.shipping_next') }}</p>
                <a href="{{ route('checkout.show') }}" class="btn-dark mt-6 w-full">{{ __('shop.cart.checkout') }}</a>
                <p class="mt-3 text-center text-xs text-muted">{{ __('shop.cart.bnpl_hint') }}</p>
            </aside>
        </div>
    @endif
</div>
@endsection
