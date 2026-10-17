@extends('layouts.app')

@section('content')
<div class="container-x max-w-md py-16">
    <h1 class="font-display text-4xl">{{ __('shop.nav.track') }}</h1>
    <form method="GET" class="card mt-6 space-y-4 p-6">
        <div><label class="label" for="number">{{ __('shop.track.number') }}</label><input id="number" name="number" value="{{ old('number') }}" required class="input uppercase" dir="ltr"></div>
        <div><label class="label" for="email">{{ __('shop.checkout.email') }}</label><input id="email" type="email" name="email" value="{{ old('email') }}" required class="input"></div>
        <button class="btn-dark w-full">{{ __('shop.track.find') }}</button>
    </form>
</div>
@endsection
