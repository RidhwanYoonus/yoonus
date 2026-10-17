@extends('layouts.app')

@section('content')
    @foreach ($campaigns as $campaign)
        <section class="relative overflow-hidden bg-onyx text-ivory">
            @if ($campaign->bannerUrl())
                <img src="{{ $campaign->bannerUrl() }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-40">
            @endif
            <div class="container-x relative flex flex-col items-center gap-4 py-6 text-center sm:flex-row sm:justify-between sm:text-start">
                <div>
                    <p class="eyebrow text-gold-300">{{ $campaign->localized('name') }}</p>
                    <p class="font-display text-2xl">{{ $campaign->localized('headline') ?: $campaign->localized('name') }}</p>
                </div>
                @if ($campaign->show_countdown)
                    <div class="flex gap-2 price" x-data="countdown('{{ $campaign->ends_at->toIso8601String() }}')" aria-label="{{ __('shop.campaign.ends_in') }}">
                        @foreach (['d' => 'days', 'h' => 'hours', 'm' => 'minutes', 's' => 'seconds'] as $k => $label)
                            <div class="min-w-14 rounded-lg bg-white/10 px-2 py-1.5 text-center"><span class="block text-xl font-semibold text-gold-300" x-text="{{ $k }}"></span><span class="text-[10px] uppercase tracking-wider text-ivory/60">{{ __('shop.campaign.'.$label) }}</span></div>
                        @endforeach
                    </div>
                @endif
                <a href="{{ $campaign->link_url ?: route('shop.index') }}" class="btn-gold">{{ __('shop.campaign.shop_now') }}</a>
            </div>
        </section>
    @endforeach

    <section class="relative overflow-hidden bg-gradient-to-b from-sand to-ivory">
        <div class="container-x grid items-center gap-10 py-10 sm:py-16 lg:grid-cols-2 lg:py-24">
            <div>
                <p class="eyebrow">{{ __('shop.home.eyebrow') }}</p>
                <h1 class="font-display mt-3 text-4xl leading-tight text-ink sm:mt-4 sm:text-6xl">{{ __('shop.home.headline') }}</h1>
                <p class="mt-4 max-w-lg text-sm text-muted sm:mt-5 sm:text-base">{{ __('shop.home.subline') }}</p>
                <div class="mt-6 flex flex-wrap gap-3 sm:mt-8">
                    <a href="{{ route('shop.index') }}" class="btn-dark">{{ __('shop.home.cta') }}</a>
                    <a href="#price-promise" class="btn-outline">{{ __('shop.home.how_priced') }}</a>
                </div>
            </div>
            <div class="hidden grid-cols-2 gap-4 sm:grid">
                @foreach ($featured->take(4) as $p)
                    <a href="{{ route('product.show', $p) }}" class="overflow-hidden rounded-2xl border border-line bg-white {{ $loop->odd ? 'translate-y-6' : '' }}">
                        <img src="{{ $p->primaryImage() }}" alt="{{ $p->localized('name') }}" class="aspect-square w-full object-cover" loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    @if ($categories->isNotEmpty())
        <section class="container-x mt-10 sm:mt-16">
            <div class="flex items-end justify-between"><h2 class="font-display text-2xl sm:text-3xl">{{ __('shop.home.categories') }}</h2></div>
            {{-- Phones: one swipeable row of small tiles. Larger screens: grid. --}}
            <div class="no-scrollbar -mx-4 mt-4 flex snap-x gap-3 overflow-x-auto px-4 pb-1 sm:mx-0 sm:mt-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0 lg:grid-cols-6">
                @foreach ($categories as $c)
                    <a href="{{ route('category.show', $c) }}" class="card flex aspect-square w-28 shrink-0 snap-start flex-col items-center justify-end bg-gradient-to-b from-white to-sand p-3 text-center transition hover:border-gold-400 sm:aspect-[4/5] sm:w-auto sm:p-4">
                        <span class="font-display text-base sm:text-xl">{{ $c->localized('name') }}</span>
                        <span class="mt-1 hidden text-xs text-muted sm:block">{{ __('shop.home.explore') }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="container-x mt-10 sm:mt-16">
        <div class="flex items-end justify-between">
            <h2 class="font-display text-2xl sm:text-3xl">{{ __('shop.home.featured') }}</h2>
            <a href="{{ route('shop.index') }}" class="text-sm text-gold-700 hover:underline">{{ __('shop.home.view_all') }}</a>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-x-3 gap-y-6 sm:mt-6 sm:gap-x-4 sm:gap-y-8 md:grid-cols-4">
            @foreach ($featured as $product)@include('partials.product-card')@endforeach
        </div>
    </section>

    <section id="price-promise" class="container-x mt-12 sm:mt-20">
        <div class="card grid gap-6 bg-white p-6 sm:gap-8 sm:p-8 md:grid-cols-3 md:p-12">
            <div class="md:col-span-1">
                <p class="eyebrow">{{ __('shop.home.promise_eyebrow') }}</p>
                <h2 class="font-display mt-3 text-2xl sm:text-3xl">{{ __('shop.home.promise_title') }}</h2>
            </div>
            <ol class="grid gap-6 text-sm text-muted sm:grid-cols-2 md:col-span-2">
                @foreach (['gold', 'making', 'stone', 'vat'] as $step)
                    <li class="border-s-2 border-gold-400 ps-4"><p class="font-medium text-ink">{{ __("shop.home.promise_{$step}_title") }}</p><p class="mt-1">{{ __("shop.home.promise_{$step}") }}</p></li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="container-x mt-10 sm:mt-16">
        <h2 class="font-display text-2xl sm:text-3xl">{{ __('shop.home.new') }}</h2>
        {{-- Phones: swipeable carousel. --}}
        <div class="no-scrollbar -mx-4 mt-4 flex snap-x gap-3 overflow-x-auto px-4 pb-1 sm:mx-0 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-x-4 sm:gap-y-8 sm:overflow-visible sm:px-0 md:grid-cols-4">
            @foreach ($newArrivals as $product)<div class="w-[44%] shrink-0 snap-start sm:w-auto">@include('partials.product-card')</div>@endforeach
        </div>
    </section>

    <section class="container-x mt-12 sm:mt-20">
        <div class="card flex flex-col gap-5 bg-onyx p-6 text-ivory sm:p-8 md:flex-row md:items-center md:p-12">
            <div class="flex-1">
                <p class="eyebrow text-gold-300">{{ __('shop.alerts.eyebrow') }}</p>
                <h2 class="font-display mt-2 text-3xl">{{ __('shop.alerts.title') }}</h2>
                <p class="mt-2 text-sm text-ivory/60">{{ __('shop.alerts.subtitle') }}</p>
            </div>
            <form method="POST" action="{{ route('alerts.store') }}" class="grid flex-1 gap-2 sm:grid-cols-3">
                @csrf
                <select name="gold_category_id" class="input bg-white text-ink" aria-label="{{ __('shop.filters.karat') }}">
                    @foreach ($karats as $k)<option value="{{ $k->id }}">{{ $k->name }}</option>@endforeach
                </select>
                <input type="email" name="email" placeholder="{{ __('shop.checkout.email') }}" class="input text-ink" required>
                <button class="btn-gold">{{ __('shop.alerts.cta_subscribe') }}</button>
            </form>
        </div>
    </section>
@endsection
