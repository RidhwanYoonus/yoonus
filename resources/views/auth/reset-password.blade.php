@extends('layouts.app')
@section('content')
<div class="container-x max-w-md py-16">
    <h1 class="font-display text-4xl">{{ __('shop.reset.choose') }}</h1>
    <form method="POST" action="{{ route('password.update') }}" class="card mt-6 space-y-4 p-6" data-live-validate x-data="{ show: false }">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div><label class="label" for="email">{{ __('shop.checkout.email') }}</label><input id="email" type="email" name="email" value="{{ old('email', $email) }}" required class="input" autocomplete="email"></div>
        <div><label class="label" for="password">{{ __('shop.auth.password') }}</label><input id="password" :type="show ? 'text' : 'password'" name="password" required minlength="8" class="input" autocomplete="new-password" dir="ltr">
            <p class="mt-1 text-xs text-muted">{{ __('shop.auth.pw_len') }} · {{ __('shop.auth.pw_case') }} · {{ __('shop.auth.pw_num') }}</p></div>
        <div><label class="label" for="password_confirmation">{{ __('shop.auth.password_confirm') }}</label><input id="password_confirmation" :type="show ? 'text' : 'password'" name="password_confirmation" required class="input" autocomplete="new-password" dir="ltr"></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="show" class="accent-gold-600"> {{ __('shop.auth.show_password') }}</label>
        <button class="btn-dark w-full">{{ __('shop.reset.save') }}</button>
    </form>
</div>
@endsection
