@extends('layouts.app')

@section('content')
<section class="border-b border-line bg-gradient-to-b from-sand/70 to-ivory">
    <div class="container-x py-10 text-center sm:py-14">
        <nav class="text-xs text-muted" aria-label="breadcrumb"><a href="{{ route('home') }}" class="hover:text-gold-700">{{ __('shop.nav.home') }}</a> <span class="mx-1">/</span> {{ $category?->localized('name') ?? __('shop.nav.shop') }}</nav>
        <h1 class="font-display mt-3 text-4xl sm:text-5xl">{{ $category?->localized('name') ?? __('shop.nav.shop') }}</h1>
        @if ($category?->localized('description'))<p class="mx-auto mt-3 max-w-2xl text-muted">{{ $category->localized('description') }}</p>@endif
    </div>
</section>
<div class="container-x py-8 sm:py-10">
    <div class="grid gap-8 lg:grid-cols-[16rem_1fr] 2xl:grid-cols-[17rem_1fr]" x-data="{ filters: false }">
        <button class="btn-outline lg:hidden" @click="filters = !filters">{{ __('shop.filters.title') }}</button>
        <form method="GET" class="card h-fit space-y-6 p-5 text-sm lg:sticky lg:top-36" :class="filters ? '' : 'hidden lg:block'">
            @if (request('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif
            <fieldset>
                <legend class="label">{{ __('shop.filters.karat') }}</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach ($karats as $k)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="karat[]" value="{{ $k->id }}" @checked(in_array($k->id, $filters['karat'] ?? [])) class="peer sr-only">
                            <span class="inline-block rounded-full border border-line px-3.5 py-1.5 text-xs transition peer-checked:border-gold-600 peer-checked:bg-gold-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-gold-300 hover:border-gold-400">{{ $k->name }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            @unless ($category)
                <fieldset>
                    <legend class="label">{{ __('shop.filters.category') }}</legend>
                    <select name="category" class="input"><option value="">{{ __('shop.filters.all') }}</option>
                        @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(($filters['category'] ?? null) == $c->id)>{{ $c->localized('name') }}</option>@endforeach
                    </select>
                </fieldset>
            @endunless
            <fieldset>
                <legend class="label">{{ __('shop.filters.price') }}</legend>
                <div class="flex gap-2"><input type="number" name="min_price" min="0" value="{{ $filters['min_price'] ?? '' }}" placeholder="{{ __('shop.filters.min') }}" class="input"><input type="number" name="max_price" min="0" value="{{ $filters['max_price'] ?? '' }}" placeholder="{{ __('shop.filters.max') }}" class="input"></div>
            </fieldset>
            <fieldset>
                <legend class="label">{{ __('shop.filters.weight') }}</legend>
                <div class="flex gap-2"><input type="number" step="0.1" name="min_weight" min="0" value="{{ $filters['min_weight'] ?? '' }}" placeholder="{{ __('shop.filters.min') }}" class="input"><input type="number" step="0.1" name="max_weight" min="0" value="{{ $filters['max_weight'] ?? '' }}" placeholder="{{ __('shop.filters.max') }}" class="input"></div>
            </fieldset>
            @if ($stones->isNotEmpty())
                <fieldset>
                    <legend class="label">{{ __('shop.filters.stone') }}</legend>
                    <select name="stone" class="input"><option value="">{{ __('shop.filters.all') }}</option><option value="none" @selected(($filters['stone'] ?? '') === 'none')>{{ __('shop.filters.plain_gold') }}</option>
                        @foreach ($stones as $s)<option value="{{ $s }}" @selected(($filters['stone'] ?? '') === $s)>{{ $s }}</option>@endforeach
                    </select>
                </fieldset>
            @endif
            <fieldset>
                <legend class="label">{{ __('shop.filters.sort') }}</legend>
                <select name="sort" class="input">
                    @foreach (['newest', 'price_asc', 'price_desc', 'weight_asc', 'weight_desc', 'popular'] as $s)
                        <option value="{{ $s }}" @selected(($filters['sort'] ?? 'newest') === $s)>{{ __('shop.filters.sort_'.$s) }}</option>
                    @endforeach
                </select>
            </fieldset>
            <div class="flex gap-2"><button class="btn-dark flex-1">{{ __('shop.filters.apply') }}</button><a href="{{ url()->current() }}" class="btn-outline">{{ __('shop.filters.reset') }}</a></div>
        </form>

        <div>
            <p class="text-sm text-muted">{{ trans_choice('shop.filters.count', $products->total(), ['count' => $products->total()]) }}</p>
            <div class="mt-4 grid-products">
                @forelse ($products as $product)
                    @include('partials.product-card')
                @empty
                    <p class="col-span-full py-16 text-center text-muted">{{ __('shop.filters.none') }}</p>
                @endforelse
            </div>
            <div class="mt-10">{{ $products->links() }}</div>
        </div>
    </div>
</div>
@endsection
