@extends('layouts.app')
@php
    $product = $call->product;
    $join = $call->customerJoinUrl();
@endphp

@section('content')
<div class="container-x max-w-3xl py-10" x-data="{ status: @js($call->status), join: @js($join), poll() { fetch(@js(route('video-call.status', $call))).then(r => r.json()).then(d => { this.status = d.status; if (d.join) this.join = d.join }).catch(() => {}) } }" x-init="setInterval(() => poll(), 5000)">
    <p class="text-xs uppercase tracking-[0.3em] text-gold-700">{{ __('shop.video.eyebrow') }}</p>
    <h1 class="mt-2 font-display text-3xl sm:text-4xl">{{ __('shop.video.title') }}</h1>

    <div class="card mt-8 overflow-hidden">
        @if ($product)
            <a href="{{ route('product.show', $product) }}" class="flex items-center gap-4 border-b border-line p-4">
                <img src="{{ $product->primaryImage() }}" alt="" class="h-16 w-16 rounded-xl object-cover bg-sand">
                <div><p class="font-medium">{{ $product->localized('name') }}</p><p class="text-sm text-muted">{{ $product->specLine() }} · {{ $product->sku }}</p></div>
            </a>
        @endif
        <div class="space-y-5 p-6 text-center">
            <div x-show="status === 'waiting'" class="flex items-center justify-center gap-3 text-sm">
                <span class="relative flex h-3 w-3"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-gold-500 opacity-75"></span><span class="relative inline-flex h-3 w-3 rounded-full bg-gold-600"></span></span>
                {{ __('shop.video.waiting') }}
            </div>
            <p x-show="status === 'answered'" x-cloak class="text-sm font-medium text-green-700">● {{ __('shop.video.answered') }}</p>
            <p x-show="status === 'done' || status === 'missed'" x-cloak class="text-sm text-muted">{{ __('shop.video.ended') }}</p>

            @if ($call->platform)
                <p class="rounded-xl bg-sand px-4 py-3 text-sm" x-show="!join">{{ __('shop.video.we_call', ['app' => $call->platformName(), 'phone' => $call->phone]) }}</p>
            @endif
            <a x-show="join" :href="join" href="{{ $join }}" target="_blank" rel="noopener" class="btn-dark w-full gap-2 py-4 text-base sm:w-auto sm:px-10">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 10l4.55-2.28A1 1 0 0121 8.62v6.76a1 1 0 01-1.45.9L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                {{ __('shop.video.join') }}
            </a>
            <p class="text-xs text-muted" x-show="join">{{ __('shop.video.join_note') }}</p>
            <p class="text-xs text-muted">{{ __('shop.video.hint', ['hours' => \App\Support\Settings::get('video_call_hours')]) }}</p>
        </div>
    </div>

    @if ($whatsapp)
        <p class="mt-6 text-center text-sm text-muted">{{ __('shop.video.whatsapp_alt') }}
            <a href="{{ \App\Messaging\WhatsApp::link($whatsapp, __('shop.video.whatsapp_text', ['name' => $product?->name ?? '', 'sku' => $product?->sku ?? ''])) }}" target="_blank" rel="noopener" class="text-gold-700 underline">WhatsApp</a>
        </p>
    @endif
</div>
@endsection
