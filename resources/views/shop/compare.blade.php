@extends('layouts.app')
@php use App\Pricing\Money; @endphp
@section('content')
<div class="container-x py-12">
    <h1 class="font-display text-4xl">{{ __('shop.nav.compare') }}</h1>
    @if ($products->isEmpty())
        <p class="py-16 text-center text-muted">{{ __('shop.compare.empty') }}</p>
    @else
        <div class="card mt-8 overflow-x-auto">
            <table class="w-full min-w-[600px] text-sm price">
                <tbody class="divide-y divide-line">
                    <tr><th class="w-40 bg-sand/50 px-4 py-3 text-start"></th>@foreach ($products as $p)<td class="px-4 py-3"><img src="{{ $p->primaryImage() }}" alt="" class="aspect-square w-32 rounded-xl object-cover"><a href="{{ route('product.show', $p) }}" class="mt-2 block font-medium">{{ $p->localized('name') }}</a></td>@endforeach</tr>
                    @php($rows = [
                        __('shop.product.price') => fn ($p) => Money::format($p->displayPrice()->taxable).' + VAT',
                        __('shop.filters.karat') => fn ($p) => $p->goldCategory?->name ?? '—',
                        __('shop.product.weight') => fn ($p) => (float) $p->weight_grams > 0 ? $p->weight_grams.' g' : '—',
                        __('shop.breakdown.gold_short') => fn ($p) => $p->isFixedPrice() ? __('shop.product.fixed_price') : Money::format($p->price()->goldValue),
                        __('shop.breakdown.making') => fn ($p) => $p->isFixedPrice() ? '—' : Money::format($p->price()->makingFee),
                        __('shop.breakdown.stone') => fn ($p) => $p->stone_type ? $p->stone_type.' · '.Money::format($p->price()->stoneValue) : '—',
                        __('shop.filters.category') => fn ($p) => $p->category?->localized('name') ?? '—',
                    ])
                    @foreach ($rows as $label => $fn)
                        <tr><th class="bg-sand/50 px-4 py-3 text-start font-medium">{{ $label }}</th>@foreach ($products as $p)<td class="px-4 py-3">{{ $fn($p) }}</td>@endforeach</tr>
                    @endforeach
                    <tr><th class="bg-sand/50"></th>@foreach ($products as $p)<td class="px-4 py-3"><form method="POST" action="{{ route('compare.toggle', $p) }}">@csrf<button class="text-xs text-muted underline">{{ __('shop.cart.remove') }}</button></form></td>@endforeach</tr>
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
