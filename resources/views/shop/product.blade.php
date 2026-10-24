@extends('layouts.app')
@php
    $focus = true;   // sticky add-to-bag bar replaces the bottom nav on phones
    use App\Pricing\Money;
    $name = $product->localized('name');
    $images = $product->imageUrls() ?: [$product->primaryImage()];
    $shareText = __('shop.whatsapp.share', ['name' => $name, 'price' => Money::format($price->taxable).' + VAT', 'url' => route('product.show', $product)]);
    $enquireText = __('shop.whatsapp.enquire', ['name' => $name, 'sku' => $product->sku, 'karat' => $product->goldCategory?->name ?? '-', 'weight' => $product->weight_grams, 'url' => route('product.show', $product)]);
    $fmt = fn ($f) => Money::format($f);
@endphp

@section('content')
<div class="container-x py-6 sm:py-10">
    <nav class="text-xs text-muted" aria-label="breadcrumb">
        <a href="{{ route('home') }}">{{ __('shop.nav.home') }}</a> /
        @if ($product->category)<a href="{{ route('category.show', $product->category) }}">{{ $product->category->localized('name') }}</a> /@endif
        <span>{{ $name }}</span>
    </nav>

    <div class="mt-6 grid gap-10 lg:grid-cols-2">
        <div x-data="{ active: 0 }">
            <div class="relative overflow-hidden rounded-3xl border border-line bg-sand" data-fly-scope>
                @foreach ($images as $i => $img)
                    <img x-show="active === {{ $i }}" @if($i) x-cloak @endif src="{{ $img }}" alt="{{ $name }}" class="aspect-square w-full object-cover {{ $product->inStock() ? '' : 'grayscale-[35%]' }}" loading="{{ $i ? 'lazy' : 'eager' }}" width="900" height="900">
                @endforeach
                @unless ($product->inStock())
                    @include('partials.sold-watermark', ['large' => true])
                @endunless
                @include('partials.diamond-specs', ['large' => true])
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
            <p class="eyebrow">{{ collect([$product->goldCategory ? $product->goldCategory->name.' '.__('shop.product.gold') : null, $product->category?->localized('name')])->filter()->join(' · ') }}</p>
            <h1 class="font-display mt-2 text-3xl sm:text-4xl">{{ $name }}</h1>
            @if (\App\Support\StaffAccess::current('products', 'edit'))
                <a href="{{ \App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $product]) }}" class="mt-2 inline-flex items-center gap-1 rounded-full border border-gold-500 px-3 py-1 text-xs text-gold-700 hover:bg-gold-600 hover:text-white">✎ Edit in admin</a>
            @endif
            <p class="mt-1 text-xs text-muted">SKU {{ $product->sku }} @if($product->hallmark_ref) · {{ __('shop.product.hallmark') }} {{ $product->hallmark_ref }} @endif</p>

            @if ($badge = $product->makingBadge())
                <span class="mt-4 inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $badge['highlight'] ? 'bg-onyx text-gold-300' : 'bg-sand text-ink/70' }}">{{ $badge['text'] }}</span>
            @endif
            <div class="mt-4 flex flex-wrap items-baseline gap-3 price">
                <span class="text-price text-3xl font-bold">{{ $fmt($price->taxable) }}</span>
                @if ($price->hasDiscount())
                    <s class="text-lg text-muted">{{ $fmt($price->subtotal) }}</s>
                    <span class="rounded-full bg-gold-silver px-3 py-1 text-xs font-semibold text-zinc-900">{{ __('shop.product.save_amount', ['amount' => $fmt($price->subtotal - $price->taxable)]) }}</span>
                @endif
            </div>
            <p class="mt-1 text-xs text-muted">{{ __('shop.product.plus_vat', ['vat' => rtrim(rtrim($price->vatPercent(), '0'), '.')]) }}</p>
            @unless ($product->isFixedPrice())
                <p class="mt-2 text-xs text-muted">{{ __('shop.product.valid_as_of', ['time' => now()->locale(app()->getLocale())->isoFormat('LLL'), 'rate' => Money::format($price->rateFils)]) }}</p>
            @endunless

            <div class="mt-8 card overflow-hidden">
                <p class="border-b border-line bg-sand/60 px-5 py-3 text-xs font-medium uppercase tracking-wider text-muted">{{ __('shop.breakdown.title') }}</p>
                <table class="w-full text-sm price">
                    <tbody class="divide-y divide-line">
                        @unless ($product->isFixedPrice())
                        <tr><td class="px-5 py-2.5">{{ __('shop.breakdown.gold', ['weight' => $product->weight_grams, 'rate' => Money::toAed($price->rateFils)]) }}</td><td class="px-5 py-2.5 text-end">{{ $fmt($price->goldValue) }}</td></tr>
                        <tr><td class="px-5 py-2.5">{{ __('shop.breakdown.making') }} @if($product->manufacturer_charge_type === 'PERCENTAGE' && ! app(\App\Pricing\PricingService::class)->markup()->isActive())<span class="text-muted">({{ rtrim(rtrim((string) $product->manufacturer_charge_value, '0'), '.') }}%)</span>@endif</td><td class="px-5 py-2.5 text-end">{{ $fmt($price->makingFee) }}</td></tr>
                        @endunless
                        @if ($price->stoneValue)<tr><td class="px-5 py-2.5">{{ __('shop.breakdown.stone') }} @if($product->stone_type)<span class="text-muted">({{ $product->stoneLabel(', ') }})</span>@endif</td><td class="px-5 py-2.5 text-end">{{ $fmt($price->stoneValue) }}</td></tr>@endif
                        @if ($price->wastage)<tr><td class="px-5 py-2.5">{{ __('shop.breakdown.wastage') }} <span class="text-muted">({{ rtrim(rtrim((string) $product->wastage_percent, '0'), '.') }}%)</span></td><td class="px-5 py-2.5 text-end">{{ $fmt($price->wastage) }}</td></tr>@endif
                        @if ($price->rounding)<tr><td class="px-5 py-2.5">{{ __('shop.breakdown.rounding') }}</td><td class="px-5 py-2.5 text-end">{{ $fmt($price->rounding) }}</td></tr>@endif
                        @if ($price->hasDiscount())<tr class="text-gold-700"><td class="px-5 py-2.5">{{ __('shop.breakdown.discount') }}</td><td class="px-5 py-2.5 text-end">− {{ $fmt($price->discount) }}</td></tr>@endif
                        <tr class="bg-sand/40 font-semibold"><td class="px-5 py-3">{{ __('shop.breakdown.price_ex_vat') }}</td><td class="px-5 py-3 text-end text-price">{{ $fmt($price->taxable) }}</td></tr>
                    </tbody>
                </table>
                <p class="border-t border-line px-5 py-2.5 text-xs text-muted">{{ __('shop.breakdown.vat_at_checkout', ['vat' => rtrim(rtrim($price->vatPercent(), '0'), '.')]) }}</p>
            </div>

            @if ($product->stoneLabel())
                <p class="mt-4 text-sm"><span class="text-muted">{{ __('shop.product.stones') }}:</span> <span class="font-medium">{{ $product->stoneLabel() }}</span></p>
            @endif

            @if ($product->hasCertificate())
                @include('partials.certificate', ['product' => $product])
            @endif

            @if ($product->inStock())
                <form id="add-to-bag" method="POST" action="{{ route('cart.add', $product) }}" data-add-to-bag class="mt-6 flex flex-wrap gap-3">
                    @csrf
                    {{-- Size / gold colour the customer picks (set per product in the admin). --}}
                    @if ($product->sizeList())
                        <div class="w-full sm:flex-1"><label class="label" for="opt-size">{{ __('shop.product.size') }}</label>
                            <select id="opt-size" name="size" required class="input"><option value="">{{ __('shop.product.choose') }}</option>
                                @foreach ($product->sizeList() as $s)<option value="{{ $s }}" @selected(old('size') === $s)>{{ $s }}</option>@endforeach</select></div>
                    @endif
                    @if ($product->colourList())
                        <div class="w-full sm:flex-1"><label class="label" for="opt-colour">{{ __('shop.product.gold_colour') }}</label>
                            <select id="opt-colour" name="colour" required class="input">
                                @if (count($product->colourList()) > 1)<option value="">{{ __('shop.product.choose') }}</option>@endif
                                @foreach ($product->colourList() as $c)<option value="{{ $c }}" @selected(old('colour') === $c)>{{ \App\Models\Product::colourLabel($c) }}</option>@endforeach</select></div>
                    @endif
                    @if ($product->needsOptions())<div class="basis-full h-0"></div>@endif
                    <label class="sr-only" for="qty">{{ __('shop.cart.qty') }}</label>
                    <input id="qty" type="number" name="qty" value="1" min="1" max="{{ $product->stock_qty }}" class="input w-20 text-center">
                    <button class="btn-gold h-11 flex-1">{{ __('shop.product.add_to_cart') }}</button>
                </form>
                <p class="mt-2 text-xs text-muted">🔒 <a href="{{ route('policy.show', 'refund-policy') }}" class="underline decoration-dotted">{{ __('shop.policy.note') }}</a></p>
                @unless ($deliveryEnabled)<p class="mt-2 text-xs text-gold-700">{{ __('shop.pickup.only_pickup') }}</p>@endunless
                @if ($product->isLowStock())<p class="mt-2 text-xs text-red-700">{{ trans_choice('shop.product.only_left', $product->stock_qty, ['count' => $product->stock_qty]) }}</p>@endif
            @else
                <p class="mt-6 rounded-xl bg-sand px-4 py-3 text-sm">{{ __('shop.product.out_of_stock') }}</p>
            @endif

            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <a href="{{ \App\Messaging\WhatsApp::link($whatsapp, $enquireText) }}" target="_blank" rel="noopener" class="btn-whatsapp">{{ __('shop.whatsapp.enquire_cta') }}</a>
                {{-- Share: the phone's own share sheet (any app); on computers a small menu. --}}
                @php($shareUrl = route('product.show', $product))
                <div class="relative" x-data="{ menu: false, copied: false,
                        async share() {
                            if (navigator.share) { try { await navigator.share({ title: @js($name), text: @js($shareText), url: @js($shareUrl) }); } catch (e) {} return; }
                            this.menu = ! this.menu;
                        },
                        copy() { navigator.clipboard?.writeText(@js($shareUrl)); this.copied = true; setTimeout(() => this.copied = false, 2000); } }" @click.outside="menu = false">
                    <button type="button" @click="share()" class="btn-outline w-full gap-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7.2 10.9a2.25 2.25 0 100 2.2m0-2.2c.2.3.3.7.3 1.1s-.1.8-.3 1.1m0-2.2l9.6-5.3m-9.6 7.5l9.6 5.3m0 0a2.25 2.25 0 103.9 2.2 2.25 2.25 0 00-3.9-2.2zm0-12.8a2.25 2.25 0 103.9-2.2 2.25 2.25 0 00-3.9 2.2z"/></svg>
                        {{ __('shop.whatsapp.share_cta') }}</button>
                    <div x-show="menu" x-cloak x-transition class="card absolute inset-x-0 top-full z-20 mt-2 grid gap-1 p-2 text-sm shadow-lg">
                        <a class="rounded-lg px-3 py-2 hover:bg-sand" target="_blank" rel="noopener" href="https://wa.me/?text={{ rawurlencode($shareText) }}">WhatsApp</a>
                        <a class="rounded-lg px-3 py-2 hover:bg-sand" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($shareUrl) }}">Facebook</a>
                        <a class="rounded-lg px-3 py-2 hover:bg-sand" target="_blank" rel="noopener" href="https://t.me/share/url?url={{ rawurlencode($shareUrl) }}&text={{ rawurlencode($name) }}">Telegram</a>
                        <a class="rounded-lg px-3 py-2 hover:bg-sand" target="_blank" rel="noopener" href="https://x.com/intent/post?url={{ rawurlencode($shareUrl) }}&text={{ rawurlencode($name) }}">X (Twitter)</a>
                        <a class="rounded-lg px-3 py-2 hover:bg-sand" href="mailto:?subject={{ rawurlencode($name) }}&body={{ rawurlencode($shareText) }}">{{ __('shop.share.email') }}</a>
                        <button type="button" class="rounded-lg px-3 py-2 text-start hover:bg-sand" @click="copy()"><span x-show="!copied">{{ __('shop.share.copy') }}</span><span x-show="copied" x-cloak class="text-green-700">✓ {{ __('shop.share.copied') }}</span></button>
                    </div>
                </div>
            </div>

            @if ($lock = \App\Affiliates\PriceLocks::forProduct($product->id))
                <p class="mt-3 rounded-xl border border-gold-400 bg-gold-300/20 px-4 py-2.5 text-sm">⏱ {{ __('shop.affiliate.lock_badge', ['time' => $lock->expires_at->locale(app()->getLocale())->isoFormat('lll')]) }}</p>
            @endif

            {{-- Affiliates: share this piece with their link --}}
            @php($me = auth()->check() ? rescue(fn () => auth()->user()->affiliate, null, false) : null)
            @if ($me && $me->is_active)
                @php($myLink = route('product.show', $product).'?aff='.$me->code.($me->white_page_enabled ? '&w=1' : ''))
                @php($myText = __('shop.affiliate.caption', ['name' => $product->localized('name'), 'karat' => $product->goldCategory?->name ?? '', 'url' => $myLink]))
                <div class="mt-3 rounded-xl border border-gold-300 bg-sand/60 p-4" x-data="{ c: false }">
                    <p class="text-sm font-medium">🤝 {{ __('shop.affiliate.share_earn') }}</p>
                    <div class="mt-2 flex gap-2">
                        <a class="btn-whatsapp flex-1" target="_blank" rel="noopener" href="https://wa.me/?text={{ rawurlencode($myText) }}">WhatsApp</a>
                        <button type="button" class="btn-outline flex-1" @click="navigator.clipboard?.writeText(@js($myLink)); c = true; setTimeout(() => c = false, 1500)" x-text="c ? '✓' : @js(__('shop.affiliate.copy_link'))"></button>
                    </div>
                </div>
            @endif

            {{-- Book a store visit to see the piece (Settings → Video calls can hide it) --}}
            @if (\App\Support\Settings::bool('store_visit_button', true))
            <div x-data="{ open: {{ $errors->has('preferred_at') ? 'true' : 'false' }} }" class="mt-3">
                <button type="button" @click="open = !open" class="btn-outline w-full">🏬 {{ __('shop.affiliate.visit_cta') }}</button>
                <form x-show="open" x-cloak method="POST" action="{{ route('store-visit.store') }}" class="card mt-2 grid gap-3 p-4 text-sm">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input name="name" required maxlength="120" placeholder="{{ __('shop.affiliate.visit_name') }}" class="input" value="{{ auth()->user()?->name }}">
                    <input name="phone" required maxlength="32" placeholder="+971 5X XXX XXXX" class="input" dir="ltr" value="{{ auth()->user()?->phone }}">
                    <input name="preferred_at" type="datetime-local" class="input" min="{{ now()->addHour()->format('Y-m-d\TH:i') }}" aria-label="{{ __('shop.affiliate.visit_when') }}">
                    @php($visitBranches = \App\Models\Branch::orderBy('name')->get())
                    @if ($visitBranches->count() > 1)
                        <select name="branch_id" class="input">@foreach ($visitBranches as $b)<option value="{{ $b->id }}">{{ $b->localized('name') }}</option>@endforeach</select>
                    @elseif ($visitBranches->count() === 1)<input type="hidden" name="branch_id" value="{{ $visitBranches->first()->id }}">@endif
                    <button class="btn-gold h-10">{{ __('shop.affiliate.visit_send') }}</button>
                </form>
            </div>
            @endif
            @if ($product->videoCallAvailable())
                <div x-data="{ open: {{ $errors->has('name') || $errors->has('phone') ? 'true' : 'false' }} }" class="mt-3">
                    <button type="button" @click="open = true" class="btn-outline w-full gap-2">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 10l4.55-2.28A1 1 0 0121 8.62v6.76a1 1 0 01-1.45.9L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        {{ __('shop.video.cta') }}
                    </button>
                    <p class="mt-1 text-center text-xs text-muted">{{ __('shop.video.hint', ['hours' => \App\Support\Settings::get('video_call_hours')]) }}</p>
                    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-4 sm:items-center" @keydown.escape.window="open = false" @click.self="open = false">
                        <form method="POST" action="{{ route('video-call.store', $product) }}" class="card w-full max-w-md space-y-4 p-6">
                            @csrf
                            <div class="flex items-start justify-between gap-4">
                                <div><h2 class="font-display text-2xl">{{ __('shop.video.title') }}</h2><p class="mt-1 text-sm text-muted">{{ __('shop.video.intro', ['name' => $name]) }}</p></div>
                                <button type="button" @click="open = false" class="text-2xl leading-none text-muted" aria-label="{{ __('shop.video.close') }}">×</button>
                            </div>
                            <div><label class="label" for="vc_name">{{ __('shop.checkout.name') }}</label><input id="vc_name" name="name" value="{{ old('name', auth()->user()?->name) }}" required class="input" autocomplete="name">@error('name')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                            <div><label class="label" for="vc_phone">{{ __('shop.checkout.phone') }}</label><input id="vc_phone" name="phone" value="{{ old('phone', auth()->user()?->phone) }}" required class="input" dir="ltr" placeholder="+971 50 000 0000" autocomplete="tel">@error('phone')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                            <fieldset><legend class="label">{{ __('shop.video.platform') }}</legend>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    @foreach (\App\Models\VideoCallRequest::PLATFORMS as $key => $label)
                                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-line px-3 py-2 has-[:checked]:border-gold-500 has-[:checked]:bg-gold-300/15">
                                            <input type="radio" name="platform" value="{{ $key }}" @checked(old('platform', 'botim') === $key) required class="accent-gold-600"> {{ $label }}</label>
                                    @endforeach
                                </div>@error('platform')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</fieldset>
                            <p class="text-xs text-muted">{{ __('shop.video.privacy') }}</p>
                            <button class="btn-dark w-full">{{ __('shop.video.start') }}</button>
                        </form>
                    </div>
                </div>
            @endif
            <div class="mt-3 flex gap-4 text-sm">
                <form method="POST" action="{{ route('wishlist.toggle', $product) }}">@csrf<button class="{{ $inWishlist ? 'font-semibold text-gold-700' : 'text-muted hover:text-gold-700' }}" aria-pressed="{{ $inWishlist ? 'true' : 'false' }}">{{ $inWishlist ? '♥ '.__('shop.wishlist.saved').' · '.__('shop.wishlist.remove') : '♡ '.__('shop.wishlist.add') }}</button></form>
                <form method="POST" action="{{ route('compare.toggle', $product) }}">@csrf<button class="text-muted hover:text-gold-700">⇄ {{ __('shop.compare.add') }}</button></form>
            </div>

            @if ($product->localized('description'))
                <div class="prose mt-8 max-w-none text-sm leading-relaxed text-muted">{!! nl2br(e($product->localized('description'))) !!}</div>
            @endif
            <dl class="mt-6 grid grid-cols-2 gap-3 text-sm">
                @if ($product->goldCategory)<div class="card px-4 py-3"><dt class="text-xs text-muted">{{ __('shop.product.purity') }}</dt><dd class="font-medium">{{ $product->goldCategory->name }} ({{ $product->goldCategory->purity_percent }}%)</dd></div>@endif
                @if ((float) $product->weight_grams > 0)<div class="card px-4 py-3"><dt class="text-xs text-muted">{{ __('shop.product.weight') }}</dt><dd class="font-medium">{{ $product->weight_grams }} {{ __('shop.product.g') }}</dd></div>@endif
            </dl>
        </div>
    </div>

    @if ($storeBranches->isNotEmpty())
        <section class="mt-20" id="collect">
            <p class="text-xs uppercase tracking-[0.3em] text-gold-700">{{ __('shop.pickup.eyebrow') }}</p>
            <h2 class="mt-2 font-display text-3xl">{{ __('shop.pickup.title') }}</h2>
            <p class="mt-2 max-w-2xl text-sm text-muted">{{ $deliveryEnabled ? __('shop.pickup.intro') : \App\Support\Settings::get(app()->getLocale() === 'ar' ? 'delivery_off_message_ar' : 'delivery_off_message') }}</p>
            <div class="mt-6 grid gap-6 {{ $storeBranches->count() > 1 ? 'md:grid-cols-2' : '' }}">
                @foreach ($storeBranches as $b)@include('partials.store-map', ['branch' => $b, 'height' => 'h-64 sm:h-80'])@endforeach
            </div>
        </section>
    @endif

    <section class="mt-20 grid gap-10 lg:grid-cols-[1fr_24rem]" id="reviews">
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

    {{-- Phones: price + Add to bag always in reach. --}}
    <div class="pb-safe fixed inset-x-0 bottom-0 z-40 border-t border-line bg-ivory/95 backdrop-blur lg:hidden">
        <div class="container-x flex items-center gap-3 py-3">
            <div class="min-w-0 flex-1">
                <p class="price text-price truncate font-bold">{{ $fmt($price->taxable) }}</p>
                <p class="text-[0.6875rem] text-muted">{{ __('shop.product.plus_vat', ['vat' => rtrim(rtrim($price->vatPercent(), '0'), '.')]) }}</p>
            </div>
            <a href="{{ \App\Messaging\WhatsApp::link($whatsapp, $enquireText) }}" target="_blank" rel="noopener" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#25D366] text-white" aria-label="{{ __('shop.whatsapp.enquire_cta') }}">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.9 9.9 0 004.74 1.21c5.46 0 9.91-4.45 9.91-9.91S17.5 2 12.04 2zm5.8 14.02c-.24.68-1.41 1.3-1.95 1.38-.5.07-1.13.1-1.82-.12-.42-.13-.96-.31-1.65-.61-2.9-1.25-4.79-4.17-4.94-4.36-.14-.19-1.18-1.57-1.18-3s.75-2.13 1.02-2.42c.27-.29.58-.36.78-.36h.56c.18 0 .42-.07.66.5.24.58.82 2.01.89 2.16.07.14.12.31.02.5-.1.19-.14.31-.29.48-.14.17-.3.38-.43.5-.14.14-.29.3-.13.59.17.29.74 1.22 1.59 1.98 1.09.97 2.01 1.27 2.3 1.42.29.14.46.12.63-.07.17-.19.72-.84.91-1.13.19-.29.38-.24.65-.14.26.1 1.68.79 1.97.94.29.14.48.21.55.33.07.12.07.7-.17 1.38z"/></svg>
            </a>
            @if ($product->inStock())
                <button form="add-to-bag" class="btn-gold shrink-0 px-5">{{ __('shop.product.add_to_cart') }}</button>
            @else
                <span class="shrink-0 rounded-full bg-sand px-4 py-2.5 text-xs">{{ __('shop.product.sold_out') }}</span>
            @endif
        </div>
    </div>

    @if ($related->isNotEmpty())
        <section class="mt-20">
            <h2 class="font-display text-3xl">{{ __('shop.product.related') }}</h2>
            <div class="mt-6 grid-products">@foreach ($related as $rp)@include('partials.product-card', ['product' => $rp])@endforeach</div>
        </section>
    @endif
</div>
@endsection

@push('scripts')
<script>document.addEventListener('DOMContentLoaded', () => shopTrack('view_item', @js(['sku' => $product->sku, 'item_name' => $product->name, 'value' => $price->taxable / 100])));</script>
@endpush
