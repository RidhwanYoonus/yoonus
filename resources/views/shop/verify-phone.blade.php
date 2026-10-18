@extends('layouts.app')
@section('content')
<div class="container-x max-w-md py-16">
    <p class="eyebrow">{{ __('shop.verify.eyebrow') }}</p>
    <h1 class="mt-2 font-display text-4xl">{{ __('shop.verify.title') }}</h1>
    <p class="mt-2 text-sm text-muted">{{ __('shop.verify.intro') }}</p>
    <div class="card mt-6 p-6">
        @include('partials.phone-verify', ['initialPhone' => $user->phone, 'verifiedPhones' => $user->phone_verified_at && $user->phone ? [\App\Shop\PhoneVerification::normalise($user->phone)] : session('verified_phones', [])])
    </div>
    <div class="mt-6 flex justify-between text-sm">
        <a href="{{ session('url.intended', route('account')) }}" class="text-muted underline">{{ __('shop.verify.later') }}</a>
        <a href="{{ route('account') }}" class="text-gold-700 underline">{{ __('shop.nav.account') }} →</a>
    </div>
</div>
@endsection
