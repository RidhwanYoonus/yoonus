@extends('layouts.app')

@section('content')
<div class="container-x max-w-md py-16">
    <h1 class="font-display text-4xl">{{ __('shop.nav.track') }}</h1>
    <a href="{{ route('login.code') }}" class="card mt-6 flex items-center gap-3 p-4 text-sm hover:border-gold-500">📱 <span><b>{{ __('shop.code_login.button') }}</b><span class="block text-xs text-muted">{{ __('shop.code_login.track_note') }}</span></span></a>
    <form method="GET" class="card mt-6 space-y-4 p-6">
        <div><label class="label" for="number">{{ __('shop.track.number') }}</label><input id="number" name="number" value="{{ old('number') }}" required class="input uppercase" dir="ltr"></div>
        <div><label class="label" for="email">{{ __('shop.checkout.email') }}</label><input id="email" type="email" name="email" value="{{ old('email') }}" required class="input"></div>
        <button class="btn-dark w-full">{{ __('shop.track.find') }}</button>
    </form>
</div>
@endsection
