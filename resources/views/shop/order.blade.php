@extends('layouts.app')

@section('content')
<div class="container-x max-w-4xl py-12">
    <h1 class="font-display text-4xl">{{ __('shop.order.number', ['number' => $order->number]) }}</h1>
    <p class="mt-1 text-sm text-muted">{{ $order->created_at->locale(app()->getLocale())->isoFormat('LLL') }}</p>

    @php($steps = $order->steps())
    @php($current = array_search($order->status, $steps))
    @if ($current !== false)
        <ol class="mt-8 grid grid-cols-5 gap-2 text-center text-xs">
            @foreach ($steps as $i => $s)
                <li><span class="mx-auto flex h-8 w-8 items-center justify-center rounded-full {{ $i <= $current ? 'bg-gold-silver text-zinc-900' : 'bg-sand text-muted' }}">{{ $i + 1 }}</span><span class="mt-2 block">{{ $order->statusLabel($s) }}</span></li>
            @endforeach
        </ol>
    @else
        <p class="mt-6 rounded-xl bg-sand px-4 py-3 text-sm">{{ $order->statusLabel() }}</p>
    @endif

    @if ($order->handover_otp_sent_at && ! $order->handover_verified_at && in_array($order->status, ['shipped', 'ready']))
        <p class="mt-6 rounded-xl border border-gold-400 bg-gold-300/20 px-4 py-3 text-sm">🔐 {{ __('shop.otp.sent_note') }}</p>
    @endif
    @if ($order->tracking_number)
        <p class="mt-6 text-sm">{{ __('shop.order.tracking') }}: <strong dir="ltr">{{ $order->tracking_carrier }} {{ $order->tracking_number }}</strong></p>
    @endif
    @if ($order->pickupBranch)
        <p class="mt-6 text-sm">{{ __('shop.order.pickup_at', ['branch' => $order->pickupBranch->localized('name'), 'address' => $order->pickupBranch->address]) }}</p>
    @endif

    <div class="mt-8 grid gap-8 md:grid-cols-[1fr_260px]">
        @include('partials.order-summary')
        <div class="space-y-4 text-sm">
            @if ($order->shipping_address)
                <div class="card p-4"><p class="label">{{ __('shop.checkout.delivery') }}</p><p>{{ $order->shipping_address['name'] }}<br>{{ $order->shipping_address['line1'] }} {{ $order->shipping_address['line2'] }}<br>{{ $order->shipping_address['city'] }}, {{ $order->shipping_address['zone'] }}</p></div>
            @endif
            <div class="card p-4"><p class="label">{{ __('shop.checkout.payment') }}</p><p>{{ __('shop.pay.'.$order->payment_method) }} — {{ __('shop.pay_status.'.$order->payment_status) }}</p></div>
            @if ($order->invoice_number)<a href="{{ route('order.invoice', $order->number) }}" class="btn-outline w-full">{{ __('shop.order.download_invoice') }}</a>@endif
        </div>
    </div>
</div>
@endsection
