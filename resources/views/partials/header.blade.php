@php
    $rtl = app()->getLocale() === 'ar';
    $categories = \Illuminate\Support\Facades\Cache::remember('nav.categories.'.app()->getLocale(), 300, fn () => \App\Models\Category::where('is_active', true)->orderBy('sort_order')->take(6)->get());
@endphp
<header class="sticky top-0 z-40 border-b border-line bg-ivory/95 backdrop-blur" x-data="{ open: false }">
    <div class="container-x flex h-14 items-center gap-3 sm:h-16 sm:gap-4">
        <button class="lg:hidden -ms-2 p-2" @click="open = !open" aria-label="{{ __('shop.nav.menu') }}">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        <a href="{{ route('home') }}" class="font-display min-w-0 truncate text-xl leading-none tracking-wide text-gold-700 sm:text-2xl">{{ \App\Support\Seo::storeName() }}</a>

        <nav class="ms-8 hidden items-center gap-6 text-sm lg:flex">
            <a href="{{ route('shop.index') }}" class="hover:text-gold-600">{{ __('shop.nav.shop') }}</a>
            @foreach ($categories as $c)
                <a href="{{ route('category.show', $c) }}" class="hover:text-gold-600">{{ $c->localized('name') }}</a>
            @endforeach
            <a href="{{ route('gift-cards') }}" class="hover:text-gold-600">{{ __('shop.nav.gift_cards') }}</a>
        </nav>

        <div class="ms-auto flex shrink-0 items-center gap-1 sm:gap-3 text-sm">
            <form action="{{ route('shop.index') }}" class="hidden md:block">
                <input name="q" type="search" value="{{ request('q') }}" placeholder="{{ __('shop.nav.search') }}" class="input w-44 rounded-full py-1.5" aria-label="{{ __('shop.nav.search') }}">
            </form>
            <a href="{{ request()->fullUrlWithQuery(['lang' => $rtl ? 'en' : 'ar']) }}" class="rounded-full border border-line px-3 py-1 text-xs hover:border-gold-500" hreflang="{{ $rtl ? 'en' : 'ar' }}">{{ $rtl ? 'English' : 'العربية' }}</a>
            <a href="{{ route('wishlist') }}" class="hidden p-2 hover:text-gold-600 lg:inline-flex" aria-label="{{ __('shop.nav.wishlist') }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
            </a>
            <a href="{{ auth()->check() ? route('account') : route('login') }}" class="hidden p-2 hover:text-gold-600 lg:inline-flex" aria-label="{{ __('shop.nav.account') }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.1a7.5 7.5 0 0115 0A17.9 17.9 0 0112 21.75c-2.68 0-5.22-.58-7.5-1.65z"/></svg>
            </a>
            <a href="{{ route('cart.show') }}" class="relative p-2 hover:text-gold-600" aria-label="{{ __('shop.nav.cart') }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z"/></svg>
                @if ($cartCount ?? 0)
                    <span class="absolute -top-0.5 -end-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-gold-600 px-1 text-[10px] text-white">{{ $cartCount }}</span>
                @endif
            </a>
        </div>
    </div>
    <div x-show="open" x-collapse x-cloak class="border-t border-line lg:hidden">
        <nav class="container-x flex flex-col gap-3 py-4 text-sm">
            <form action="{{ route('shop.index') }}"><input name="q" type="search" placeholder="{{ __('shop.nav.search') }}" class="input"></form>
            <a href="{{ route('shop.index') }}">{{ __('shop.nav.shop') }}</a>
            @foreach ($categories as $c)<a href="{{ route('category.show', $c) }}">{{ $c->localized('name') }}</a>@endforeach
            <a href="{{ route('gift-cards') }}">{{ __('shop.nav.gift_cards') }}</a>
            <a href="{{ route('compare') }}">{{ __('shop.nav.compare') }}</a>
            <a href="{{ route('order.track') }}">{{ __('shop.nav.track') }}</a>
        </nav>
    </div>
</header>
