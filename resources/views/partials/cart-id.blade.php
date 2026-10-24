{{-- Bag page: add the ID now (needed for the order), so checkout is one tap. Only when the shop asks for an ID. --}}
@php($u = auth()->user())
@php($idMode = (string) \App\Support\Settings::get('id_verification', 'delivery'))
@if ($idMode !== 'off' || \App\Support\TaxFree::enabled())
    @php($idPhotos = \App\Http\Controllers\CheckoutController::idPhotos())
    <section id="cart-id" class="card mt-8 scroll-mt-28 p-4 sm:p-6">
        <h2 class="font-display text-2xl">🪪 {{ __('shop.cart_id.title') }}</h2>
        <p class="mt-1 text-sm text-muted">{{ __('shop.cart_id.intro') }}</p>
        @if (session('status') && session('id_saved_here'))<p class="mt-3 rounded-xl bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</p>@endif

        @guest
            <p class="mt-4 text-sm">{{ __('shop.cart_id.guest') }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('login') }}" class="btn-dark px-5 py-2 text-sm">{{ __('shop.auth.login') }}</a>
                <a href="{{ route('register') }}" class="btn-outline px-5 py-2 text-sm">{{ __('shop.auth.register') }}</a>
            </div>
        @else
            @if ($u->hasValidSavedId())
                <p class="mt-4 rounded-xl bg-green-50 px-4 py-3 text-sm text-green-800">✓ {{ __('shop.cart_id.saved', ['id' => \App\Support\IdDocument::masked($u->id_type, $u->id_last4)]) }}
                    <a href="{{ route('account') }}#id" class="ms-2 underline">{{ __('shop.cart_id.change') }}</a></p>
            @else
                <form method="POST" action="{{ route('account.id.store') }}" enctype="multipart/form-data" class="mt-4" data-live-validate
                      x-data="{ tourist: @js((bool) old('is_tourist', $u->is_tourist)), type: @js(old('id_type', $u->is_tourist ? 'passport' : 'emirates_id')) }" x-effect="if (tourist) type = 'passport'">
                    @csrf
                    <input type="hidden" name="back" value="cart">
                    @if (\App\Support\TaxFree::enabled())
                        <input type="hidden" name="is_tourist" :value="tourist ? 1 : 0">
                        <label class="mb-4 flex items-start gap-2 rounded-xl bg-sand px-4 py-3 text-sm"><input type="checkbox" x-model="tourist" class="mt-0.5 accent-gold-600">
                            <span><span class="font-medium">{{ __('shop.signup.tourist_yes') }}</span><span class="block text-xs text-muted">{{ __('shop.tax_free.how_passport') }}</span></span></label>
                    @endif
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label class="label" for="cid_type">{{ __('shop.id.type') }}</label>
                            <select id="cid_type" name="id_type" class="input" x-model="type" required>
                                @foreach (\App\Support\IdDocument::TYPES as $t)<option value="{{ $t }}" @if ($t !== 'passport') :disabled="tourist" @endif>{{ __('shop.id.types.'.$t) }}</option>@endforeach
                            </select>@error('id_type')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                        <div><label class="label" for="cid_number">{{ __('shop.id.number') }}</label>
                            <input id="cid_number" name="id_number" value="{{ old('id_number') }}" class="input" dir="ltr" required autocomplete="off" :placeholder="type === 'emirates_id' ? '784-1990-1234567-1' : ''">
                            @error('id_number')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                        <div><label class="label" for="cid_expiry">{{ __('shop.id.expiry') }} <span class="text-xs font-normal text-muted">({{ __('shop.cart_id.optional') }})</span></label>
                            <input id="cid_expiry" type="date" name="id_expiry" value="{{ old('id_expiry') }}" min="{{ now()->addDay()->toDateString() }}" class="input">
                            @error('id_expiry')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                        <div x-show="type === 'passport'" x-cloak><label class="label" for="cid_country">{{ __('shop.tax_free.nationality') }}</label>
                            <select id="cid_country" name="id_country" class="input" :required="type === 'passport'" :disabled="type !== 'passport'">
                                <option value="">{{ __('shop.tax_free.choose_country') }}</option>
                                @foreach (\App\Support\TaxFree::countries() as $code => $country)<option value="{{ $code }}" @selected(old('id_country', $u->id_country ?? null) === $code)>{{ $country }}</option>@endforeach
                            </select>@error('id_country')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                        @if ($idPhotos !== 'hidden')
                            @include('partials.id-photo-input', ['name' => 'id_front', 'label' => __('shop.id.front'), 'required' => false])
                            @include('partials.id-photo-input', ['name' => 'id_back', 'label' => __('shop.id.back'), 'required' => false])
                        @endif
                    </div>
                    <p class="mt-4 rounded-xl bg-sand px-4 py-3 text-xs text-muted">🔒 {{ __('shop.id.privacy') }}</p>
                    <button class="btn-dark mt-4 px-6">{{ __('shop.id.save_id_button') }}</button>
                </form>
            @endif
        @endguest
    </section>
@endif
