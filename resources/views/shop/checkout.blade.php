@extends('layouts.app')
@php
    $focus = true;   // sticky total bar replaces the bottom nav on phones
    use App\Pricing\Money;
    $user = auth()->user();
    $f = fn ($fils) => Money::format($fils);
    $initial = [
        'items_subtotal' => $f($quote->itemsSubtotal), 'discount' => $quote->discountTotal ? $f($quote->discountTotal) : null,
        'taxable' => $f($quote->taxable), 'vat' => $f($quote->vatTotal()),
        'shipping' => $quote->shipping ? $f($quote->shipping) : __('shop.checkout.free'),
        'payment_fee' => $quote->paymentFee ? $f($quote->paymentFee) : null,
        'taxable_all' => $f($quote->taxable + $quote->shipping + $quote->paymentFee),
        'grand_total' => $f($quote->grandTotal), 'grand_total_raw' => Money::toAed($quote->grandTotal),
        'gift_card' => null, 'loyalty' => null, 'amount_due' => $f($quote->amountDue),
        'coupons' => $quote->couponCodes(), 'messages' => $quote->messages, 'methods' => $methods,
        'lines' => collect($quote->lines)->map(fn ($l, $i) => ['name' => $l->product->localized('name'), 'qty' => $l->qty, 'subtotal' => $f($l->subtotal()), 'discount' => $l->discount ? $f($l->discount) : null, 'total' => $f($quote->lineTaxable($i))])->all(),
    ];
    $form = [
        'email' => old('email', $user?->email), 'phone' => old('phone', $user?->phone), 'name' => old('name', $user?->name),
        'buyer_trn' => old('buyer_trn', $user?->buyer_trn), 'fulfilment' => $deliveryEnabled ? old('fulfilment', 'delivery') : 'pickup',
        'zone_id' => (int) old('zone_id', $quote->zone?->id ?? $zones->first()?->id), 'line1' => old('line1', $addresses->first()?->line1), 'line2' => old('line2', $addresses->first()?->line2), 'city' => old('city', $addresses->first()?->city),
        'pickup_branch_id' => old('pickup_branch_id', $branches->first()?->id), 'coupon' => old('coupon', ''), 'gift_card' => old('gift_card', ''),
        'loyalty_points' => (int) old('loyalty_points', 0), 'tax_free' => old('tax_free') === '1', 'payment_method' => old('payment_method', isset($methods['card']) ? 'card' : array_key_first($methods)), 'notes' => old('notes', ''),
    ];
@endphp

@section('content')
<div class="container-x py-6 sm:py-10" x-data="checkout(@js(['form' => $form, 'quote' => $initial, 'url' => route('checkout.quote'), 'csrf' => csrf_token()]))">
    <h1 class="font-display text-3xl sm:text-4xl">{{ __('shop.checkout.title') }}</h1>

    @unless ($deliveryEnabled)
        <div class="mt-6 rounded-xl border border-gold-400 bg-gold-300/20 p-4 text-sm">{{ \App\Support\Settings::get(app()->getLocale() === 'ar' ? 'delivery_off_message_ar' : 'delivery_off_message') }}</div>
        @if ($branches->isEmpty())<div class="mt-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ __('shop.checkout.no_fulfilment') }}</div>@endif
    @endunless
    @if ($blocked)
        <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ __('shop.checkout.rates_stale') }}</div>
    @endif
    @if (session('price_updated') || $changes)
        <div class="mt-6 rounded-xl border border-gold-400 bg-gold-300/20 p-4 text-sm" role="alert">
            <p class="font-medium">{{ session('price_updated') ?? __('shop.cart.prices_updated') }}</p>
            @foreach ($changes as $c)<p class="mt-1">{{ $c['product']->localized('name') }}: <s>{{ $f($c['old']) }}</s> → <strong>{{ $f($c['new']) }}</strong></p>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('checkout.place') }}" enctype="multipart/form-data" class="mt-8 grid gap-10 lg:grid-cols-[1fr_25rem]">
        @csrf
        <input type="hidden" name="expected_total" :value="q.grand_total_raw">
        <div class="space-y-8">
            <section class="card p-4 sm:p-6">
                <h2 class="font-display text-2xl">{{ __('shop.checkout.contact') }}</h2>
                @guest<p class="mt-1 text-sm text-muted">{{ __('shop.checkout.guest_note') }} <a href="{{ route('login') }}" class="text-gold-700 underline">{{ __('shop.auth.login') }}</a></p>@endguest
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><label class="label" for="name">{{ __('shop.checkout.name') }}</label><input id="name" name="name" x-model="form.name" required class="input" autocomplete="name"></div>
                    <div><label class="label" for="phone">{{ __('shop.checkout.phone') }}</label><input id="phone" name="phone" x-model="form.phone" required class="input" dir="ltr" placeholder="+971 50 000 0000" autocomplete="tel"></div>
                    <div class="sm:col-span-2"><label class="label" for="email">{{ __('shop.checkout.email') }}</label><input id="email" type="email" name="email" x-model.lazy="form.email" required class="input" autocomplete="email"></div>
                    <div class="sm:col-span-2"><label class="label" for="buyer_trn">{{ __('shop.checkout.buyer_trn') }}</label><input id="buyer_trn" name="buyer_trn" x-model="form.buyer_trn" class="input" inputmode="numeric" maxlength="15" dir="ltr"><p class="mt-1 text-xs text-muted">{{ __('shop.checkout.buyer_trn_note') }}</p></div>
                </div>
            </section>

            <section class="card p-4 sm:p-6">
                <h2 class="font-display text-2xl">{{ __('shop.checkout.delivery') }}</h2>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    @if ($deliveryEnabled)
                        <label class="card flex cursor-pointer items-center gap-3 p-4" :class="form.fulfilment === 'delivery' && 'border-gold-500 ring-1 ring-gold-500'"><input type="radio" name="fulfilment" value="delivery" x-model="form.fulfilment" class="accent-gold-600"> {{ __('shop.checkout.home_delivery') }}</label>
                    @else
                        <div class="card flex items-center gap-3 p-4 text-muted opacity-60"><input type="radio" disabled> <span>{{ __('shop.checkout.home_delivery') }} <span class="block text-xs">{{ __('shop.checkout.delivery_paused') }}</span></span></div>
                    @endif
                    @if ($branches->isNotEmpty())
                        <label class="card flex cursor-pointer items-center gap-3 p-4" :class="form.fulfilment === 'pickup' && 'border-gold-500 ring-1 ring-gold-500'"><input type="radio" name="fulfilment" value="pickup" x-model="form.fulfilment" class="accent-gold-600"> {{ __('shop.checkout.store_pickup') }}</label>
                    @endif
                </div>
                @if ($deliveryEnabled)
                <div x-show="form.fulfilment === 'delivery'" class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2"><label class="label" for="zone_id">{{ __('shop.checkout.emirate') }}</label>
                        <select id="zone_id" name="zone_id" x-model.number="form.zone_id" class="input">
                            @foreach ($zones as $z)<option value="{{ $z->id }}">{{ $z->localized('name') }} — {{ (float) $z->flat_rate ? Money::format(Money::toFils($z->flat_rate)) : __('shop.checkout.free') }}@if($z->free_above) · {{ __('shop.checkout.free_above', ['amount' => Money::format(Money::toFils($z->free_above))]) }}@endif</option>@endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2"><label class="label" for="line1">{{ __('shop.checkout.address') }}</label><input id="line1" name="line1" x-model="form.line1" class="input" :required="form.fulfilment === 'delivery'" autocomplete="address-line1"></div>
                    <div><label class="label" for="line2">{{ __('shop.checkout.address2') }}</label><input id="line2" name="line2" x-model="form.line2" class="input" autocomplete="address-line2"></div>
                    <div><label class="label" for="city">{{ __('shop.checkout.city') }}</label><input id="city" name="city" x-model="form.city" class="input" :required="form.fulfilment === 'delivery'" autocomplete="address-level2"></div>
                    @auth<label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="save_address" value="1" class="accent-gold-600"> {{ __('shop.checkout.save_address') }}</label>@endauth
                </div>
                @endif
                <div x-show="form.fulfilment === 'pickup'" x-cloak class="mt-4">
                    <label class="label" for="pickup_branch_id">{{ __('shop.checkout.branch') }}</label>
                    <select id="pickup_branch_id" name="pickup_branch_id" x-model="form.pickup_branch_id" class="input">
                        @foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->localized('name') }} — {{ $b->address }}</option>@endforeach
                    </select>
                    @foreach ($branches as $b)
                        <div x-show="form.pickup_branch_id == {{ $b->id }}" class="mt-3">@include('partials.store-map', ['branch' => $b, 'height' => 'h-56'])</div>
                    @endforeach
                </div>
            </section>

            @if ($phoneVerification)
                <section class="card p-4 sm:p-6">
                    <div class="flex items-start gap-3">
                        <svg class="mt-1 h-6 w-6 shrink-0 text-gold-700" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/></svg>
                        <div><h2 class="font-display text-2xl">{{ __('shop.verify.title') }}</h2><p class="mt-1 text-sm text-muted">{{ __('shop.verify.checkout_intro') }}</p></div>
                    </div>
                    <div class="mt-4">@include('partials.phone-verify', ['phoneModel' => 'form.phone', 'verifiedPhones' => $verifiedPhones])</div>
                    @error('phone_verification')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                </section>
            @endif

            @php($taxFreeOn = \App\Support\TaxFree::enabled())
            @if ($taxFreeOn)
                <section class="card p-4 sm:p-6">
                    <input type="hidden" name="tax_free" :value="form.tax_free ? 1 : 0">
                    <div class="flex items-start gap-3">
                        <svg class="mt-1 h-6 w-6 shrink-0 text-gold-700" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 100-18 9 9 0 000 18z"/><path d="M3.6 9h16.8M3.6 15h16.8M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/></svg>
                        <div><h2 class="font-display text-2xl">{{ __('shop.tax_free.title') }}</h2><p class="mt-1 text-sm text-muted">{{ __('shop.tax_free.question') }}</p></div>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label class="card flex cursor-pointer items-center gap-3 p-4" :class="!form.tax_free && 'border-gold-500 ring-1 ring-gold-500'"><input type="radio" name="tax_free_choice" :checked="!form.tax_free" @change="form.tax_free = false" class="accent-gold-600"> {{ __('shop.tax_free.no') }}</label>
                        <label class="card flex cursor-pointer items-center gap-3 p-4" :class="form.tax_free && 'border-gold-500 ring-1 ring-gold-500'"><input type="radio" name="tax_free_choice" :checked="form.tax_free" @change="form.tax_free = true" class="accent-gold-600">
                            <span><span class="font-medium">{{ __('shop.tax_free.yes') }}</span><span class="block text-xs text-muted">{{ __('shop.tax_free.yes_note') }}</span></span></label>
                    </div>
                    <div x-show="form.tax_free" x-cloak class="mt-4 rounded-xl border border-gold-400 bg-gold-300/15 p-4 text-sm">
                        <p class="font-medium">{{ __('shop.tax_free.how_title') }}</p>
                        <ul class="mt-2 list-disc space-y-1 ps-5 text-muted">
                            <li>{{ __('shop.tax_free.how_passport') }}</li>
                            <li>{{ __('shop.tax_free.how_vat') }}</li>
                            <li>{{ __('shop.tax_free.how_tag') }}</li>
                            <li>{{ __('shop.tax_free.how_min', ['min' => 'AED '.number_format(\App\Support\TaxFree::minimum())]) }}</li>
                        </ul>
                    </div>
                    @error('tax_free')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                </section>
            @endif

            @if ($idMode !== 'off' || $taxFreeOn)
                @php($savedId = auth()->user()?->hasValidSavedId() ? auth()->user() : null)
                @php($savedPassport = $savedId && $savedId->id_type === 'passport' && filled($savedId->id_country ?? null))
                <section class="card p-4 sm:p-6" x-data="{ mode: @js($idMode), savedPassport: @js($savedPassport), hasSaved: @js((bool) $savedId), pickSaved: @js((bool) $savedId && old('use_saved_id', '1') === '1'),
                        get canUseSaved() { return this.hasSaved && (! this.form.tax_free || this.savedPassport) },
                        get useSaved() { return this.pickSaved && this.canUseSaved }, set useSaved(v) { this.pickSaved = v },
                        get needed() { return this.form.tax_free || this.mode === 'all' || (this.mode === 'delivery' && this.form.fulfilment === 'delivery') } }" x-show="needed" x-cloak>
                    <div class="flex items-start gap-3">
                        <svg class="mt-1 h-6 w-6 shrink-0 text-gold-700" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 12l2 2 4-4m5.6-4.02A11.96 11.96 0 0112 2.94a11.96 11.96 0 01-8.6 3.04A12 12 0 003 9c0 5.6 3.82 10.3 9 11.62 5.18-1.33 9-6.03 9-11.62 0-1.04-.13-2.05-.38-3.02z"/></svg>
                        <div><h2 class="font-display text-2xl">{{ __('shop.id.title') }}</h2><p class="mt-1 text-sm text-muted">{{ __('shop.id.intro') }}</p></div>
                    </div>
                    <input type="hidden" name="use_saved_id" :value="useSaved ? 1 : 0">

                    <p x-show="form.tax_free" x-cloak class="mt-3 rounded-lg bg-sand px-3 py-2 text-sm">🛂 {{ __('shop.tax_free.passport_only') }}</p>
                    @if ($savedId)
                        <div class="mt-4 grid gap-3 sm:grid-cols-2" x-show="canUseSaved">
                            <label class="card flex cursor-pointer items-center gap-3 p-4" :class="useSaved && 'border-gold-500 ring-1 ring-gold-500'"><input type="radio" :checked="useSaved" @change="useSaved = true" class="accent-gold-600">
                                <span><span class="font-medium">{{ __('shop.id.use_saved') }}</span><span class="block text-xs text-muted">{{ \App\Support\IdDocument::masked($savedId->id_type, $savedId->id_last4) }}@if ($savedId->id_expiry) · {{ __('shop.id.expires', ['date' => $savedId->id_expiry->format('d/m/Y')]) }}@endif</span></span></label>
                            <label class="card flex cursor-pointer items-center gap-3 p-4" :class="!useSaved && 'border-gold-500 ring-1 ring-gold-500'"><input type="radio" :checked="!useSaved" @change="useSaved = false" class="accent-gold-600"> {{ __('shop.id.use_new') }}</label>
                        </div>
                    @endif

                    <div x-show="!useSaved" class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><label class="label" for="id_type">{{ __('shop.id.type') }}</label>
                            <select id="id_type" name="id_type" class="input" :required="needed && !useSaved" :disabled="!needed || useSaved" x-effect="if (form.tax_free) $el.value = 'passport'">
                                @foreach (\App\Support\IdDocument::TYPES as $t)<option value="{{ $t }}" @selected(old('id_type', 'emirates_id') === $t) @if ($t !== 'passport') :disabled="form.tax_free" @endif>{{ __('shop.id.types.'.$t) }}</option>@endforeach
                            </select>
                        </div>
                        <div x-show="form.tax_free" x-cloak><label class="label" for="id_country">{{ __('shop.tax_free.nationality') }}</label>
                            <select id="id_country" name="id_country" class="input" :required="form.tax_free && !useSaved" :disabled="!form.tax_free || useSaved">
                                <option value="">{{ __('shop.tax_free.choose_country') }}</option>
                                @foreach (\App\Support\TaxFree::countries() as $code => $country)<option value="{{ $code }}" @selected(old('id_country') === $code)>{{ $country }}</option>@endforeach
                            </select>
                            @error('id_country')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                        <div><label class="label" for="id_number">{{ __('shop.id.number') }}</label>
                            <input id="id_number" name="id_number" value="{{ old('id_number') }}" class="input" dir="ltr" placeholder="784-1990-1234567-1" autocomplete="off" :required="needed && !useSaved" :disabled="!needed || useSaved">
                            @error('id_number')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                        <div><label class="label" for="id_expiry">{{ __('shop.id.expiry') }}</label>
                            <input id="id_expiry" type="date" name="id_expiry" value="{{ old('id_expiry') }}" min="{{ now()->addDay()->toDateString() }}" class="input" :required="needed && !useSaved" :disabled="!needed || useSaved">
                            @error('id_expiry')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                        <div class="hidden sm:block" x-show="!form.tax_free"></div>
                        @php($idPhotos = \App\Http\Controllers\CheckoutController::idPhotos())
                        @if ($idPhotos !== 'hidden')
                            @include('partials.id-photo-input', ['name' => 'id_front', 'label' => __($idPhotos === 'required' ? 'shop.id.front_required' : 'shop.id.front'), 'required' => $idPhotos === 'required'])
                            @include('partials.id-photo-input', ['name' => 'id_back', 'label' => __($idPhotos === 'required' ? 'shop.id.back_required' : 'shop.id.back'), 'required' => $idPhotos === 'required'])
                        @endif
                        @auth
                            <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="save_id" value="1" @checked(old('save_id', true)) class="accent-gold-600" :disabled="!needed || useSaved"> {{ __('shop.id.save') }}</label>
                        @else
                            <p class="text-xs text-muted sm:col-span-2">{{ __('shop.id.guest_save') }}</p>
                        @endauth
                    </div>
                    <p class="mt-4 rounded-xl bg-sand px-4 py-3 text-xs text-muted">🔒 {{ __('shop.id.privacy') }}</p>
                </section>
            @endif

            <section class="card p-4 sm:p-6">
                <h2 class="font-display text-2xl">{{ __('shop.checkout.payment') }}</h2>
                <div class="mt-4 space-y-3">
                    <span x-init="if (form.payment_method === 'applepay' && !(window.ApplePaySession && ApplePaySession.canMakePayments())) form.payment_method = 'card'"></span>
                    <template x-for="(m, key) in q.methods" :key="key">
                        {{-- Apple Pay only appears on devices that support it (iPhone, iPad, Mac with Safari). --}}
                        <label x-show="key !== 'applepay' || (window.ApplePaySession && ApplePaySession.canMakePayments())" class="card flex cursor-pointer items-center gap-3 p-4" :class="form.payment_method === key && 'border-gold-500 ring-1 ring-gold-500'">
                            <input type="radio" name="payment_method" :value="key" x-model="form.payment_method" class="accent-gold-600">
                            <span class="flex-1"><span class="font-medium" x-text="m.label"></span><span class="block text-xs text-muted" x-text="m.note" x-show="m.note"></span></span>
                            <span x-show="key === 'applepay'" class="inline-flex h-8 items-center rounded-md bg-black px-2.5 text-white" aria-hidden="true">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M16.37 12.6c-.03-2.7 2.2-4 2.3-4.06-1.25-1.83-3.2-2.08-3.9-2.11-1.66-.17-3.24.98-4.08.98-.85 0-2.15-.95-3.53-.93A5.23 5.23 0 002.73 9.2c-1.9 3.28-.48 8.15 1.36 10.82.9 1.3 1.98 2.77 3.39 2.71 1.36-.05 1.87-.88 3.51-.88s2.1.88 3.53.85c1.46-.03 2.38-1.33 3.27-2.64a11.6 11.6 0 001.48-3.04 4.73 4.73 0 01-2.9-4.42zM13.68 4.66A4.68 4.68 0 0014.8 1.3a4.8 4.8 0 00-3.12 1.6 4.47 4.47 0 00-1.15 3.26 3.97 3.97 0 003.15-1.5z"/></svg>
                                <span class="ms-0.5 text-sm font-semibold">Pay</span>
                            </span>
                            <span x-show="key === 'googlepay'" class="inline-flex h-8 items-center gap-1 rounded-md border border-line bg-white px-2.5 text-[#3c4043]" aria-hidden="true">
                                <svg class="h-4 w-4" viewBox="0 0 48 48"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>
                                <span class="text-sm font-medium">Pay</span>
                            </span>
                            <span x-show="key === 'card'" class="text-xs font-semibold tracking-wide text-muted" aria-hidden="true">VISA · MC</span>
                        </label>
                    </template>
                </div>
            </section>

            <section class="card p-4 sm:p-6">
                <label class="label" for="notes">{{ __('shop.checkout.notes') }}</label>
                <textarea id="notes" name="notes" x-model="form.notes" rows="2" class="input"></textarea>
            </section>
        </div>

        <aside class="h-fit space-y-4 lg:sticky lg:top-24">
            <div class="card p-4 sm:p-6" :class="loading && 'opacity-60'">
                <h2 class="font-display text-2xl">{{ __('shop.checkout.summary') }}</h2>
                <ul class="mt-4 space-y-3 text-sm">
                    <template x-for="l in q.lines"><li class="flex justify-between gap-3"><span><span x-text="l.name"></span> <span class="text-muted" x-text="'× ' + l.qty"></span><span class="block text-xs text-gold-700" x-show="l.discount" x-text="'− ' + l.discount"></span></span><span class="price" x-text="l.total"></span></li></template>
                </ul>

                <div class="mt-5 space-y-2 border-t border-line pt-4">
                    <div class="flex gap-2"><input name="coupon" x-model.lazy="form.coupon" placeholder="{{ __('shop.checkout.coupon') }}" class="input uppercase" aria-label="{{ __('shop.checkout.coupon') }}"><button type="button" class="btn-outline px-4" @click="requote()">{{ __('shop.checkout.apply') }}</button></div>
                    <div class="flex gap-2"><input name="gift_card" x-model.lazy="form.gift_card" placeholder="{{ __('shop.checkout.gift_card') }}" class="input uppercase" aria-label="{{ __('shop.checkout.gift_card') }}"><button type="button" class="btn-outline px-4" @click="requote()">{{ __('shop.checkout.apply') }}</button></div>
                    @if ($loyaltyBalance > 0)
                        <div><label class="text-xs text-muted" for="loyalty_points">{{ __('shop.loyalty.use', ['points' => $loyaltyBalance]) }}</label><input id="loyalty_points" type="number" min="0" max="{{ $loyaltyBalance }}" name="loyalty_points" x-model.number.lazy="form.loyalty_points" class="input"></div>
                    @endif
                    <template x-for="msg in q.messages"><p class="text-xs text-red-700" x-text="msg"></p></template>
                    <template x-for="c in q.coupons"><p class="text-xs text-green-700" x-text="'✓ ' + c"></p></template>
                </div>

                <dl class="mt-5 space-y-2 border-t border-line pt-4 text-sm price">
                    <div class="flex justify-between"><dt>{{ __('shop.checkout.subtotal_ex_vat') }}</dt><dd x-text="q.items_subtotal"></dd></div>
                    <div class="flex justify-between text-gold-700" x-show="q.discount"><dt>{{ __('shop.checkout.discount') }}</dt><dd x-text="'− ' + q.discount"></dd></div>
                    <div class="flex justify-between text-xs text-muted" x-show="q.loyalty"><dt>{{ __('shop.loyalty.applied') }}</dt><dd x-text="q.loyalty && (q.loyalty.points + ' pts')"></dd></div>
                    <div class="flex justify-between"><dt>{{ __('shop.checkout.shipping') }}</dt><dd x-text="q.shipping"></dd></div>
                    <div class="flex justify-between" x-show="q.payment_fee"><dt>{{ __('shop.checkout.payment_fee') }}</dt><dd x-text="q.payment_fee"></dd></div>
                    <div class="flex justify-between border-t border-line pt-2"><dt>{{ __('shop.checkout.taxable') }}</dt><dd x-text="q.taxable_all"></dd></div>
                    <div class="flex justify-between"><dt>{{ __('shop.checkout.vat') }}</dt><dd x-text="q.vat"></dd></div>
                    <div class="flex justify-between border-t border-line pt-3 text-base font-semibold"><dt>{{ __('shop.checkout.total') }}</dt><dd class="text-price" x-text="q.grand_total"></dd></div>
                    <div class="flex justify-between text-gold-700" x-show="q.gift_card"><dt>{{ __('shop.checkout.gift_card') }}</dt><dd x-text="'− ' + q.gift_card"></dd></div>
                    <div class="flex justify-between font-semibold" x-show="q.gift_card"><dt>{{ __('shop.checkout.amount_due') }}</dt><dd x-text="q.amount_due"></dd></div>
                </dl>
                <p class="mt-3 text-xs text-muted">{{ __('shop.checkout.vat_note') }}</p>
                @if (\App\Shop\OrderService::paymentWindow() > 0)
                    <p class="mt-2 text-xs text-gold-700">⏱ {{ __('shop.pay.hold_note', ['minutes' => \App\Shop\OrderService::paymentWindow()]) }}</p>
                @endif
                @if (\App\Support\Settings::bool('require_terms_acceptance'))
                    <label class="mt-4 flex items-start gap-2.5 rounded-xl border border-line bg-sand/50 p-3 text-xs leading-relaxed">
                        <input type="checkbox" name="accept_terms" value="1" required class="mt-0.5 h-4 w-4 shrink-0 accent-gold-600" @checked(old('accept_terms'))>
                        <span>{!! __('shop.policy.agree', [
                            'terms' => '<a href="'.route('policy.show', 'terms-and-conditions').'" target="_blank" class="underline">'.e(\App\Support\Policies::title('terms')).'</a>',
                            'refund' => '<a href="'.route('policy.show', 'refund-policy').'" target="_blank" class="underline">'.e(\App\Support\Policies::title('refund')).'</a>',
                        ]) !!}</span>
                    </label>
                    @error('accept_terms')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                @endif
                <button class="btn-gold mt-5 hidden h-11 w-full lg:flex" @disabled($blocked) :disabled="loading">{{ __('shop.checkout.place') }}</button>
            </div>
        </aside>

        {{-- Phones: total + Place order always visible. --}}
        <div class="pb-safe fixed inset-x-0 bottom-0 z-40 border-t border-line bg-ivory/95 backdrop-blur lg:hidden">
            <div class="container-x flex items-center gap-3 py-3">
                <div class="min-w-0 flex-1">
                    <p class="text-[0.6875rem] text-muted">{{ __('shop.checkout.total') }} · {{ __('shop.product.incl_vat', ['vat' => 5]) }}</p>
                    <p class="price text-price truncate text-lg font-bold" x-text="q.gift_card ? q.amount_due : q.grand_total"></p>
                </div>
                <button class="btn-gold shrink-0 px-6" @disabled($blocked) :disabled="loading">{{ __('shop.checkout.place') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>document.addEventListener('DOMContentLoaded', () => shopTrack('begin_checkout', @js(['value' => $quote->grandTotal / 100, 'items' => collect($quote->lines)->map(fn ($l) => ['item_id' => $l->product->sku, 'item_name' => $l->product->name, 'quantity' => $l->qty])->all()])));</script>
@endpush
