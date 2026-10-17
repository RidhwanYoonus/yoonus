@extends('layouts.app')

@section('content')
<div class="container-x max-w-3xl py-16">
    <div class="text-center">
        <p class="eyebrow">{{ __('shop.checkout.thanks') }}</p>
        <h1 class="font-display mt-3 text-4xl">{{ __('shop.order.number', ['number' => $order->number]) }}</h1>
        <p class="mt-3 text-muted">
            @if ($order->isPaid()) {{ __('shop.order.paid_message') }}
            @elseif ($order->payment_method === 'cod') {{ __('shop.order.cod_message') }}
            @else {{ __('shop.order.awaiting_payment') }} @endif
        </p>
    </div>
    <div class="mt-10">@include('partials.order-summary')</div>
    <div class="mt-6 flex flex-wrap justify-center gap-3">
        <a href="{{ route('order.show', $order->number) }}" class="btn-dark">{{ __('shop.notify.view_order') }}</a>
        @if ($order->invoice_number)<a href="{{ route('order.invoice', $order->number) }}" class="btn-outline">{{ __('shop.order.download_invoice') }}</a>@endif
    </div>
</div>
@endsection

@if ($order->isPaid() && ! session('purchase_tracked_'.$order->number))
    @php(session()->put('purchase_tracked_'.$order->number, true))
    @push('scripts')
    <script>document.addEventListener('DOMContentLoaded', () => shopTrack('purchase', @js(['transaction_id' => $order->number, 'value' => (float) $order->grand_total, 'items' => $order->items->map(fn ($i) => ['item_id' => $i->sku, 'item_name' => $i->product_name, 'quantity' => $i->qty, 'price' => round((float) $i->line_total / $i->qty, 2)])->all()])));</script>
    @endpush
@endif
