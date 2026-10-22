@php
    $rtl = app()->getLocale() === 'ar';
    $categories = \Illuminate\Support\Facades\Cache::remember('nav.categories.'.app()->getLocale(), 300, fn () => \App\Models\Category::where('is_active', true)->orderBy('sort_order')->take(7)->get());
    $navLink = 'relative py-3 text-[0.8125rem] uppercase tracking-[0.18em] text-ink/80 transition hover:text-gold-700 after:absolute after:inset-x-0 after:bottom-0 after:h-px after:scale-x-0 after:bg-gold-silver after:transition hover:after:scale-x-100';
    $icon = 'relative h-10 w-10 items-center justify-center rounded-full text-ink/80 transition hover:bg-sand hover:text-gold-700';
    $isAffiliate = auth()->check() && rescue(fn () => (bool) auth()->user()->affiliate, false, false);
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
                <input name="q" type="search" value="{{ request('q') }}" placeholder="{{ __('shop.nav.search') }}" class="input w-64 bg-sand/60 ps-9" aria-label="{{ __('shop.nav.search') }}">
            </form>
        </div>

        <a href="{{ route('home') }}" class="text-center leading-none">
            <span class="font-display text-gold-silver block max-w-[52vw] truncate whitespace-nowrap text-lg font-semibold uppercase tracking-[0.14em] sm:max-w-none sm:text-xl lg:text-2xl">{{ \App\Support\Seo::storeName() }}</span>
            <span class="mt-1.5 hidden whitespace-nowrap text-[0.5625rem] uppercase tracking-[0.18em] text-muted xl:block">{{ \App\Support\Settings::get($rtl ? 'store_caption_ar' : 'store_caption') }}</span>
        </a>

        <div class="flex items-center justify-end gap-0.5 sm:gap-1">
            <a href="{{ request()->fullUrlWithQuery(['lang' => $rtl ? 'en' : 'ar']) }}" class="me-1 rounded-full border border-line px-2.5 py-1 text-[0.6875rem] hover:border-gold-500 sm:px-3 sm:py-1.5 sm:text-xs" hreflang="{{ $rtl ? 'en' : 'ar' }}">{{ $rtl ? 'EN' : 'عربي' }}</a>
            <button type="button" class="{{ $icon }} hidden lg:inline-flex" x-data="themeToggle" @click="toggle()" :aria-label="dark ? '{{ __('shop.nav.light_mode') }}' : '{{ __('shop.nav.dark_mode') }}'" :title="dark ? '{{ __('shop.nav.light_mode') }}' : '{{ __('shop.nav.dark_mode') }}'">
                <svg x-show="!dark" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21.75 15.002A9.72 9.72 0 0118 15.75 9.75 9.75 0 018.25 6c0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25 9.75 9.75 0 0012.75 21a9.753 9.753 0 009.002-5.998z"/></svg>
                <svg x-show="dark" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/></svg>
            </button>
            <a href="{{ route('wishlist') }}" class="{{ $icon }} hidden lg:inline-flex" aria-label="{{ __('shop.nav.wishlist') }}">
                <svg class="h-5 w-5 {{ ($wishlistCount ?? 0) ? 'text-gold-600' : '' }}" fill="{{ ($wishlistCount ?? 0) ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
                @if ($wishlistCount ?? 0)
                    <span class="absolute top-0.5 end-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-gold-silver px-1 text-[0.625rem] font-semibold text-zinc-900">{{ $wishlistCount }}</span>
                @endif
            </a>
            @auth
                {{-- Signed in: small menu with account, affiliate dashboard and sign out. --}}
                <div class="relative hidden lg:block" x-data="{ menu: false }" @click.outside="menu = false" @keydown.escape="menu = false">
                    <button type="button" class="{{ $icon }} inline-flex" @click="menu = !menu" :aria-expanded="menu" aria-label="{{ __('shop.nav.account') }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.1a7.5 7.5 0 0115 0A17.9 17.9 0 0112 21.75c-2.68 0-5.22-.58-7.5-1.65z"/></svg>
                        <span class="absolute bottom-1.5 end-1.5 h-2 w-2 rounded-full bg-green-500 ring-2 ring-ivory"></span>
                    </button>
                    <div x-show="menu" x-cloak x-transition.opacity class="absolute end-0 z-50 mt-2 w-56 overflow-hidden rounded-xl border border-line bg-card text-sm shadow-lg">
                        <p class="truncate border-b border-line px-4 py-3 text-xs text-muted">{{ auth()->user()->name }}</p>
                        <a href="{{ route('account') }}" class="block px-4 py-2.5 hover:bg-sand">{{ __('shop.nav.account') }}</a>
                        @if ($isAffiliate)<a href="{{ route('affiliate.dashboard') }}" class="block px-4 py-2.5 font-medium text-gold-700 hover:bg-sand">🤝 {{ __('shop.affiliate.title') }}</a>@endif
                        <form method="POST" action="{{ route('logout') }}" class="border-t border-line">@csrf<button class="block w-full px-4 py-2.5 text-start hover:bg-sand">{{ __('shop.auth.logout') }}</button></form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="{{ $icon }} hidden lg:inline-flex" aria-label="{{ __('shop.nav.account') }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.1a7.5 7.5 0 0115 0A17.9 17.9 0 0112 21.75c-2.68 0-5.22-.58-7.5-1.65z"/></svg>
                </a>
            @endauth
            <a href="{{ route('cart.show') }}" class="{{ $icon }} inline-flex text-ink" aria-label="{{ __('shop.nav.cart') }}" data-cart-icon>
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z"/></svg>
                @if ($cartCount ?? 0)
                    <span class="absolute -top-0.5 -end-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[0.6875rem] font-bold text-white shadow ring-2 ring-ivory" data-cart-count>{{ $cartCount }}</span>
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
            <form action="{{ route('shop.index') }}" class="pb-3"><input name="q" type="search" placeholder="{{ __('shop.nav.search') }}" class="input"></form>
            <a href="{{ route('shop.index') }}" class="border-b border-line/60 py-3">{{ __('shop.nav.shop') }}</a>
            @foreach ($categories as $c)<a href="{{ route('category.show', $c) }}" class="border-b border-line/60 py-3">{{ $c->localized('name') }}</a>@endforeach
            <a href="{{ route('gift-cards') }}" class="border-b border-line/60 py-3">{{ __('shop.nav.gift_cards') }}</a>
            <a href="{{ route('compare') }}" class="border-b border-line/60 py-3">{{ __('shop.nav.compare') }}</a>
            <a href="{{ route('order.track') }}" class="border-b border-line/60 py-3">{{ __('shop.nav.track') }}</a>
            @auth
                @if ($isAffiliate)<a href="{{ route('affiliate.dashboard') }}" class="border-b border-line/60 py-3 font-medium text-gold-700">🤝 {{ __('shop.affiliate.title') }}</a>@endif
                <form method="POST" action="{{ route('logout') }}" class="border-b border-line/60">@csrf<button class="w-full py-3 text-start">{{ __('shop.auth.logout') }}</button></form>
            @endauth
            <button type="button" x-data="themeToggle" @click="toggle()" class="flex items-center justify-between py-3 text-start">
                <span x-text="dark ? '{{ __('shop.nav.light_mode') }}' : '{{ __('shop.nav.dark_mode') }}'"></span>
                <span class="relative inline-flex h-5 w-9 items-center rounded-full transition" :class="dark ? 'bg-gold-silver' : 'bg-line'"><span class="absolute h-4 w-4 rounded-full bg-card shadow-xs transition-all" :class="dark ? 'start-[1.1rem]' : 'start-0.5'"></span></span>
            </button>
        </nav>
    </div>
</header>
