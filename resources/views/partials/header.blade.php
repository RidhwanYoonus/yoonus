@php
    $rtl = app()->getLocale() === 'ar';
    $categories = \Illuminate\Support\Facades\Cache::remember('nav.categories.'.app()->getLocale(), 300, fn () => \App\Models\Category::where('is_active', true)->orderBy('sort_order')->take(7)->get());
    $navLink = 'relative py-3 text-[13px] uppercase tracking-[0.18em] text-ink/80 transition hover:text-gold-700 after:absolute after:inset-x-0 after:bottom-0 after:h-px after:scale-x-0 after:bg-gold-600 after:transition hover:after:scale-x-100';
    $icon = 'relative h-10 w-10 items-center justify-center rounded-full text-ink/80 transition hover:bg-sand hover:text-gold-700';
@endphp
<header class="sticky top-0 z-40 border-b border-line bg-ivory/95 backdrop-blur" x-data="{ open: false }">
    {{-- Row 1: search · centred logo · icons --}}
    <div class="container-x grid h-16 grid-cols-[1fr_auto_1fr] items-center gap-3 lg:h-20">
        <div class="flex items-center gap-2">
            <button class="-ms-2 inline-flex h-10 w-10 items-center justify-center lg:hidden" @click="open = !open" aria-label="{{ __('shop.nav.menu') }}">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <form action="{{ route('shop.index') }}" class="relative hidden lg:block">
                <svg class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z"/></svg>
                <input name="q" type="search" value="{{ request('q') }}" placeholder="{{ __('shop.nav.search') }}" class="input w-64 rounded-full border-line bg-white/70 py-2 ps-9" aria-label="{{ __('shop.nav.search') }}">
            </form>
        </div>

        <a href="{{ route('home') }}" class="text-center leading-none">
            <span class="font-display block max-w-[52vw] truncate whitespace-nowrap text-lg text-gold-700 sm:max-w-none sm:text-2xl sm:tracking-[0.08em] lg:text-[1.9rem]">{{ \App\Support\Seo::storeName() }}</span>
            <span class="mt-1.5 hidden whitespace-nowrap text-[9px] uppercase tracking-[0.18em] text-muted xl:block">{{ \App\Support\Settings::get($rtl ? 'store_caption_ar' : 'store_caption') }}</span>
        </a>

        <div class="flex items-center justify-end gap-0.5 sm:gap-1">
            <a href="{{ request()->fullUrlWithQuery(['lang' => $rtl ? 'en' : 'ar']) }}" class="me-1 rounded-full border border-line px-2.5 py-1 text-[11px] hover:border-gold-500 sm:px-3 sm:py-1.5 sm:text-xs" hreflang="{{ $rtl ? 'en' : 'ar' }}">{{ $rtl ? 'EN' : 'عربي' }}</a>
            <a href="{{ route('wishlist') }}" class="{{ $icon }} hidden lg:inline-flex" aria-label="{{ __('shop.nav.wishlist') }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
            </a>
            <a href="{{ auth()->check() ? route('account') : route('login') }}" class="{{ $icon }} hidden lg:inline-flex" aria-label="{{ __('shop.nav.account') }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.1a7.5 7.5 0 0115 0A17.9 17.9 0 0112 21.75c-2.68 0-5.22-.58-7.5-1.65z"/></svg>
            </a>
            <a href="{{ route('cart.show') }}" class="{{ $icon }} inline-flex" aria-label="{{ __('shop.nav.cart') }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z"/></svg>
                @if ($cartCount ?? 0)
                    <span class="absolute top-0.5 end-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-gold-600 px-1 text-[10px] text-white">{{ $cartCount }}</span>
                @endif
            </a>
        </div>
    </div>

    {{-- Row 2 (desktop): centred navigation --}}
    <nav class="hidden border-t border-line/70 lg:block" aria-label="{{ __('shop.nav.menu') }}">
        <div class="container-x flex items-center justify-center gap-9">
            <a href="{{ route('shop.index') }}" class="{{ $navLink }}">{{ __('shop.nav.shop') }}</a>
            @foreach ($categories as $c)
                <a href="{{ route('category.show', $c) }}" class="{{ $navLink }}">{{ $c->localized('name') }}</a>
            @endforeach
            <a href="{{ route('gift-cards') }}" class="{{ $navLink }}">{{ __('shop.nav.gift_cards') }}</a>
        </div>
    </nav>

    {{-- Mobile menu --}}
    <div x-show="open" x-collapse x-cloak class="border-t border-line lg:hidden">
        <nav class="container-x flex flex-col py-3 text-sm">
            <form action="{{ route('shop.index') }}" class="pb-3"><input name="q" type="search" placeholder="{{ __('shop.nav.search') }}" class="input rounded-full"></form>
            <a href="{{ route('shop.index') }}" class="border-b border-line/60 py-3">{{ __('shop.nav.shop') }}</a>
            @foreach ($categories as $c)<a href="{{ route('category.show', $c) }}" class="border-b border-line/60 py-3">{{ $c->localized('name') }}</a>@endforeach
            <a href="{{ route('gift-cards') }}" class="border-b border-line/60 py-3">{{ __('shop.nav.gift_cards') }}</a>
            <a href="{{ route('compare') }}" class="border-b border-line/60 py-3">{{ __('shop.nav.compare') }}</a>
            <a href="{{ route('order.track') }}" class="py-3">{{ __('shop.nav.track') }}</a>
        </nav>
    </div>
</header>
