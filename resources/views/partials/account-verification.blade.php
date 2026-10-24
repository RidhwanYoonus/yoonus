{{-- My account → Verification: saved ID (upload / replace / remove) and mobile verification status. --}}
@php($u = auth()->user())
@php($idPhotos = \App\Http\Controllers\CheckoutController::idPhotos())
<section id="id" class="mt-10 scroll-mt-28">
    <h2 class="font-display text-2xl">{{ __('shop.id.profile_title') }}</h2>
    <p class="mt-1 text-sm text-muted">{{ __('shop.id.profile_intro') }}</p>
    @if (session('status') && str_contains(url()->previous(), 'account'))<p class="mt-3 rounded-xl bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</p>@endif

    <div class="mt-4 grid gap-4 lg:grid-cols-[18rem_1fr]">
        <div class="space-y-4">
            <div class="card p-4">
                <p class="label">{{ __('shop.id.mobile') }}</p>
                @if ($u->phone)
                    <p class="font-medium" dir="ltr">{{ $u->phone }}</p>
                    @if ($u->phone_verified_at)
                        <p class="mt-1 text-sm text-green-700">✓ {{ __('shop.id.mobile_verified') }}</p>
                    @else
                        <p class="mt-1 text-sm text-muted">{{ __('shop.id.mobile_not_verified') }}</p>
                        <a href="{{ route('phone.verify') }}" class="btn-dark mt-3 px-4 py-2 text-xs">{{ __('shop.id.verify_now') }}</a>
                    @endif
                @else
                    <p class="text-sm text-muted">{{ __('shop.id.add_phone') }}</p>
                    <a href="{{ route('phone.verify') }}" class="btn-outline mt-3 px-4 py-2 text-xs">{{ __('shop.id.verify_now') }}</a>
                @endif
            </div>
            <div class="card p-4">
                <p class="label">{{ __('shop.id.id_saved_title') }}</p>
                @if ($u->id_saved_at && $u->id_number)
                    <p class="font-medium">{{ \App\Support\IdDocument::masked($u->id_type, $u->id_last4) }}</p>
                    @if ($u->id_expiry)<p class="text-sm {{ $u->id_expiry->isPast() ? 'text-red-700' : 'text-muted' }}">{{ $u->id_expiry->isPast() ? __('shop.id.expired') : __('shop.id.expires', ['date' => $u->id_expiry->format('d/m/Y')]) }}</p>@endif
                    @if ($u->id_type === 'passport' && ($u->id_country ?? null))<p class="text-sm text-muted">{{ __('shop.tax_free.nationality') }}: {{ \App\Support\TaxFree::countryName($u->id_country) }}</p>@endif
                    @php($sides = array_keys(array_filter(['front' => $u->id_front_path, 'back' => $u->id_back_path])))
                    <p class="text-xs text-muted">{{ $sides ? __('shop.id.photos_on_file', ['sides' => implode(' + ', array_map(fn ($s) => __('shop.id.'.$s.'_side'), $sides))]) : __('shop.id.no_photos') }}</p>
                    <form method="POST" action="{{ route('account.id.forget') }}" class="mt-3">@csrf @method('DELETE')<button class="btn-outline px-4 py-2 text-xs">{{ __('shop.id.remove') }}</button></form>
                @else
                    <p class="text-sm text-muted">{{ __('shop.id.none_saved') }}</p>
                @endif
            </div>
        </div>

        <form method="POST" action="{{ route('account.id.store') }}" enctype="multipart/form-data" class="card p-4 sm:p-6" data-live-validate
              x-data="{ needed: true, useSaved: false, tourist: @js((bool) old('is_tourist', $u->is_tourist)), type: @js(old('id_type', $u->id_type ?: 'emirates_id')) }" x-effect="if (tourist) type = 'passport'">
            @csrf
            <h3 class="font-display text-xl">{{ $u->id_saved_at ? __('shop.id.replace') : __('shop.id.add_id') }}</h3>
            @if (\App\Support\TaxFree::enabled())
                <input type="hidden" name="is_tourist" :value="tourist ? 1 : 0">
                <label class="mt-3 flex items-start gap-2 rounded-xl bg-sand px-4 py-3 text-sm"><input type="checkbox" x-model="tourist" class="mt-0.5 accent-gold-600">
                    <span><span class="font-medium">{{ __('shop.signup.tourist_yes') }}</span><span class="block text-xs text-muted">{{ __('shop.id.tourist_hint') }}</span></span></label>
            @endif
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><label class="label" for="acc_id_type">{{ __('shop.id.type') }}</label>
                    <select id="acc_id_type" name="id_type" class="input" x-model="type" required>
                        @foreach (\App\Support\IdDocument::TYPES as $t)<option value="{{ $t }}" @if ($t !== 'passport') :disabled="tourist" @endif>{{ __('shop.id.types.'.$t) }}</option>@endforeach
                    </select></div>
                <div><label class="label" for="acc_id_number">{{ __('shop.id.number') }}</label>
                    <input id="acc_id_number" name="id_number" value="{{ old('id_number') }}" class="input" dir="ltr" required autocomplete="off" :placeholder="type === 'emirates_id' ? '784-1990-1234567-1' : ''">
                    @error('id_number')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="acc_id_expiry">{{ __('shop.id.expiry') }}</label>
                    <input id="acc_id_expiry" type="date" name="id_expiry" value="{{ old('id_expiry', $u->id_expiry?->toDateString()) }}" min="{{ now()->addDay()->toDateString() }}" class="input">
                    @error('id_expiry')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                <div x-show="type === 'passport'" x-cloak><label class="label" for="acc_id_country">{{ __('shop.tax_free.nationality') }}</label>
                    <select id="acc_id_country" name="id_country" class="input" :required="type === 'passport'" :disabled="type !== 'passport'">
                        <option value="">{{ __('shop.tax_free.choose_country') }}</option>
                        @foreach (\App\Support\TaxFree::countries() as $code => $country)<option value="{{ $code }}" @selected(old('id_country', $u->id_country ?? null) === $code)>{{ $country }}</option>@endforeach
                    </select>
                    @error('id_country')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                @if ($idPhotos !== 'hidden')
                    @include('partials.id-photo-input', ['name' => 'id_front', 'label' => __('shop.id.front'), 'required' => false])
                    @include('partials.id-photo-input', ['name' => 'id_back', 'label' => __('shop.id.back'), 'required' => false])
                @endif
            </div>
            <p class="mt-4 rounded-xl bg-sand px-4 py-3 text-xs text-muted">🔒 {{ __('shop.id.privacy') }}</p>
            <button class="btn-dark mt-4 px-6">{{ __('shop.id.save_id_button') }}</button>
        </form>
    </div>
</section>
