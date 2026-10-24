@extends('layouts.app')
@section('content')
<div class="container-x max-w-md py-16">
    <h1 class="font-display text-4xl">{{ __('shop.reset.title') }}</h1>
    <p class="mt-2 text-sm text-muted">{{ __('shop.reset.intro') }}</p>
    <form method="POST" action="{{ route('password.email') }}" class="card mt-6 space-y-4 p-6" data-live-validate>
        @csrf
        <div><label class="label" for="email">{{ __('shop.checkout.email') }}</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus class="input" autocomplete="email"></div>
        <button class="btn-dark w-full">{{ __('shop.reset.send') }}</button>
        <p class="text-center text-sm"><a href="{{ route('login') }}" class="text-gold-700 underline">{{ __('shop.auth.login') }}</a></p>
    </form>
</div>
@endsection
