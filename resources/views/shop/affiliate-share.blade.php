@extends('layouts.app')

@section('content')
<div class="container-x py-10" x-data="affShare({{ $affiliate->white_page_enabled ? 'true' : 'false' }})">
    @include('partials.affiliate-share-chooser')
    <a href="{{ route('affiliate.dashboard') }}" class="text-sm text-gold-700 hover:underline">{{ __('shop.affiliate.back_dashboard') }}</a>
    <h1 class="mt-3 font-display text-3xl sm:text-4xl">{{ __('shop.affiliate.share_all_title') }}</h1>
    <p class="mt-1 text-sm text-muted">{{ __('shop.affiliate.share_all_intro') }}</p>
    {{-- The rates the shop set for this affiliate. --}}
    <div class="card mt-4 grid gap-1 bg-gold-300/10 p-4 text-sm">
        <p><span class="text-muted">{{ __('shop.affiliate.your_rates') }}:</span> <b>{{ $affiliate->commissionSummary() }}</b>@if ((float) ($affiliate->stone_commission_percent ?? 0) > 0 && $affiliate->making_commission_type === 'per_gram') · {{ __('shop.affiliate.stone_label', ['pct' => rtrim(rtrim((string) $affiliate->stone_commission_percent, '0'), '.')]) }}@endif</p>
        @if ($affiliate->white_page_enabled)<p><span class="text-muted">{{ __('shop.affiliate.your_markup') }}:</span> <b>{{ $affiliate->markupSummary() }}</b></p>@endif
    </div>

    <form method="GET" class="card mt-6 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[1fr_12rem_9rem_11rem_11rem_auto]">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('shop.affiliate.search_pieces') }}" class="input" aria-label="{{ __('shop.affiliate.search_pieces') }}">
        <select name="category" class="input" aria-label="{{ __('shop.filters.category') }}">
            <option value="">{{ __('shop.filters.category') }}: {{ __('shop.filters.all') }}</option>
            @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(($filters['category'] ?? null) == $c->id)>{{ $c->localized('name') }}</option>@endforeach
        </select>
        <select name="karat" class="input" aria-label="{{ __('shop.filters.karat') }}">
            <option value="">{{ __('shop.filters.karat') }}: {{ __('shop.filters.all') }}</option>
            @foreach ($karats as $k)<option value="{{ $k->id }}" @selected(($filters['karat'] ?? null) == $k->id)>{{ $k->name }}</option>@endforeach
        </select>
        <select name="diamond" class="input" aria-label="{{ __('shop.filters.diamond') }}">
            <option value="">{{ __('shop.filters.diamond') }}: {{ __('shop.filters.all') }}</option>
            <option value="any" @selected(($filters['diamond'] ?? '') === 'any')>{{ __('shop.filters.with_diamond') }}</option>
            <option value="natural" @selected(($filters['diamond'] ?? '') === 'natural')>{{ __('shop.certificate.origin_natural') }}</option>
            <option value="lab_grown" @selected(($filters['diamond'] ?? '') === 'lab_grown')>{{ __('shop.certificate.origin_lab_grown') }}</option>
        </select>
        <select name="sort" class="input" aria-label="{{ __('shop.filters.sort') }}">
            @foreach (['newest', 'price_asc', 'price_desc', 'popular'] as $s)<option value="{{ $s }}" @selected(($filters['sort'] ?? 'newest') === $s)>{{ __('shop.filters.sort_'.$s) }}</option>@endforeach
        </select>
        <button class="btn-dark px-5">{{ __('shop.filters.apply') }}</button>
    </form>

    <p class="mt-6 text-sm text-muted">{{ trans_choice('shop.filters.count', $products->total(), ['count' => $products->total()]) }}</p>
    <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($products as $p)
            @include('partials.affiliate-post-card', ['p' => $p])
        @empty
            <p class="col-span-full py-12 text-center text-muted">{{ __('shop.filters.none') }}</p>
        @endforelse
    </div>
    <div class="mt-8">{{ $products->links() }}</div>
</div>
@endsection
