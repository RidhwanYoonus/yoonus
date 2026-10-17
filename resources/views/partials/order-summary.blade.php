@php($m = fn ($v) => \App\Pricing\Money::format(\App\Pricing\Money::toFils($v)))
<div class="card overflow-hidden">
    <table class="w-full text-sm price">
        <thead class="bg-sand/60 text-xs uppercase tracking-wider text-muted"><tr><th class="px-4 py-3 text-start">{{ __('shop.order.item') }}</th><th class="px-4 py-3 text-end">{{ __('shop.order.total') }}</th></tr></thead>
        <tbody class="divide-y divide-line">
            @foreach ($order->items as $item)
                <tr><td class="px-4 py-3">{{ $item->localizedName() }} <span class="text-muted">× {{ $item->qty }}</span><span class="block text-xs text-muted">{{ $item->karat }}@if ((float) $item->weight_grams > 0) · {{ $item->weight_grams }} g @endif @if ((float) $item->gold_rate_snapshot > 0)· {{ __('shop.order.rate_at_purchase', ['rate' => $item->gold_rate_snapshot]) }}@endif</span></td><td class="px-4 py-3 text-end">{{ $m((float) $item->subtotal - (float) $item->discount_amount) }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <dl class="space-y-1.5 border-t border-line p-4 text-sm price">
        <div class="flex justify-between"><dt>{{ __('shop.checkout.subtotal_ex_vat') }}</dt><dd>{{ $m($order->items_subtotal) }}</dd></div>
        @if ((float) $order->discount_total)<div class="flex justify-between text-gold-700"><dt>{{ __('shop.checkout.discount') }} @if($order->coupon_code)({{ $order->coupon_code }})@endif</dt><dd>− {{ $m($order->discount_total) }}</dd></div>@endif
        <div class="flex justify-between"><dt>{{ __('shop.checkout.shipping') }}</dt><dd>{{ (float) $order->shipping_total ? $m((float) $order->shipping_total - (float) $order->shipping_vat) : __('shop.checkout.free') }}</dd></div>
        @if ((float) $order->payment_fee)<div class="flex justify-between"><dt>{{ __('shop.checkout.payment_fee') }}</dt><dd>{{ $m($order->payment_fee) }}</dd></div>@endif
        <div class="flex justify-between"><dt>{{ __('shop.checkout.vat') }}</dt><dd>{{ $m((float) $order->vat_total + (float) $order->shipping_vat + (float) $order->payment_fee_vat) }}</dd></div>
        <div class="flex justify-between border-t border-line pt-2 font-semibold"><dt>{{ __('shop.checkout.total') }}</dt><dd class="text-gold-700">{{ $m($order->grand_total) }}</dd></div>
        @if ((float) $order->gift_card_amount)<div class="flex justify-between"><dt>{{ __('shop.checkout.gift_card') }}</dt><dd>− {{ $m($order->gift_card_amount) }}</dd></div>@endif
    </dl>
</div>
