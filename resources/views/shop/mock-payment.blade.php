@extends('layouts.app')
@section('content')
<div class="container-x max-w-md py-16">
    <div class="card p-8 text-center">
        <p class="eyebrow">Test mode — no real money</p>
        <h1 class="font-display mt-3 text-3xl">Simulated card payment</h1>
        <p class="mt-2 text-muted">Order {{ $order->number }} · {{ \App\Pricing\Money::format(\App\Pricing\Money::toFils($order->amount_due)) }}</p>
        <form method="POST" action="{{ request()->fullUrl() }}" class="mt-6">@csrf<button class="btn-gold w-full">Approve payment</button></form>
        <a href="{{ route('order.show', $order->number) }}" class="mt-3 inline-block text-sm text-muted underline">Cancel</a>
    </div>
</div>
@endsection
