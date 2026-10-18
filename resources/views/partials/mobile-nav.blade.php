{{-- App-style bottom navigation on phones and small tablets. --}}
@php
    $items = [
        ['home', route('home'), __('shop.nav.home'), 'M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75'],
        ['shop.*', route('shop.index'), __('shop.nav.shop'), 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z'],
        ['wishlist', route('wishlist'), __('shop.nav.wishlist'), 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z'],
        ['cart.*', route('cart.show'), __('shop.nav.cart'), 'M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z'],
        ['account', auth()->check() ? route('account') : route('login'), __('shop.nav.account'), 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.1a7.5 7.5 0 0115 0A17.9 17.9 0 0112 21.75c-2.68 0-5.22-.58-7.5-1.65z'],
    ];
@endphp
<nav class="pb-safe fixed inset-x-0 bottom-0 z-40 border-t border-line bg-ivory/95 backdrop-blur lg:hidden" aria-label="{{ __('shop.nav.menu') }}">
    <ul class="grid grid-cols-5">
        @foreach ($items as [$pattern, $url, $label, $icon])
            @php($active = request()->routeIs($pattern))
            <li>
                <a href="{{ $url }}" class="relative flex flex-col items-center gap-0.5 py-2 text-[0.625rem] {{ $active ? 'text-gold-700' : 'text-muted' }}" @if($active) aria-current="page" @endif>
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="{{ $active ? 2 : 1.5 }}" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                    <span>{{ $label }}</span>
                    @if ($pattern === 'cart.*' && ($cartCount ?? 0))
                        <span class="absolute top-1 start-1/2 ms-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-gold-silver px-1 text-[0.625rem] text-zinc-900">{{ $cartCount }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</nav>
