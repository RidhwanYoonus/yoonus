@extends('layouts.app')
@section('content')
<div class="container-x max-w-md py-16">
    <h1 class="font-display text-4xl">{{ __('shop.code_login.title') }}</h1>
    <p class="mt-2 text-sm text-muted">{{ __('shop.code_login.intro') }}</p>
    @if (! $target)
        <form method="POST" action="{{ route('login.code.send') }}" class="card mt-6 space-y-4 p-6" data-live-validate x-data="{ login: @js(old('login', '')) }">
            @csrf
            <div><label class="label" for="login">{{ __('shop.code_login.login') }}</label>
                <input id="login" name="login" x-model="login" required class="input" dir="ltr" autocomplete="username" placeholder="050 123 4567  /  name@gmail.com" autofocus>
                @error('login')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
            @if (in_array('sms', $channels, true))
                <div class="flex gap-4 text-sm" x-show="login && ! login.includes('@')">
                    <label class="flex items-center gap-2"><input type="radio" name="channel" value="whatsapp" checked class="accent-gold-600"> WhatsApp</label>
                    <label class="flex items-center gap-2"><input type="radio" name="channel" value="sms" class="accent-gold-600"> SMS</label>
                </div>
            @endif
            <button class="btn-dark w-full">{{ __('shop.code_login.send') }}</button>
        </form>
    @else
        <form method="POST" action="{{ route('login.code.verify') }}" class="card mt-6 space-y-4 p-6" x-data="{ code: '' }">
            @csrf
            <p class="text-sm">{{ __('shop.code_login.enter', ['to' => $target[1]]) }}</p>
            <input name="code" x-model="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus placeholder="••••••"
                   class="input text-center text-2xl tracking-[0.4em]" dir="ltr" aria-label="{{ __('shop.verify.code') }}"
                   @input="code = code.replace(/\D/g, ''); if (code.length === 6) $nextTick(() => $el.form.requestSubmit())">
            @error('code')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
            @if ($testCode)<p class="rounded-md bg-gold-300/20 px-3 py-2 text-xs font-medium text-gold-700">TEST MODE — messages are not set up yet, so here is the code: <b class="tracking-widest">{{ $testCode }}</b></p>@endif
            <button class="btn-dark w-full">{{ __('shop.code_login.confirm') }}</button>
        </form>
        <form method="POST" action="{{ route('login.code.reset') }}" class="mt-3 text-center text-sm">@csrf<button class="text-gold-700 underline">{{ __('shop.code_login.other') }}</button></form>
    @endif
    <p class="mt-6 text-center text-sm text-muted"><a href="{{ route('login') }}" class="underline">{{ __('shop.code_login.with_password') }}</a></p>
</div>
@endsection
