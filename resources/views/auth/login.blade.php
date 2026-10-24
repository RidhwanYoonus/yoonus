@extends('layouts.app')
@section('content')
<div class="container-x max-w-md py-16">
    <h1 class="font-display text-4xl">{{ __('shop.auth.login') }}</h1>
    <form method="POST" action="{{ route('login') }}" class="card mt-6 space-y-4 p-6">
        @include('partials.google-button')
        <a href="{{ route('login.code') }}" class="btn-outline flex h-11 w-full items-center justify-center gap-2">📱 {{ __('shop.code_login.button') }}</a>
        @csrf
        <div><label class="label" for="email">{{ __('shop.checkout.email') }}</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus class="input" autocomplete="email"></div>
        <div><label class="label" for="password">{{ __('shop.auth.password') }}</label><input id="password" type="password" name="password" required class="input" autocomplete="current-password"></div>
        <div class="flex items-center justify-between gap-3 text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="remember" value="1" checked class="accent-gold-600"> {{ __('shop.auth.remember') }}</label>
            <a href="{{ route('password.request') }}" class="text-gold-700 underline">{{ __('shop.reset.forgot') }}</a>
        </div>
        <button class="btn-dark w-full">{{ __('shop.auth.login') }}</button>
        <p class="text-center text-sm text-muted">{{ __('shop.auth.no_account') }} <a href="{{ route('register') }}" class="text-gold-700 underline">{{ __('shop.auth.register') }}</a></p>
    </form>
</div>
@endsection
