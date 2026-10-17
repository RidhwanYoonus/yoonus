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
        'buyer_trn' => old('buyer_trn', $user?->buyer_trn), 'fulfilment' => old('fulfilment', 'delivery'),
        'zone_id' => (int) old('zone_id', $quote->zone?->id ?? $zones->first()?->id), 'line1' => old('line1', $addresses->first()?->line1), 'line2' => old('line2', $addresses->first()?->line2), 'city' => old('city', $addresses->first()?->city),
        'pickup_branch_id' => old('pickup_branch_id', $branches->first()?->id), 'coupon' => old('coupon', ''), 'gift_card' => old('gift_card', ''),
        'loyalty_points' => (int) old('loyalty_points', 0), 'payment_method' => old('payment_method', array_key_first($methods)), 'notes' => old('notes', ''),
    ];
@endphp

@section('content')
<div class="container-x py-6 sm:py-10" x-data="checkout(@js(['form' => $form, 'quote' => $initial, 'url' => route('checkout.quote'), 'csrf' => csrf_token()]))">
    <h1 class="font-display text-3xl sm:text-4xl">{{ __('shop.checkout.title') }}</h1>

    @if ($blocked)
        <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ __('shop.checkout.rates_stale') }}</div>
    @endif
    @if (session('price_updated') || $changes)
        <div class="mt-6 rounded-xl border border-gold-400 bg-gold-300/20 p-4 text-sm" role="alert">
            <p class="font-medium">{{ session('price_updated') ?? __('shop.cart.prices_updated') }}</p>
            @foreach ($changes as $c)<p class="mt-1">{{ $c['product']->localized('name') }}: <s>{{ $f($c['old']) }}</s> → <strong>{{ $f($c['new']) }}</strong></p>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('checkout.place') }}" class="mt-8 grid gap-10 lg:grid-cols-[1fr_400px]">
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
                    <label class="card flex cursor-pointer items-center gap-3 p-4" :class="form.fulfilment === 'delivery' && 'border-gold-500 ring-1 ring-gold-500'"><input type="radio" name="fulfilment" value="delivery" x-model="form.fulfilment" class="accent-gold-600"> {{ __('shop.checkout.home_delivery') }}</label>
                    @if ($branches->isNotEmpty())
                        <label class="card flex cursor-pointer items-center gap-3 p-4" :class="form.fulfilment === 'pickup' && 'border-gold-500 ring-1 ring-gold-500'"><input type="radio" name="fulfilment" value="pickup" x-model="form.fulfilment" class="accent-gold-600"> {{ __('shop.checkout.store_pickup') }}</label>
                    @endif
                </div>
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
                <div x-show="form.fulfilment === 'pickup'" x-cloak class="mt-4">
                    <label class="label" for="pickup_branch_id">{{ __('shop.checkout.branch') }}</label>
                    <select id="pickup_branch_id" name="pickup_branch_id" x-model="form.pickup_branch_id" class="input">
                        @foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->localized('name') }} — {{ $b->address }}</option>@endforeach
                    </select>
                </div>
            </section>

            <section class="card p-4 sm:p-6">
                <h2 class="font-display text-2xl">{{ __('shop.checkout.payment') }}</h2>
                <div class="mt-4 space-y-3">
                    <template x-for="(m, key) in q.methods" :key="key">
                        <label class="card flex cursor-pointer items-start gap-3 p-4" :class="form.payment_method === key && 'border-gold-500 ring-1 ring-gold-500'">
                            <input type="radio" name="payment_method" :value="key" x-model="form.payment_method" class="mt-1 accent-gold-600">
                            <span><span class="font-medium" x-text="m.label"></span><span class="block text-xs text-muted" x-text="m.note" x-show="m.note"></span></span>
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
                    <div class="flex justify-between border-t border-line pt-3 text-base font-semibold"><dt>{{ __('shop.checkout.total') }}</dt><dd class="text-gold-700" x-text="q.grand_total"></dd></div>
                    <div class="flex justify-between text-gold-700" x-show="q.gift_card"><dt>{{ __('shop.checkout.gift_card') }}</dt><dd x-text="'− ' + q.gift_card"></dd></div>
                    <div class="flex justify-between font-semibold" x-show="q.gift_card"><dt>{{ __('shop.checkout.amount_due') }}</dt><dd x-text="q.amount_due"></dd></div>
                </dl>
                <p class="mt-3 text-xs text-muted">{{ __('shop.checkout.vat_note') }}</p>
                <button class="btn-dark mt-5 hidden w-full lg:flex" @disabled($blocked) :disabled="loading">{{ __('shop.checkout.place') }}</button>
            </div>
        </aside>

        {{-- Phones: total + Place order always visible. --}}
        <div class="pb-safe fixed inset-x-0 bottom-0 z-40 border-t border-line bg-ivory/95 backdrop-blur lg:hidden">
            <div class="container-x flex items-center gap-3 py-3">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] text-muted">{{ __('shop.checkout.total') }} · {{ __('shop.product.incl_vat', ['vat' => 5]) }}</p>
                    <p class="price truncate text-lg font-semibold text-gold-700" x-text="q.gift_card ? q.amount_due : q.grand_total"></p>
                </div>
                <button class="btn-dark shrink-0 px-6" @disabled($blocked) :disabled="loading">{{ __('shop.checkout.place') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>document.addEventListener('DOMContentLoaded', () => shopTrack('begin_checkout', @js(['value' => $quote->grandTotal / 100, 'items' => collect($quote->lines)->map(fn ($l) => ['item_id' => $l->product->sku, 'item_name' => $l->product->name, 'quantity' => $l->qty])->all()])));</script>
@endpush
