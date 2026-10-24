@extends('layouts.app')
@section('content')
<div class="container-x max-w-md py-16">
    <h1 class="font-display text-4xl">{{ __('shop.auth.register') }}</h1>
    @php($refAffiliate = $ref ? rescue(fn () => \App\Models\Affiliate::where('code', strtoupper($ref))->where('is_active', true)->first(), null, false) : null)
    @if ($refAffiliate)
        <p class="mt-2 rounded-xl bg-gold-300/20 px-4 py-3 text-sm">{{ __('shop.affiliate.signup_invited', ['name' => $refAffiliate->name()]) }}</p>
    @elseif ($ref)<p class="mt-2 rounded-xl bg-gold-300/20 px-4 py-3 text-sm">{{ __('shop.referral.invited', ['amount' => \App\Support\Settings::get('referral_reward')]) }}</p>@endif
    <script>
        // Live checks of the sign-up form (email already used, password rules), so it is not sent back with an error.
        window.signupCheck = async (data) => {
            try {
                const r = await fetch(@js(route('register.check')), { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content }, body: JSON.stringify(data) });
                return r.ok ? await r.json() : null;
            } catch (e) { return null; }
        };
    </script>
    <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data" class="card mt-6 space-y-4 p-6" data-live-validate>
        @include('partials.google-button', ['ref' => $ref])
        @csrf
        <div><label class="label" for="name">{{ __('shop.checkout.name') }}</label><input id="name" name="name" value="{{ old('name') }}" required class="input" autocomplete="name"></div>
        <div x-data="{ msg: '', async check(el) {
                if (! el.value || ! el.checkValidity() && ! this.msg) { return; }
                const j = await window.signupCheck({ email: el.value }); this.msg = j?.errors?.email || ''; el.setCustomValidity(this.msg);
            } }"><label class="label" for="email">{{ __('shop.checkout.email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required class="input" autocomplete="email" :class="msg && 'border-red-400'" @blur="check($el)" @input="if (msg) { msg = ''; $el.setCustomValidity('') }">
            <p x-show="msg" x-cloak x-text="msg" class="mt-1 text-xs text-red-700"></p>@error('email')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
        <div x-data="{ suPhone: @js(old('phone', '')) }"><label class="label" for="phone">{{ __('shop.checkout.phone') }}</label><input id="phone" name="phone" x-model="suPhone" class="input" dir="ltr" autocomplete="tel">
            @if (\App\Shop\PhoneVerification::enabled())
                <p class="mt-1 text-xs text-muted">{{ __('shop.signup.verify_optional') }}</p>
                <div class="mt-2">@include('partials.phone-verify', ['phoneModel' => 'suPhone', 'verifiedPhones' => session('verified_phones', []), 'emailInput' => '#email'])</div>
            @else
                <p class="mt-1 text-xs text-muted">{{ __('shop.verify.after_signup') }}</p>
            @endif
        </div>
        {{-- Password: eye button to see it, green ticks while typing, the "same password" check when leaving the second box. --}}
        <div x-data="{ pw: '', pw2: '', show: false, left: false, left2: false, server: '', checking: false,
                get len() { return this.pw.length >= 8 }, get cas() { return /[a-z]/.test(this.pw) && /[A-Z]/.test(this.pw) }, get num() { return /[0-9]/.test(this.pw) },
                get strong() { return this.len && this.cas && this.num }, get match() { return this.pw2 !== '' && this.pw === this.pw2 },
                async check() {
                    this.left = true;
                    if (! this.strong) { return; }
                    this.checking = true; const j = await window.signupCheck({ password: this.pw }); this.checking = false;
                    this.server = j?.errors?.password || '';
                } }"
             x-effect="$refs.pw.setCustomValidity(pw && (! strong || server) ? (server || @js(__('shop.auth.pw_len'))) : ''); $refs.pw2.setCustomValidity(pw2 && ! match ? @js(__('shop.auth.pw_no_match')) : '')"
             class="space-y-3">
            <div><label class="label" for="password">{{ __('shop.auth.password') }}</label>
                <div class="relative">
                    <input id="password" x-ref="pw" :type="show ? 'text' : 'password'" name="password" required class="input pe-12" autocomplete="new-password" dir="ltr"
                           x-model="pw" @input="server = ''" @blur="check()" :class="left && pw && (! strong || server) ? 'border-red-400' : (strong && ! server ? 'border-green-500' : '')">
                    <button type="button" @click="show = ! show" class="absolute inset-y-0 end-0 flex w-11 items-center justify-center text-muted hover:text-ink" :aria-label="show ? @js(__('shop.auth.hide_password')) : @js(__('shop.auth.show_password'))" :aria-pressed="show">
                        <svg x-show="! show" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7S2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg x-show="show" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 5.1A9.7 9.7 0 0 1 12 5c6 0 9.5 7 9.5 7a16 16 0 0 1-3.1 3.9M6.2 6.3C3.8 8 2.5 12 2.5 12S6 19 12 19c1.8 0 3.3-.5 4.6-1.2M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                    </button>
                </div>
                <ul class="mt-2 grid gap-1 text-xs sm:grid-cols-3">
                    <li :class="len ? 'text-green-700' : (left && pw ? 'text-red-700' : 'text-muted')"><span x-text="len ? '✓' : '○'">○</span> {{ __('shop.auth.pw_len') }}</li>
                    <li :class="cas ? 'text-green-700' : (left && pw ? 'text-red-700' : 'text-muted')"><span x-text="cas ? '✓' : '○'">○</span> {{ __('shop.auth.pw_case') }}</li>
                    <li :class="num ? 'text-green-700' : (left && pw ? 'text-red-700' : 'text-muted')"><span x-text="num ? '✓' : '○'">○</span> {{ __('shop.auth.pw_num') }}</li>
                </ul>
                <p x-show="checking" x-cloak class="mt-1 text-xs text-muted">{{ __('shop.auth.pw_checking') }}</p>
                <p x-show="server" x-cloak x-text="server" class="mt-1 text-xs text-red-700"></p>
                @error('password')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="password_confirmation">{{ __('shop.auth.password_confirm') }}</label>
                <div class="relative">
                    <input id="password_confirmation" x-ref="pw2" :type="show ? 'text' : 'password'" name="password_confirmation" required class="input pe-10" autocomplete="new-password" dir="ltr"
                           x-model="pw2" @blur="left2 = true" :class="match ? 'border-green-500' : (left2 && pw2 ? 'border-red-400' : '')">
                    <span x-show="match" x-cloak class="absolute inset-y-0 end-0 flex w-10 items-center justify-center text-lg text-green-600" aria-hidden="true">✓</span>
                </div>
                <p x-show="match" x-cloak class="mt-1 text-xs text-green-700">✓ {{ __('shop.auth.pw_match') }}</p>
                <p x-show="left2 && pw2 && ! match" x-cloak class="mt-1 text-xs text-red-700">{{ __('shop.auth.pw_no_match') }}</p></div>
        </div>
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
                @if ($idPhotos !== 'hidden')
                    <p class="text-xs text-muted">{{ __('shop.signup.photo_first') }}</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @include('partials.id-photo-input', ['name' => 'id_front', 'label' => __('shop.id.front'), 'required' => false])
                        @include('partials.id-photo-input', ['name' => 'id_back', 'label' => __('shop.id.back'), 'required' => false])
                    </div>
                @endif
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
                <p class="rounded-lg bg-sand px-3 py-2 text-xs text-muted">🔒 {{ __('shop.id.privacy') }}</p>
            </div>
            <p class="mt-3 text-xs text-gold-700">{{ __('shop.signup.later_required') }}</p>
        </fieldset>
        <button class="btn-dark w-full">{{ __('shop.auth.register') }}</button>
    </form>
</div>
@endsection
