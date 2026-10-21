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
        <div class="mt-2 flex flex-wrap gap-2">
            <button type="button" class="btn-whatsapp h-8 px-3 text-xs" @click="share('wa', @js($texts))">WhatsApp</button>
            <button type="button" class="btn-outline h-8 px-3 text-xs" @click="share('copy', @js($texts), 'p{{ $p->id }}')" x-text="done === 'p{{ $p->id }}' ? '✓' : @js(__('shop.affiliate.copy_post'))"></button>
            <a class="btn-outline h-8 px-3 text-xs" href="{{ $p->primaryImage() }}" download target="_blank">{{ __('shop.affiliate.photo') }}</a>
        </div>
    </div>
</div>
