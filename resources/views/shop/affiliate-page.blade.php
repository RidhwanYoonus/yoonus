@extends('layouts.app')
@section('content')
<section class="border-b border-line bg-gradient-to-b from-sand/70 to-ivory">
    <div class="container-x py-12 text-center">
        <p class="eyebrow">{{ __('shop.affiliate.page_eyebrow') }}</p>
        <h1 class="mt-3 font-display text-4xl sm:text-5xl">{{ $affiliate->name() }}</h1>
        <p class="mx-auto mt-3 max-w-xl text-sm text-muted">{{ __('shop.affiliate.page_intro', ['store' => \App\Support\Seo::storeName()]) }}</p>
        @if ($preview)
            <p class="mx-auto mt-5 max-w-xl rounded-xl border border-gold-400 bg-gold-300/20 px-4 py-3 text-sm">
                {{ __('shop.affiliate.preview', ['markup' => rtrim(rtrim((string) min((float) $affiliate->markup_percent, (float) $affiliate->max_markup_percent), '0'), '.')]) }}
            </p>
        @elseif (! $affiliate->white_page_enabled)
            <p class="mt-3 text-xs text-muted">{{ __('shop.affiliate.page_normal_prices') }}</p>
        @endif
    </div>
</section>

<div class="container-x py-10">
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <a href="{{ route('affiliate.page', $affiliate->code) }}" @class(['rounded-full border px-4 py-2 text-sm', 'border-ink bg-ink text-ivory' => empty($filters['category']), 'border-line hover:border-gold-400' => ! empty($filters['category'])])>{{ __('shop.affiliate.all') }}</a>
        @foreach ($categories as $c)
            <a href="{{ route('affiliate.page', [$affiliate->code, 'category' => $c->id]) }}" @class(['rounded-full border px-4 py-2 text-sm', 'border-ink bg-ink text-ivory' => ($filters['category'] ?? null) == $c->id, 'border-line hover:border-gold-400' => ($filters['category'] ?? null) != $c->id])>{{ $c->localized('name') }}</a>
        @endforeach
        <span class="ms-auto flex items-center gap-2">
            @if (! empty($filters['category']))<input type="hidden" name="category" value="{{ $filters['category'] }}">@endif
            <input name="q" value="{{ $filters['q'] ?? '' }}" type="search" placeholder="{{ __('shop.nav.search') }}" class="input h-10 w-44">
            <select name="sort" class="input h-10 w-auto" onchange="this.form.submit()">
                <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>{{ __('shop.affiliate.sort_featured') }}</option>
                <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>{{ __('shop.affiliate.sort_low') }}</option>
                <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>{{ __('shop.affiliate.sort_high') }}</option>
            </select>
        </span>
    </form>

    @if ($products->isEmpty())
        <p class="py-20 text-center text-muted">{{ __('shop.affiliate.no_products') }}</p>
    @else
        <div class="grid-products mt-8">
            @foreach ($products as $product)
                @include('partials.product-card')
            @endforeach
        </div>
        <div class="mt-10">{{ $products->links() }}</div>
    @endif
</div>
@endsection
