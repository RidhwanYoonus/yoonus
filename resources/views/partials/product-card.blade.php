@php($price = $product->displayPrice())
<article class="group relative">
    <div class="relative overflow-hidden rounded-2xl border border-line bg-gradient-to-b from-white to-sand transition duration-300 group-hover:-translate-y-1 group-hover:border-gold-300 group-hover:shadow-[0_20px_45px_-20px_rgba(122,95,34,.45)]">
        <a href="{{ route('product.show', $product) }}" class="block">
            <img src="{{ $product->primaryImage() }}" alt="{{ $product->localized('name') }}" loading="lazy" decoding="async" width="600" height="600"
                 class="aspect-square w-full object-cover transition duration-700 group-hover:scale-105">
        </a>
        <div class="pointer-events-none absolute inset-x-3 top-3 flex items-start justify-between">
            <span class="flex flex-col items-start gap-1.5">
                @if ($product->goldCategory)<span class="rounded-full bg-white/90 px-2.5 py-1 text-[10px] font-semibold tracking-wider text-gold-700 shadow-sm">{{ $product->goldCategory->name }}</span>@endif
                @if ($badge = $product->makingBadge())
                    <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold shadow-sm {{ $badge['highlight'] ? 'bg-onyx text-gold-300' : 'bg-white/90 text-ink/70' }}">{{ $badge['text'] }}</span>
                @endif
                @if ($price->hasDiscount())<span class="rounded-full bg-gold-600 px-2.5 py-1 text-[10px] font-semibold text-white">{{ __('shop.product.save_percent', ['percent' => $price->savingsPercent()]) }}</span>@endif
                @unless ($product->inStock())<span class="rounded-full bg-onyx/80 px-2.5 py-1 text-[10px] text-ivory">{{ __('shop.product.sold_out') }}</span>@endunless
            </span>
        </div>
        <form method="POST" action="{{ route('wishlist.toggle', $product) }}" class="absolute end-3 top-3">
            @csrf
            <button class="flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-ink/70 shadow-sm transition hover:text-gold-700" aria-label="{{ __('shop.wishlist.add') }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
            </button>
        </form>
        @if ($product->inStock())
            <form method="POST" action="{{ route('cart.add', $product) }}" class="absolute inset-x-3 bottom-3 hidden translate-y-2 opacity-0 transition duration-300 group-hover:translate-y-0 group-hover:opacity-100 lg:block">
                @csrf
                <button class="w-full rounded-full bg-onyx/90 py-2.5 text-xs font-medium uppercase tracking-[0.15em] text-ivory backdrop-blur hover:bg-onyx">{{ __('shop.product.add_to_cart') }}</button>
            </form>
        @endif
    </div>
    <div class="mt-3 px-1">
        <p class="text-[11px] uppercase tracking-[0.15em] text-muted">{{ $product->specLine() }}</p>
        <h3 class="mt-1 line-clamp-1 font-medium"><a href="{{ route('product.show', $product) }}" class="hover:text-gold-700">{{ $product->localized('name') }}</a></h3>
        <p class="price mt-1">
            <span class="font-semibold text-gold-700">{{ \App\Pricing\Money::format($price->taxable) }}</span>
            @if ($price->hasDiscount())
                <s class="ms-1 text-xs text-muted">{{ \App\Pricing\Money::format($price->subtotal) }}</s>
            @endif
        </p>
        <p class="text-[11px] text-muted">{{ __('shop.product.plus_vat', ['vat' => rtrim(rtrim($price->vatPercent(), '0'), '.')]) }}</p>
    </div>
</article>
