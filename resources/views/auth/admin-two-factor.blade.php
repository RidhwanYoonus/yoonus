@extends('layouts.app')
@section('content')
<div class="container-x max-w-md py-16">
    <p class="eyebrow">Admin security</p>
    <h1 class="mt-2 font-display text-4xl">Enter your sign-in code</h1>
    <p class="mt-2 text-sm text-muted">
        We sent a 6-digit code to <strong dir="ltr">{{ $email }}</strong>@if ($phone) and by WhatsApp to <strong dir="ltr">{{ $phone }}</strong>@endif. It expires in 10 minutes.
    </p>
    @if (session('status'))<p class="mt-4 rounded-xl bg-sand px-4 py-3 text-sm">{{ session('status') }}</p>@endif
    <form method="POST" action="{{ route('admin.two-factor.verify') }}" class="card mt-6 grid gap-4 p-6">
        @csrf
        <label class="grid gap-1 text-sm">Code
            <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus class="input text-center text-2xl tracking-[0.4em]" dir="ltr">
        </label>
        @error('code')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="trust" value="1"> Trust this computer for {{ \App\Support\AdminTwoFactor::TRUST_DAYS }} days</label>
        <button class="btn-gold h-11">Continue</button>
    </form>
    <div class="mt-6 flex justify-between text-sm">
        <form method="POST" action="{{ route('admin.two-factor.resend') }}">@csrf<button class="text-gold-700 underline">Send a new code</button></form>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-muted underline">Sign out</button></form>
    </div>
</div>
@endsection
