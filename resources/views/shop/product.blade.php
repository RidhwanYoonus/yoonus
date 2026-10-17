@extends('layouts.app')
@php
    use App\Pricing\Money;
    $name = $product->localized('name');
    $images = $product->imageUrls() ?: [$product->primaryImage()];
    $shareText = __('shop.whatsapp.share', ['name' => $name, 'price' => Money::format($price->total), 'url' => route('product.show', $product)]);
    $enquireText = __('shop.whatsapp.enquire', ['name' => $name, 'sku' => $product->sku, 'karat' => $product->goldCategory->name, 'weight' => $product->weight_grams, 'url' => route('product.show', $product)]);
    $fmt = fn ($f) => Money::format($f);
@endphp

@section('content')
<div class="container-x py-10">
    <nav class="text-xs text-muted" aria-label="breadcrumb">
        <a href="{{ route('home') }}">{{ __('shop.nav.home') }}</a> /
        @if ($product->category)<a href="{{ route('category.show', $product->category) }}">{{ $product->category->localized('name') }}</a> /@endif
        <span>{{ $name }}</span>
    </nav>

    <div class="mt-6 grid gap-10 lg:grid-cols-2">
        <div x-data="{ active: 0 }">
            <div class="overflow-hidden rounded-3xl border border-line bg-sand">
                @foreach ($images as $i => $img)
                    <img x-show="active === {{ $i }}" @if($i) x-cloak @endif src="{{ $img }}" alt="{{ $name }}" class="aspect-square w-full object-cover" loading="{{ $i ? 'lazy' : 'eager' }}" width="900" height="900">
                @endforeach
            </div>
            @if (count($images) > 1)
                <div class="mt-3 flex gap-3">
                    @foreach ($images as $i => $img)
                        <button @click="active = {{ $i }}" class="h-20 w-20 overflow-hidden rounded-xl border" :class="active === {{ $i }} ? 'border-gold-500' : 'border-line'"><img src="{{ $img }}" alt="" class="h-full w-full object-cover" loading="lazy"></button>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <p class="eyebrow">{{ $product->goldCategory->name }} {{ __('shop.product.gold') }} · {{ $product->category?->localized('name') }}</p>
            <h1 class="font-display mt-2 text-4xl">{{ $name }}</h1>
            <p class="mt-1 text-xs text-muted">SKU {{ $product->sku }} @if($product->hallmark_ref) · {{ __('shop.product.hallmark') }} {{ $product->hallmark_ref }} @endif</p>

            <div class="mt-6 flex flex-wrap items-baseline gap-3 price">
                <span class="text-3xl font-semibold text-gold-700">{{ $fmt($price->total) }}</span>
                @if ($price->hasDiscount())
                    <s class="text-lg text-muted">{{ $fmt($price->originalTotal) }}</s>
                    <span class="rounded-full bg-gold-600 px-3 py-1 text-xs font-semibold text-white">{{ __('shop.product.save_amount', ['amount' => $fmt($price->savings())]) }}</span>
                @endif
            </div>
            <p class="mt-1 text-xs text-muted">{{ __('shop.product.incl_vat', ['vat' => rtrim(rtrim($price->vatPercent(), '0'), '.')]) }}</p>
            <p class="mt-2 text-xs text-muted">{{ __('shop.product.valid_as_of', ['time' => now()->locale(app()->getLocale())->isoFormat('LLL'), 'rate' => Money::format($price->rateFils)]) }}</p>

            <div class="mt-8 card overflow-hidden">
                <p class="border-b border-line bg-sand/60 px-5 py-3 text-xs font-medium uppercase tracking-wider text-muted">{{ __('shop.breakdown.title') }}</p>
                <table class="w-full text-sm price">
                    <tbody class="divide-y divide-line">
                        <tr><td class="px-5 py-2.5">{{ __('shop.breakdown.gold', ['weight' => $product->weight_grams, 'rate' => Money::toAed($price->rateFils)]) }}</td><td class="px-5 py-2.5 text-end">{{ $fmt($price->goldValue) }}</td></tr>
                        <tr><td class="px-5 py-2.5">{{ __('shop.breakdown.making') }} @if($product->manufacturer_charge_type === 'PERCENTAGE')<span class="text-muted">({{ rtrim(rtrim((string) $product->manufacturer_charge_value, '0'), '.') }}%)</span>@endif</td><td class="px-5 py-2.5 text-end">{{ $fmt($price->makingFee) }}</td></tr>
                        @if ($price->stoneValue)<tr><td class="px-5 py-2.5">{{ __('shop.breakdown.stone') }} @if($product->stone_type)<span class="text-muted">({{ $product->stone_type }})</span>@endif</td><td class="px-5 py-2.5 text-end">{{ $fmt($price->stoneValue) }}</td></tr>@endif
                        @if ($price->wastage)<tr><td class="px-5 py-2.5">{{ __('shop.breakdown.wastage') }} <span class="text-muted">({{ rtrim(rtrim((string) $product->wastage_percent, '0'), '.') }}%)</span></td><td class="px-5 py-2.5 text-end">{{ $fmt($price->wastage) }}</td></tr>@endif
                        @if ($price->rounding)<tr><td class="px-5 py-2.5">{{ __('shop.breakdown.rounding') }}</td><td class="px-5 py-2.5 text-end">{{ $fmt($price->rounding) }}</td></tr>@endif
                        @if ($price->hasDiscount())<tr class="text-gold-700"><td class="px-5 py-2.5">{{ __('shop.breakdown.discount') }}</td><td class="px-5 py-2.5 text-end">− {{ $fmt($price->discount) }}</td></tr>@endif
                        <tr><td class="px-5 py-2.5">{{ __('shop.breakdown.vat', ['vat' => rtrim(rtrim($price->vatPercent(), '0'), '.')]) }}</td><td class="px-5 py-2.5 text-end">{{ $fmt($price->vat) }}</td></tr>
                        <tr class="bg-sand/40 font-semibold"><td class="px-5 py-3">{{ __('shop.breakdown.total') }}</td><td class="px-5 py-3 text-end text-gold-700">{{ $fmt($price->total) }}</td></tr>
                    </tbody>
                </table>
            </div>

            @if ($product->inStock())
                <form method="POST" action="{{ route('cart.add', $product) }}" class="mt-6 flex gap-3">
                    @csrf
                    <label class="sr-only" for="qty">{{ __('shop.cart.qty') }}</label>
                    <input id="qty" type="number" name="qty" value="1" min="1" max="{{ $product->stock_qty }}" class="input w-20 text-center">
                    <button class="btn-dark flex-1">{{ __('shop.product.add_to_cart') }}</button>
                </form>
                @if ($product->isLowStock())<p class="mt-2 text-xs text-red-700">{{ trans_choice('shop.product.only_left', $product->stock_qty, ['count' => $product->stock_qty]) }}</p>@endif
            @else
                <p class="mt-6 rounded-xl bg-sand px-4 py-3 text-sm">{{ __('shop.product.out_of_stock') }}</p>
            @endif

            <div class="mt-3 grid grid-cols-2 gap-3">
                <a href="{{ \App\Messaging\WhatsApp::link($whatsapp, $enquireText) }}" target="_blank" rel="noopener" class="btn-whatsapp">{{ __('shop.whatsapp.enquire_cta') }}</a>
                <a href="https://wa.me/?text={{ rawurlencode($shareText) }}" target="_blank" rel="noopener" class="btn-outline">{{ __('shop.whatsapp.share_cta') }}</a>
            </div>
            <div class="mt-3 flex gap-4 text-sm">
                <form method="POST" action="{{ route('wishlist.toggle', $product) }}">@csrf<button class="text-muted hover:text-gold-700">{{ $inWishlist ? '♥ '.__('shop.wishlist.remove') : '♡ '.__('shop.wishlist.add') }}</button></form>
                <form method="POST" action="{{ route('compare.toggle', $product) }}">@csrf<button class="text-muted hover:text-gold-700">⇄ {{ __('shop.compare.add') }}</button></form>
            </div>

            @if ($product->localized('description'))
                <div class="prose mt-8 max-w-none text-sm leading-relaxed text-muted">{!! nl2br(e($product->localized('description'))) !!}</div>
            @endif
            <dl class="mt-6 grid grid-cols-2 gap-3 text-sm">
                <div class="card px-4 py-3"><dt class="text-xs text-muted">{{ __('shop.product.purity') }}</dt><dd class="font-medium">{{ $product->goldCategory->name }} ({{ $product->goldCategory->purity_percent }}%)</dd></div>
                <div class="card px-4 py-3"><dt class="text-xs text-muted">{{ __('shop.product.weight') }}</dt><dd class="font-medium">{{ $product->weight_grams }} {{ __('shop.product.g') }}</dd></div>
            </dl>
        </div>
    </div>

    <section class="mt-20 grid gap-10 lg:grid-cols-[1fr_380px]" id="reviews">
        <div>
            <h2 class="font-display text-3xl">{{ __('shop.reviews.title') }}</h2>
            @forelse ($reviews as $r)
                <article class="border-b border-line py-5">
                    <p class="text-gold-600" aria-label="{{ $r->rating }}/5">{{ str_repeat('★', $r->rating) }}<span class="text-line">{{ str_repeat('★', 5 - $r->rating) }}</span></p>
                    <p class="mt-1 text-sm font-medium">{{ $r->author_name }} @if($r->verified_purchase)<span class="ms-2 text-xs text-green-700">✓ {{ __('shop.reviews.verified') }}</span>@endif</p>
                    <p class="mt-2 text-sm text-muted">{{ $r->body }}</p>
                    @if ($r->photos)<div class="mt-3 flex gap-2">@foreach ($r->photoUrls() as $ph)<img src="{{ $ph }}" alt="" class="h-20 w-20 rounded-lg object-cover" loading="lazy">@endforeach</div>@endif
                </article>
            @empty
                <p class="mt-4 text-sm text-muted">{{ __('shop.reviews.none') }}</p>
            @endforelse
        </div>
        <form method="POST" action="{{ route('reviews.store', $product) }}" enctype="multipart/form-data" class="card h-fit space-y-4 p-6">
            @csrf
            <h3 class="font-display text-xl">{{ __('shop.reviews.write') }}</h3>
            <div><label class="label" for="author_name">{{ __('shop.reviews.name') }}</label><input id="author_name" name="author_name" required class="input" value="{{ old('author_name', auth()->user()?->name) }}"></div>
            <div><label class="label" for="rating">{{ __('shop.reviews.rating') }}</label><select id="rating" name="rating" class="input">@for ($i = 5; $i >= 1; $i--)<option value="{{ $i }}">{{ str_repeat('★', $i) }}</option>@endfor</select></div>
            <div><label class="label" for="body">{{ __('shop.reviews.body') }}</label><textarea id="body" name="body" rows="4" required minlength="10" class="input">{{ old('body') }}</textarea></div>
            <div><label class="label" for="photos">{{ __('shop.reviews.photos') }}</label><input id="photos" type="file" name="photos[]" accept="image/*" multiple class="text-xs"></div>
            <button class="btn-gold w-full">{{ __('shop.reviews.submit') }}</button>
            <p class="text-xs text-muted">{{ __('shop.reviews.moderated') }}</p>
        </form>
    </section>

    @if ($related->isNotEmpty())
        <section class="mt-20">
            <h2 class="font-display text-3xl">{{ __('shop.product.related') }}</h2>
            <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-8 md:grid-cols-4">@foreach ($related as $rp)@include('partials.product-card', ['product' => $rp])@endforeach</div>
        </section>
    @endif
</div>
@endsection

@push('scripts')
<script>document.addEventListener('DOMContentLoaded', () => shopTrack('view_item', @js(['sku' => $product->sku, 'item_name' => $product->name, 'value' => $price->total / 100])));</script>
@endpush
