@php
    $url = route('product.show', $p).'?aff='.$affiliate->code;
    $caption = fn ($link) => __('shop.affiliate.caption', ['name' => $p->localized('name'), 'karat' => $p->goldCategory?->name ?? '', 'url' => $link]);
    $texts = ['r' => $caption($url), 'w' => $caption($url.'&w=1')];
@endphp
<div class="card flex gap-3 p-3">
    <img src="{{ $p->primaryImage() }}" alt="" class="h-20 w-20 shrink-0 rounded-xl object-cover bg-sand" loading="lazy">
    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium">{{ $p->localized('name') }}</p>
        <p class="text-xs text-muted">{{ $p->goldCategory?->name }}@if((float) $p->weight_grams > 0) · {{ rtrim(rtrim((string) $p->weight_grams, '0'), '.') }} g @endif</p>
        {{-- What the affiliate earns for selling this piece today. --}}
        @php($earn = app(\App\Affiliates\AffiliateService::class)->earningsFor($affiliate, $p))
        <div class="mt-1.5 rounded-lg bg-gold-300/15 px-2.5 py-1.5 text-xs">
            <p><span class="text-muted">{{ __('shop.affiliate.earn_commission') }}:</span> <b class="price">{{ \App\Pricing\Money::format($earn['commission']) }}</b></p>
            @if ($earn['markup'] > 0)
                <p><span class="text-muted">{{ __('shop.affiliate.earn_markup') }}:</span> <b class="price">{{ \App\Pricing\Money::format($earn['markup']) }}</b>
                    · <span class="text-muted">{{ __('shop.affiliate.earn_total') }}:</span> <b class="price text-gold-700">{{ \App\Pricing\Money::format($earn['commission'] + $earn['markup']) }}</b></p>
            @endif
            <div x-data="{ how: false }">
                <button type="button" class="mt-1 text-[0.6875rem] font-medium text-gold-700 underline" @click="how = !how" :aria-expanded="how">{{ __('shop.affiliate.how.button') }}</button>
                <div x-show="how" x-cloak x-transition class="price mt-2 space-y-2 border-t border-gold-500/20 pt-2 text-[0.6875rem] leading-relaxed">
                    @foreach (['price' => 'title_price', 'commission' => 'title_commission', 'markup' => 'title_markup'] as $part => $title)
                        @if ($earn['steps'][$part])
                            <div><p class="font-semibold">{{ __('shop.affiliate.how.'.$title) }}</p>
                                <ul class="text-muted">@foreach ($earn['steps'][$part] as $step)<li>{{ $step }}</li>@endforeach</ul></div>
                        @endif
                    @endforeach
                    <p class="text-muted">{{ __('shop.affiliate.how.note') }}</p>
                </div>
            </div>
        </div>
        <div class="mt-2 flex flex-wrap gap-2">
            <button type="button" class="btn-whatsapp h-8 px-3 text-xs" @click="share('wa', @js($texts))">WhatsApp</button>
            <button type="button" class="btn-outline h-8 px-3 text-xs" @click="share('copy', @js($texts), 'p{{ $p->id }}')" x-text="done === 'p{{ $p->id }}' ? '✓' : @js(__('shop.affiliate.copy_post'))"></button>
            <a class="btn-outline h-8 px-3 text-xs" href="{{ $p->primaryImage() }}" download target="_blank">{{ __('shop.affiliate.photo') }}</a>
        </div>
    </div>
</div>
