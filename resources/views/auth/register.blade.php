@extends('layouts.app')
@section('content')
<div class="container-x max-w-md py-16">
    <h1 class="font-display text-4xl">{{ __('shop.auth.register') }}</h1>
    @if ($ref)<p class="mt-2 rounded-xl bg-gold-300/20 px-4 py-3 text-sm">{{ __('shop.referral.invited', ['amount' => \App\Support\Settings::get('referral_reward')]) }}</p>@endif
    <form method="POST" action="{{ route('register') }}" class="card mt-6 space-y-4 p-6">
        @include('partials.google-button', ['ref' => $ref])
        @csrf
        <div><label class="label" for="name">{{ __('shop.checkout.name') }}</label><input id="name" name="name" value="{{ old('name') }}" required class="input" autocomplete="name"></div>
        <div><label class="label" for="email">{{ __('shop.checkout.email') }}</label><input id="email" type="email" name="email" value="{{ old('email') }}" required class="input" autocomplete="email"></div>
        <div><label class="label" for="phone">{{ __('shop.checkout.phone') }}</label><input id="phone" name="phone" value="{{ old('phone') }}" class="input" dir="ltr" autocomplete="tel"><p class="mt-1 text-xs text-muted">{{ __('shop.verify.after_signup') }}</p></div>
        <div><label class="label" for="password">{{ __('shop.auth.password') }}</label><input id="password" type="password" name="password" required class="input" autocomplete="new-password"></div>
        <div><label class="label" for="password_confirmation">{{ __('shop.auth.password_confirm') }}</label><input id="password_confirmation" type="password" name="password_confirmation" required class="input" autocomplete="new-password"></div>
        <div><label class="label" for="referral_code">{{ __('shop.referral.code') }}</label><input id="referral_code" name="referral_code" value="{{ old('referral_code', $ref) }}" class="input uppercase" dir="ltr"></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="marketing_opt_in" value="1" class="accent-gold-600"> {{ __('shop.auth.marketing') }}</label>
        <button class="btn-dark w-full">{{ __('shop.auth.register') }}</button>
    </form>
</div>
@endsection
