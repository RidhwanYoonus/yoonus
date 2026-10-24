@extends('layouts.app')
@php use App\Pricing\Money; $amount = Money::format(Money::toFils($order->amount_due)); @endphp

@section('content')
<div class="container-x max-w-lg py-12"
     x-data="{ choice: @js($cards ? $cards[0]['id'] : 'new'), save: true, busy: false, err: '', ready: false, stripe: null, elements: null,
        init() {
            const start = () => {
                this.stripe = Stripe(@js($publishableKey), { locale: @js(app()->getLocale()) });
                this.elements = this.stripe.elements({ clientSecret: @js($clientSecret), appearance: { theme: 'stripe', variables: { colorPrimary: '#9a7a2c', borderRadius: '12px', fontFamily: 'Inter, system-ui, sans-serif' } } });
                const pe = this.elements.create('payment', { layout: 'tabs', wallets: { applePay: 'auto', googlePay: 'auto' } });
                pe.on('ready', () => this.ready = true);
                pe.mount(this.$refs.card);
            };
            window.Stripe ? start() : document.getElementById('stripe-js').addEventListener('load', start);
        },
        async post(url, data) {
            const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify(data) });
            return { ok: r.ok, data: await r.json().catch(() => ({})) };
        },
        async pay() {
            if (this.busy) return;
            this.busy = true; this.err = '';
            try {
                if (this.choice !== 'new') {
                    const { data } = await this.post(@js(route('payments.card.saved', $order->number)), { payment_method: this.choice });
                    if (data.done) { location.href = data.done; return; }
                    if (data.action) {
                        const { error, paymentIntent } = await this.stripe.handleNextAction({ clientSecret: data.client_secret });
                        if (! error) { location.href = @js($returnUrl) + '?payment_intent=' + paymentIntent.id; return; }
                        this.err = error.message;
                    } else { this.err = data.error || @js(__('shop.card.declined')); }
                } else {
                    @if ($canSave) await this.post(@js(route('payments.card.prepare', $order->number)), { save: this.save }); @endif
                    const { error } = await this.stripe.confirmPayment({ elements: this.elements, confirmParams: { return_url: @js($returnUrl) } });
                    this.err = error?.message || '';   // otherwise the browser is already on its way to the order
                }
            } catch (e) { this.err = @js(__('shop.card.declined')); }
            this.busy = false;
        } }">
    <p class="eyebrow">{{ __('shop.order.title', ['number' => $order->number]) }}</p>
    <h1 class="mt-2 font-display text-3xl">{{ __('shop.card.title') }}</h1>
    <p class="mt-2 text-2xl font-semibold price">{{ $amount }}</p>
    <p class="mt-3 flex items-start gap-2 rounded-xl bg-sand px-4 py-3 text-xs text-muted">🔒 <span>{{ __('shop.card.secure') }}</span></p>

    <div class="card mt-6 space-y-3 p-5">
        @foreach ($cards as $c)
            <label class="flex cursor-pointer items-center gap-3 rounded-xl border p-3 text-sm" :class="choice === @js($c['id']) ? 'border-gold-500 ring-1 ring-gold-500' : 'border-line'">
                <input type="radio" x-model="choice" value="{{ $c['id'] }}" class="accent-gold-600">
                <span class="font-medium">{{ $c['brand'] }} •••• {{ $c['last4'] }}</span><span class="ms-auto text-xs text-muted">{{ __('shop.card.expires', ['date' => $c['exp']]) }}</span>
            </label>
        @endforeach
        @if ($cards)
            <label class="flex cursor-pointer items-center gap-3 rounded-xl border p-3 text-sm" :class="choice === 'new' ? 'border-gold-500 ring-1 ring-gold-500' : 'border-line'">
                <input type="radio" x-model="choice" value="new" class="accent-gold-600"> <span class="font-medium">{{ __('shop.card.new') }}</span></label>
        @endif
        <div x-show="choice === 'new'">
            <div x-ref="card" class="min-h-[10rem]"><p x-show="! ready" class="py-8 text-center text-sm text-muted">{{ __('shop.card.loading') }}</p></div>
            @if ($canSave)
                <label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" x-model="save" class="accent-gold-600"> {{ __('shop.card.save') }}</label>
            @endif
        </div>
        <p x-show="err" x-cloak x-text="err" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>
        <button type="button" @click="pay()" class="btn-gold h-12 w-full" :disabled="busy || (choice === 'new' && ! ready)">
            <span x-show="! busy">{{ __('shop.card.pay', ['amount' => $amount]) }}</span><span x-show="busy" x-cloak>{{ __('shop.card.paying') }}</span></button>
    </div>
    <a href="{{ route('order.show', $order->number) }}" class="mt-4 inline-block text-sm text-muted underline">{{ __('shop.card.back') }}</a>
</div>
<script id="stripe-js" src="https://js.stripe.com/v3/" async></script>
@endsection
