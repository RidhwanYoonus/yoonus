@php($price = $product->displayPrice())
<article class="group relative">
    <a href="{{ route('product.show', $product) }}" class="block overflow-hidden rounded-2xl border border-line bg-sand">
        <img src="{{ $product->primaryImage() }}" alt="{{ $product->localized('name') }}" loading="lazy" decoding="async" width="600" height="600"
             class="aspect-square w-full object-cover transition duration-500 group-hover:scale-105">
        @if ($price->hasDiscount())
            <span class="absolute top-3 start-3 rounded-full bg-gold-600 px-2.5 py-1 text-[11px] font-semibold text-white">{{ __('shop.product.save_percent', ['percent' => $price->savingsPercent()]) }}</span>
        @endif
        @unless ($product->inStock())
            <span class="absolute top-3 end-3 rounded-full bg-onyx/80 px-2.5 py-1 text-[11px] text-ivory">{{ __('shop.product.sold_out') }}</span>
        @endunless
    </a>
    <div class="mt-3 px-1">
        <p class="text-[11px] uppercase tracking-widest text-muted">{{ $product->goldCategory->name }} · {{ rtrim(rtrim((string) $product->weight_grams, '0'), '.') }} {{ __('shop.product.g') }}</p>
        <h3 class="mt-1 line-clamp-1 text-sm font-medium"><a href="{{ route('product.show', $product) }}" class="hover:text-gold-700">{{ $product->localized('name') }}</a></h3>
        <p class="price mt-1 text-sm">
            <span class="font-semibold text-gold-700">{{ \App\Pricing\Money::format($price->total) }}</span>
            @if ($price->hasDiscount())
                <s class="ms-1 text-xs text-muted">{{ \App\Pricing\Money::format($price->originalTotal) }}</s>
            @endif
        </p>
        <p class="text-[11px] text-muted">{{ __('shop.product.incl_vat', ['vat' => rtrim(rtrim($price->vatPercent(), '0'), '.')]) }}</p>
    </div>
</article>
