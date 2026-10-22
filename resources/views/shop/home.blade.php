@extends('layouts.app')
@php
    use App\Pricing\Money;
    use App\Support\JewelleryArt;
    $headline22 = $karats->firstWhere('name', '22K') ?? $karats->first();
    $heroPhotos = $featured->filter(fn ($p) => ! empty($p->images))->take(3);
    $sectionTitle = fn ($eyebrow, $title) => '<p class="eyebrow">'.e($eyebrow).'</p><h2 class="font-display mt-2 text-3xl sm:text-4xl">'.e($title).'</h2>';
@endphp

@section('content')
    {{-- Campaign strip --}}
    @foreach ($campaigns as $campaign)
        <section class="relative overflow-hidden bg-gold-silver text-zinc-900">
            @if ($campaign->bannerUrl())
                <img src="{{ $campaign->bannerUrl() }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-30">
            @endif
            <div class="container-x relative flex flex-col items-center gap-2 py-2.5 text-center sm:flex-row sm:justify-center sm:gap-8 sm:py-3">
                <p class="text-xs sm:text-sm"><span class="font-semibold uppercase tracking-[0.2em]">{{ $campaign->localized('name') }}</span> <span class="mx-2 opacity-60">·</span> {{ $campaign->localized('headline') }}</p>
                @if ($campaign->show_countdown)
                    <div class="flex items-center gap-1.5 price text-xs" x-data="countdown('{{ $campaign->ends_at->toIso8601String() }}')" aria-label="{{ __('shop.campaign.ends_in') }}">
                        @foreach (['d' => 'days', 'h' => 'hours', 'm' => 'minutes', 's' => 'seconds'] as $k => $label)
                            <span class="rounded-md bg-white/40 px-2 py-1"><b x-text="{{ $k }}"></b> {{ __('shop.campaign.'.$label) }}</span>
                        @endforeach
                    </div>
                @endif
                <a href="{{ $campaign->link_url ?: route('shop.index') }}" class="text-xs font-semibold uppercase tracking-[0.2em] underline underline-offset-4">{{ __('shop.campaign.shop_now') }} →</a>
            </div>
        </section>
    @endforeach

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-onyx text-on-dark">
        <div class="pointer-events-none absolute -top-40 end-[-10%] h-[38rem] w-[38rem] rounded-full bg-gold-500/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-48 start-[-10%] h-[30rem] w-[30rem] rounded-full bg-gold-700/20 blur-3xl"></div>
        <div class="container-x relative grid items-center gap-6 py-10 sm:gap-10 sm:py-20 lg:grid-cols-[1.1fr_1fr] lg:py-28">
            <div>
                @php($caption = \App\Support\Settings::get(app()->getLocale() === 'ar' ? 'store_caption_ar' : 'store_caption'))
                @if ($caption)
                    <p class="inline-flex items-center gap-2 rounded-full border border-gold-400/40 bg-gold-500/10 px-4 py-1.5 text-xs text-gold-300 sm:text-sm">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z"/></svg>
                        {{ $caption }}
                    </p>
                @endif
                <p class="eyebrow mt-5 text-gold-300">{{ __('shop.home.eyebrow') }}</p>
                <h1 class="font-display mt-3 text-[2.6rem] leading-[1.08] sm:mt-4 sm:text-6xl lg:text-7xl">{{ __('shop.home.headline') }}</h1>
                <p class="mt-4 max-w-xl text-sm leading-relaxed text-on-dark/70 sm:mt-6 sm:text-base">{{ __('shop.home.subline') }}</p>
                <div class="mt-6 flex flex-wrap gap-3 sm:mt-8">
                    <a href="{{ route('shop.index') }}" class="btn-gold px-8">{{ __('shop.home.cta') }}</a>
                    <a href="#price-promise" class="btn border border-white/25 text-on-dark hover:border-gold-400 hover:text-gold-300">{{ __('shop.home.how_priced') }}</a>
                </div>
                @if ($headline22)
                    <div class="mt-6 inline-flex items-center gap-4 rounded-2xl sm:mt-10 border border-white/10 bg-card/5 px-5 py-3 backdrop-blur">
                        <span class="text-xs uppercase tracking-[0.2em] text-on-dark/60">{{ __('shop.rates.today') }} · {{ $headline22->name }}</span>
                        <span class="price font-display text-rate text-2xl font-bold">{{ Money::format(Money::toFils($headline22->rate_per_gram)) }}<span class="text-sm text-on-dark/50">/{{ __('shop.rates.gram') }}</span></span>
                    </div>
                @endif
            </div>
            <div class="relative mx-auto hidden aspect-square w-full max-w-md sm:block lg:max-w-lg">
                <div class="absolute inset-6 rounded-full border border-gold-400/30"></div>
                <div class="absolute inset-16 rounded-full border border-gold-400/15"></div>
                @if ($heroImage = \App\Support\Settings::imageUrl('home_hero_image'))
                    <img src="{{ $heroImage }}" alt="{{ \App\Support\Seo::storeName() }}" class="absolute inset-10 h-[calc(100%-5rem)] w-[calc(100%-5rem)] rounded-full object-cover shadow-2xl">
                @elseif ($heroPhotos->count() >= 1)
                    <img src="{{ $heroPhotos->first()->primaryImage() }}" alt="{{ $heroPhotos->first()->localized('name') }}" class="absolute inset-10 h-[calc(100%-5rem)] w-[calc(100%-5rem)] rounded-full object-cover shadow-2xl">
                @else
                    <div class="absolute inset-[12%] drop-shadow-[0_25px_45px_rgba(212,180,104,.35)]">{!! JewelleryArt::icon('necklace', 'hn') !!}</div>
                    <div class="absolute bottom-[6%] end-[4%] h-[38%] w-[38%] drop-shadow-[0_15px_30px_rgba(212,180,104,.35)]">{!! JewelleryArt::icon('ring', 'hr') !!}</div>
                @endif
            </div>
        </div>
    </section>

    {{-- Trust bar --}}
    <section class="border-b border-line bg-card">
        <div class="container-x grid grid-cols-2 gap-x-6 gap-y-5 py-6 lg:grid-cols-4">
            @foreach ([
                ['rate', 'M3 3v18h18M7 15l4-4 3 3 5-6'],
                ['hallmark', 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['bnpl', 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z'],
                ['delivery', 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12'],
            ] as [$key, $path])
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-sand text-gold-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $path }}"/></svg>
                    </span>
                    <span><span class="block text-sm font-medium">{{ __("shop.home.trust_{$key}_title") }}</span><span class="block text-xs text-muted">{{ __("shop.home.trust_{$key}") }}</span></span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Categories --}}
    @if ($categories->isNotEmpty())
        <section class="container-x mt-14 sm:mt-20">
            <div class="text-center">{!! $sectionTitle(__('shop.home.explore'), __('shop.home.categories')) !!}</div>
            <div class="no-scrollbar -mx-4 mt-8 flex snap-x gap-3 overflow-x-auto px-4 pb-2 sm:mx-0 sm:grid sm:grid-cols-3 sm:gap-5 sm:px-0 lg:grid-cols-6">
                @foreach ($categories as $c)
                    <a href="{{ route('category.show', $c) }}" class="group w-32 shrink-0 snap-start text-center sm:w-auto">
                        <span class="relative block aspect-square overflow-hidden rounded-full border border-line bg-gradient-to-b from-card to-sand transition duration-300 group-hover:border-gold-400 group-hover:shadow-[0_15px_40px_-15px_rgba(154,123,47,.45)]">
                            @if ($c->image)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($c->image) }}" alt="" class="h-full w-full object-cover" loading="lazy">
                            @else
                                <span class="absolute inset-[18%] block transition duration-500 group-hover:scale-110">{!! JewelleryArt::icon(JewelleryArt::typeFor($c->slug, $c->name), 'c'.$c->id) !!}</span>
                            @endif
                        </span>
                        <span class="font-display mt-3 block text-lg sm:text-xl">{{ $c->localized('name') }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Featured --}}
    <section class="container-x mt-16 sm:mt-24">
        <div class="flex items-end justify-between gap-4">
            <div>{!! $sectionTitle(__('shop.home.featured_eyebrow'), __('shop.home.featured')) !!}</div>
            <a href="{{ route('shop.index') }}" class="shrink-0 text-sm font-medium text-gold-700 underline-offset-4 hover:underline">{{ __('shop.home.view_all') }} →</a>
        </div>
        <div class="mt-8 grid-products">
            @foreach ($featured as $product)@include('partials.product-card')@endforeach
        </div>
    </section>

    {{-- Price promise --}}
    <section id="price-promise" class="mt-16 bg-sand/60 py-14 sm:mt-24 sm:py-20">
        <div class="container-x grid gap-10 lg:grid-cols-[1fr_2fr] lg:items-center">
            <div>{!! $sectionTitle(__('shop.home.promise_eyebrow'), __('shop.home.promise_title')) !!}</div>
            <ol class="grid gap-4 sm:grid-cols-2">
                @foreach (['gold', 'making', 'stone', 'vat'] as $i => $step)
                    <li class="card flex gap-4 p-5">
                        <span class="font-display flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-onyx text-gold-300">{{ $i + 1 }}</span>
                        <span><span class="block font-medium">{{ __("shop.home.promise_{$step}_title") }}</span><span class="mt-1 block text-sm text-muted">{{ __("shop.home.promise_{$step}") }}</span></span>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- New arrivals --}}
    <section class="container-x mt-16 sm:mt-24">
        <div class="flex items-end justify-between gap-4">
            <div>{!! $sectionTitle(__('shop.home.new_eyebrow'), __('shop.home.new')) !!}</div>
            <a href="{{ route('shop.index', ['sort' => 'newest']) }}" class="shrink-0 text-sm font-medium text-gold-700 underline-offset-4 hover:underline">{{ __('shop.home.view_all') }} →</a>
        </div>
        <div class="no-scrollbar -mx-4 mt-8 flex snap-x gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:grid sm:grid-cols-[repeat(auto-fill,minmax(14.5rem,1fr))] sm:gap-x-6 sm:gap-y-8 sm:overflow-visible sm:px-0">
            @foreach ($newArrivals as $product)<div class="w-[46%] shrink-0 snap-start sm:w-auto">@include('partials.product-card')</div>@endforeach
        </div>
    </section>

    {{-- Showroom (Settings → Branding) --}}
    @if ($showroom = \App\Support\Settings::imageUrl('showroom_image'))
        @php($address = \App\Support\Settings::get('store_address'))
        <section class="container-x mt-16 sm:mt-24">
            <div class="grid overflow-hidden rounded-3xl border border-line bg-card lg:grid-cols-2">
                <img src="{{ $showroom }}" alt="{{ __('shop.showroom.title') }}" class="aspect-[16/10] h-full w-full object-cover" loading="lazy">
                <div class="flex flex-col justify-center gap-4 p-8 sm:p-12">
                    <p class="eyebrow">{{ __('shop.showroom.eyebrow') }}</p>
                    <h2 class="font-display text-3xl sm:text-4xl">{{ \App\Support\Settings::get(app()->getLocale() === 'ar' ? 'showroom_title_ar' : 'showroom_title') ?: __('shop.showroom.title') }}</h2>
                    @if ($address)<p class="text-muted">📍 {{ $address }}</p>@endif
                    <p class="text-sm text-muted">{{ __('shop.showroom.text') }}</p>
                    <div class="flex flex-wrap gap-3">
                        @if ($address)<a href="https://www.google.com/maps/search/?api=1&query={{ rawurlencode($address) }}" target="_blank" rel="noopener" class="btn-dark">{{ __('shop.showroom.directions') }}</a>@endif
                        @if ($wa = \App\Support\Settings::get('whatsapp_number'))<a href="{{ \App\Messaging\WhatsApp::link($wa, __('shop.showroom.whatsapp')) }}" target="_blank" rel="noopener" class="btn-whatsapp">WhatsApp</a>@endif
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Gold price alerts --}}
    <section class="container-x mt-16 sm:mt-24">
        <div class="relative overflow-hidden rounded-3xl bg-onyx p-8 text-on-dark sm:p-12">
            <div class="pointer-events-none absolute -end-20 -top-20 h-72 w-72 rounded-full bg-gold-500/20 blur-3xl"></div>
            <div class="relative grid gap-6 lg:grid-cols-2 lg:items-center">
                <div>
                    <p class="eyebrow text-gold-300">{{ __('shop.alerts.eyebrow') }}</p>
                    <h2 class="font-display mt-2 text-3xl sm:text-4xl">{{ __('shop.alerts.title') }}</h2>
                    <p class="mt-2 text-sm text-on-dark/60">{{ __('shop.alerts.subtitle') }}</p>
                </div>
                <form method="POST" action="{{ route('alerts.store') }}" class="grid gap-2 sm:grid-cols-[auto_1fr_auto]">
                    @csrf
                    <select name="gold_category_id" class="input bg-card text-ink" aria-label="{{ __('shop.filters.karat') }}">
                        @foreach ($karats as $k)<option value="{{ $k->id }}">{{ $k->name }}</option>@endforeach
                    </select>
                    <input type="email" name="email" placeholder="{{ __('shop.checkout.email') }}" class="input text-ink" required>
                    <button class="btn-gold">{{ __('shop.alerts.cta_subscribe') }}</button>
                </form>
            </div>
        </div>
    </section>
@endsection
