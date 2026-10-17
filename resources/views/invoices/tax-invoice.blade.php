@php
    use App\Support\Settings;
    $m = fn ($v) => number_format((float) $v, 2);
    $address = $order->shipping_address;
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 28px 32px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #222; }
    h1 { font-size: 18px; margin: 0; letter-spacing: 1px; }
    .muted { color: #666; }
    .gold { color: #8a6d2b; }
    table { width: 100%; border-collapse: collapse; }
    .grid td { vertical-align: top; padding: 0; }
    .items th { background: #f3ecdc; text-align: left; padding: 6px 5px; font-size: 9px; border-bottom: 1px solid #d9c9a3; }
    .items td { padding: 6px 5px; border-bottom: 1px solid #eee; }
    .num { text-align: right; white-space: nowrap; }
    .totals td { padding: 4px 5px; }
    .totals .grand td { font-size: 12px; font-weight: bold; border-top: 1.5px solid #8a6d2b; }
    .box { border: 1px solid #e3d6b8; padding: 8px 10px; }
    .ar { direction: rtl; text-align: right; }
</style>
</head>
<body>
<table class="grid">
    <tr>
        <td style="width:60%">
            <h1 class="gold">{{ Settings::get('store_legal_name') }}</h1>
            <div class="ar" style="text-align:left">{{ $ar(Settings::get('store_name_ar')) }}</div>
            <div class="muted">{{ Settings::get('store_address') }}</div>
            <div class="muted">{{ Settings::get('store_phone') }} · {{ Settings::get('store_email') }}</div>
            <div><strong>TRN: {{ Settings::get('store_trn') }}</strong></div>
        </td>
        <td style="width:40%; text-align:right">
            <h1>TAX INVOICE</h1>
            <div>{{ $ar('فاتورة ضريبية') }}</div>
            <div style="margin-top:6px">Invoice No: <strong>{{ $order->invoice_number }}</strong></div>
            <div>Invoice Date: {{ ($order->invoiced_at ?? now())->format('d M Y') }}</div>
            <div>Date of Supply: {{ ($order->paid_at ?? $order->created_at)->format('d M Y') }}</div>
            <div>Order: {{ $order->number }}</div>
        </td>
    </tr>
</table>

<table class="grid" style="margin-top:14px">
    <tr>
        <td style="width:50%; padding-right:6px">
            <div class="box">
                <div class="muted">BILL TO / {{ $ar('العميل') }}</div>
                <strong>{{ $order->customer_name }}</strong><br>
                @if ($address){{ $address['line1'] ?? '' }} {{ $address['line2'] ?? '' }}<br>{{ $address['city'] ?? '' }}, {{ $address['zone'] ?? '' }}, UAE<br>@endif
                {{ $order->email }} · {{ $order->phone }}
                @if ($order->buyer_trn)<br><strong>Buyer TRN: {{ $order->buyer_trn }}</strong>@endif
            </div>
        </td>
        <td style="width:50%; padding-left:6px">
            <div class="box">
                <div class="muted">PAYMENT / FULFILMENT</div>
                {{ strtoupper($order->payment_method) }} — {{ strtoupper($order->payment_status) }}<br>
                {{ $order->fulfilment === 'pickup' ? 'Store pickup: '.($order->pickupBranch?->name ?? '') : 'Delivery: '.($order->shippingZone?->name ?? '') }}<br>
                Currency: AED
            </div>
        </td>
    </tr>
</table>

<table class="items" style="margin-top:14px">
    <thead>
        <tr>
            <th>#</th><th>Description</th><th class="num">Qty</th><th class="num">Gold rate/g</th>
            <th class="num">Gold value</th><th class="num">Making</th><th class="num">Stone</th>
            <th class="num">Discount</th><th class="num">Taxable amount</th><th class="num">VAT %</th><th class="num">VAT</th><th class="num">Total (AED)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($order->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->product_name }}@if ($item->product_name_ar)<br>{{ $ar($item->product_name_ar) }}@endif<br><span class="muted">{{ $item->sku }} · {{ $item->karat }} · {{ $item->weight_grams }} g</span></td>
                <td class="num">{{ $item->qty }}</td>
                <td class="num">{{ $m($item->gold_rate_snapshot) }}</td>
                <td class="num">{{ $m($item->gold_value) }}</td>
                <td class="num">{{ $m((float) $item->making_fee + (float) $item->wastage + (float) $item->rounding) }}</td>
                <td class="num">{{ $m($item->stone_value) }}</td>
                <td class="num">{{ (float) $item->discount_amount ? '-'.$m($item->discount_amount) : '0.00' }}</td>
                <td class="num">{{ $m((float) $item->subtotal - (float) $item->discount_amount) }}</td>
                <td class="num">{{ rtrim(rtrim($item->vat_percent, '0'), '.') }}%</td>
                <td class="num">{{ $m($item->vat_amount) }}</td>
                <td class="num">{{ $m($item->line_total) }}</td>
            </tr>
        @endforeach
        @if ((float) $order->shipping_total)
            <tr>
                <td></td><td>Delivery charge</td><td class="num">1</td><td colspan="5"></td>
                <td class="num">{{ $m((float) $order->shipping_total - (float) $order->shipping_vat) }}</td>
                <td class="num">{{ rtrim(rtrim(number_format((float) Settings::get('default_vat_percent'), 2), '0'), '.') }}%</td>
                <td class="num">{{ $m($order->shipping_vat) }}</td>
                <td class="num">{{ $m($order->shipping_total) }}</td>
            </tr>
        @endif
        @if ((float) $order->payment_fee)
            <tr>
                <td></td><td>Payment processing fee ({{ strtoupper($order->payment_method) }})</td><td class="num">1</td><td colspan="5"></td>
                <td class="num">{{ $m($order->payment_fee) }}</td>
                <td class="num">{{ rtrim(rtrim(number_format((float) Settings::get('default_vat_percent'), 2), '0'), '.') }}%</td>
                <td class="num">{{ $m($order->payment_fee_vat) }}</td>
                <td class="num">{{ $m((float) $order->payment_fee + (float) $order->payment_fee_vat) }}</td>
            </tr>
        @endif
    </tbody>
</table>

<table class="totals" style="margin-top:12px; width:50%; margin-left:50%">
    <tr><td>Total before discount (excl. VAT)</td><td class="num">{{ $m($order->items_subtotal) }}</td></tr>
    @if ((float) $order->discount_total)<tr><td>Discount{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }}</td><td class="num">-{{ $m($order->discount_total) }}</td></tr>@endif
    @if ((float) $order->shipping_total)<tr><td>Delivery (excl. VAT)</td><td class="num">{{ $m((float) $order->shipping_total - (float) $order->shipping_vat) }}</td></tr>@endif
    @if ((float) $order->payment_fee)<tr><td>Payment processing fee (excl. VAT)</td><td class="num">{{ $m($order->payment_fee) }}</td></tr>@endif
    <tr><td>Total taxable amount</td><td class="num">{{ $m((float) $order->taxable_amount + (float) $order->shipping_total - (float) $order->shipping_vat + (float) $order->payment_fee) }}</td></tr>
    <tr><td>Total VAT</td><td class="num">{{ $m((float) $order->vat_total + (float) $order->shipping_vat + (float) $order->payment_fee_vat) }}</td></tr>
    <tr class="grand"><td>Total amount payable (AED)</td><td class="num">{{ $m($order->grand_total) }}</td></tr>
    @if ((float) $order->gift_card_amount)<tr><td class="muted">Paid by gift card</td><td class="num muted">-{{ $m($order->gift_card_amount) }}</td></tr>@endif
</table>

<p class="muted" style="margin-top:24px">Discounts are applied before VAT; VAT is charged on the discounted goods, delivery and payment fee in accordance with UAE Federal Decree-Law No. 8 of 2017 on VAT. Gold prices reflect the rate per gram at the time of purchase as shown above.</p>
<p class="muted">This is a computer-generated tax invoice.</p>
</body>
</html>
