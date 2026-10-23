@extends('layouts.app')
@section('content')
<div class="container-x max-w-md py-16">
    <h1 class="font-display text-4xl">{{ __('shop.auth.register') }}</h1>
    @php($refAffiliate = $ref ? rescue(fn () => \App\Models\Affiliate::where('code', strtoupper($ref))->where('is_active', true)->first(), null, false) : null)
    @if ($refAffiliate)
        <p class="mt-2 rounded-xl bg-gold-300/20 px-4 py-3 text-sm">{{ __('shop.affiliate.signup_invited', ['name' => $refAffiliate->name()]) }}</p>
    @elseif ($ref)<p class="mt-2 rounded-xl bg-gold-300/20 px-4 py-3 text-sm">{{ __('shop.referral.invited', ['amount' => \App\Support\Settings::get('referral_reward')]) }}</p>@endif
    <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data" class="card mt-6 space-y-4 p-6">
        @include('partials.google-button', ['ref' => $ref])
        @csrf
        <div><label class="label" for="name">{{ __('shop.checkout.name') }}</label><input id="name" name="name" value="{{ old('name') }}" required class="input" autocomplete="name"></div>
        <div><label class="label" for="email">{{ __('shop.checkout.email') }}</label><input id="email" type="email" name="email" value="{{ old('email') }}" required class="input" autocomplete="email"></div>
        <div x-data="{ suPhone: @js(old('phone', '')) }"><label class="label" for="phone">{{ __('shop.checkout.phone') }}</label><input id="phone" name="phone" x-model="suPhone" class="input" dir="ltr" autocomplete="tel">
            @if (\App\Shop\PhoneVerification::enabled())
                <p class="mt-1 text-xs text-muted">{{ __('shop.signup.verify_optional') }}</p>
                <div class="mt-2">@include('partials.phone-verify', ['phoneModel' => 'suPhone', 'verifiedPhones' => session('verified_phones', []), 'emailInput' => '#email'])</div>
            @else
                <p class="mt-1 text-xs text-muted">{{ __('shop.verify.after_signup') }}</p>
            @endif
        </div>
        <div><label class="label" for="password">{{ __('shop.auth.password') }}</label><input id="password" type="password" name="password" required class="input" autocomplete="new-password"></div>
        <div><label class="label" for="password_confirmation">{{ __('shop.auth.password_confirm') }}</label><input id="password_confirmation" type="password" name="password_confirmation" required class="input" autocomplete="new-password"></div>
        <div><label class="label" for="referral_code">{{ __('shop.referral.code') }}</label><p class="-mt-1 mb-1 text-xs text-muted">{{ __('shop.signup.code_hint') }}</p><input id="referral_code" name="referral_code" value="{{ old('referral_code', $ref) }}" class="input uppercase" dir="ltr"></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="marketing_opt_in" value="1" class="accent-gold-600"> {{ __('shop.auth.marketing') }}</label>

        {{-- Optional: tourist (tax-free) + ID now, so checkout is one tap later. --}}
        @php($idPhotos = \App\Http\Controllers\CheckoutController::idPhotos())
        <fieldset class="rounded-xl border border-line p-4" x-data="{ tourist: @js(old('is_tourist') === '1'), addId: @js(filled(old('id_number'))), type: @js(old('id_type', 'emirates_id')) }"
                  x-effect="if (tourist) type = 'passport'">
            <legend class="px-1 text-sm font-medium">{{ __('shop.signup.title') }}</legend>
            <p class="text-xs text-muted">{{ __('shop.signup.intro') }}</p>
            @if (\App\Support\TaxFree::enabled())
                <p class="mt-3 text-sm">{{ __('shop.signup.tourist_question') }}</p>
                <input type="hidden" name="is_tourist" :value="tourist ? 1 : 0">
                <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
                    <label class="card flex cursor-pointer items-center gap-2 p-3" :class="!tourist && 'border-gold-500 ring-1 ring-gold-500'"><input type="radio" name="tourist_choice" :checked="!tourist" @change="tourist = false" class="accent-gold-600"> {{ __('shop.signup.tourist_no') }}</label>
                    <label class="card flex cursor-pointer items-center gap-2 p-3" :class="tourist && 'border-gold-500 ring-1 ring-gold-500'"><input type="radio" name="tourist_choice" :checked="tourist" @change="tourist = true; addId = true" class="accent-gold-600"> {{ __('shop.signup.tourist_yes') }}</label>
                </div>
                <p x-show="tourist" x-cloak class="mt-2 rounded-lg bg-sand px-3 py-2 text-xs">🛂 {{ __('shop.tax_free.how_passport') }}</p>
            @endif
            <label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" x-model="addId" class="accent-gold-600"> {{ __('shop.signup.add_id') }}</label>
            <div x-show="addId" x-cloak class="mt-3 space-y-3">
                <div><label class="label" for="su_id_type">{{ __('shop.id.type') }}</label>
                    <select id="su_id_type" name="id_type" class="input" x-model="type" :disabled="!addId">
                        @foreach (\App\Support\IdDocument::TYPES as $t)<option value="{{ $t }}" @if ($t !== 'passport') :disabled="tourist" @endif>{{ __('shop.id.types.'.$t) }}</option>@endforeach
                    </select>@error('id_type')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="su_id_number">{{ __('shop.id.number') }}</label>
                    <input id="su_id_number" name="id_number" value="{{ old('id_number') }}" class="input" dir="ltr" autocomplete="off" :disabled="!addId" :placeholder="type === 'emirates_id' ? '784-1990-1234567-1' : ''">
                    @error('id_number')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="su_id_expiry">{{ __('shop.id.expiry') }}</label>
                    <input id="su_id_expiry" type="date" name="id_expiry" value="{{ old('id_expiry') }}" min="{{ now()->addDay()->toDateString() }}" class="input" :disabled="!addId">
                    @error('id_expiry')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                <div x-show="type === 'passport'" x-cloak><label class="label" for="su_id_country">{{ __('shop.tax_free.nationality') }}</label>
                    <select id="su_id_country" name="id_country" class="input" :disabled="!addId || type !== 'passport'">
                        <option value="">{{ __('shop.tax_free.choose_country') }}</option>
                        @foreach (\App\Support\TaxFree::countries() as $code => $country)<option value="{{ $code }}" @selected(old('id_country') === $code)>{{ $country }}</option>@endforeach
                    </select>@error('id_country')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                @if ($idPhotos !== 'hidden')
                    @include('partials.id-photo-input', ['name' => 'id_front', 'label' => __('shop.id.front'), 'required' => false])
                    @include('partials.id-photo-input', ['name' => 'id_back', 'label' => __('shop.id.back'), 'required' => false])
                @endif
                <p class="rounded-lg bg-sand px-3 py-2 text-xs text-muted">🔒 {{ __('shop.id.privacy') }}</p>
            </div>
            <p class="mt-3 text-xs text-gold-700">{{ __('shop.signup.later_required') }}</p>
        </fieldset>
        <button class="btn-dark w-full">{{ __('shop.auth.register') }}</button>
    </form>
</div>
@endsection
