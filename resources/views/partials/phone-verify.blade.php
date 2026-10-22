{{--
    Mobile verification widget. $phoneModel: Alpine expression holding the number (e.g. "form.phone"),
    or omit to show its own input. Posts to /verify-phone/send and /verify-phone/check.
--}}
@php($verified = $verifiedPhones ?? [])
<div x-data="phoneVerify(@js(['send' => route('phone.send'), 'check' => route('phone.check'), 'csrf' => csrf_token(), 'verified' => $verified, 'own' => ! isset($phoneModel)]))"
     @if (isset($phoneModel)) x-effect="phone = {{ $phoneModel }}" @endif>
    @unless (isset($phoneModel))
        <label class="label" for="vp_phone">{{ __('shop.checkout.phone') }}</label>
        <input id="vp_phone" x-model="phone" class="input mb-3" dir="ltr" placeholder="+971 50 000 0000" value="{{ $initialPhone ?? '' }}" x-init="phone = $el.value" autocomplete="tel">
    @endunless
    <template x-if="isVerified()">
        <p class="flex items-center gap-2 rounded-md bg-green-700/10 px-3 py-2 text-sm font-medium text-green-700">✓ <span x-text="'{{ __('shop.verify.verified') }} ' + phone"></span></p>
    </template>
    <div x-show="!isVerified()" class="space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="btn-outline" @click="send()" :disabled="busy || !phone">
                <svg class="h-4 w-4 text-[#25D366]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 00-8.6 15.1L2 22l5-1.3A10 10 0 1012 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.6-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.9s.7-2 1-2.3c.3-.3.6-.3.8-.3h.6c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.3.5-.4.4c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.4 2.4 1.5.3.1.5.1.6-.1l.9-1c.2-.3.4-.2.6-.1l1.9.9c.3.1.5.2.5.3.1.2.1.7-.1 1.3z"/></svg>
                <span x-text="sent ? '{{ __('shop.verify.resend') }}' : '{{ __('shop.verify.send') }}'"></span>
            </button>
            @if (\App\Messaging\Sms::configured())
                {{-- Customers without mobile data can get the code as a normal text message. --}}
                <button type="button" class="btn-outline" @click="send('sms')" :disabled="busy || !phone" title="{{ __('shop.verify.no_data') }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.6 12h.01M12 12h.01M15.4 12h.01M21 12c0 4.4-4 8-9 8a9.9 9.9 0 01-4.3-1L3 20l1.4-3.7A7.6 7.6 0 013 12c0-4.4 4-8 9-8s9 3.6 9 8z"/></svg>
                    {{ __('shop.verify.send_sms') }}
                </button>
            @endif
            <template x-if="sent">
                <span class="flex items-center gap-2">
                    <input x-model="code" inputmode="numeric" maxlength="6" placeholder="••••••" aria-label="{{ __('shop.verify.code') }}" class="input w-32 text-center tracking-[0.3em]" @keydown.enter.prevent="check()">
                    <button type="button" class="btn-dark" @click="check()" :disabled="busy || code.length < 6">{{ __('shop.verify.confirm') }}</button>
                </span>
            </template>
        </div>
        <p x-show="message" x-text="message" class="text-xs" :class="error ? 'text-red-700' : 'text-muted'"></p>
        <p x-show="testCode" class="rounded-md bg-gold-300/20 px-3 py-2 text-xs font-medium text-gold-700">TEST MODE — WhatsApp is not set up yet, so here is the code: <b x-text="testCode" class="tracking-widest"></b></p>
    </div>
</div>
