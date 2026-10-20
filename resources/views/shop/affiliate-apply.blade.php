@extends('layouts.app')
@section('content')
<div class="container-x grid max-w-5xl gap-10 py-14 lg:grid-cols-2">
    <div>
        <p class="eyebrow">{{ __('shop.affiliate.eyebrow') }}</p>
        <h1 class="mt-2 font-display text-4xl">{{ __('shop.affiliate.apply_title') }}</h1>
        <p class="mt-3 text-muted">{{ __('shop.affiliate.apply_intro') }}</p>
        <ul class="mt-6 space-y-3 text-sm">
            @foreach (['apply_point_1', 'apply_point_2', 'apply_point_3', 'apply_point_4'] as $k)
                <li class="flex gap-3"><span class="mt-0.5 text-gold-600">✦</span><span>{{ __('shop.affiliate.'.$k) }}</span></li>
            @endforeach
        </ul>
        @if ($ref)<p class="mt-6 rounded-xl bg-sand px-4 py-3 text-sm">{{ __('shop.affiliate.apply_invited', ['name' => $ref->name()]) }}</p>@endif
    </div>
    <form method="POST" action="{{ route('affiliate.apply.store') }}" class="card grid gap-4 p-6">
        @csrf
        <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
        <input type="hidden" name="ref" value="{{ $ref?->code }}">
        <label class="grid gap-1 text-sm">{{ __('shop.affiliate.visit_name') }}<input name="name" required maxlength="120" value="{{ old('name') }}" class="input"></label>
        <label class="grid gap-1 text-sm">{{ __('shop.affiliate.apply_email') }}<input name="email" type="email" required maxlength="191" value="{{ old('email') }}" class="input" dir="ltr"></label>
        <label class="grid gap-1 text-sm">{{ __('shop.affiliate.apply_phone') }}<input name="phone" required maxlength="32" value="{{ old('phone') }}" class="input" dir="ltr" placeholder="+971 5X XXX XXXX"></label>
        <label class="grid gap-1 text-sm">{{ __('shop.affiliate.apply_social') }}<input name="social" maxlength="191" value="{{ old('social') }}" class="input" dir="ltr" placeholder="@yourpage"></label>
        <label class="grid gap-1 text-sm">{{ __('shop.affiliate.apply_message') }}<textarea name="message" rows="3" maxlength="1000" class="input">{{ old('message') }}</textarea></label>
        <button class="btn-gold h-11">{{ __('shop.affiliate.apply_send') }}</button>
    </form>
</div>
@endsection
