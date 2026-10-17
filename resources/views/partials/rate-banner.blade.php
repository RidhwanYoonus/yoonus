@php
    $rateData = \Illuminate\Support\Facades\Cache::remember('home.rates', 60, fn () => [
        'updated_at' => optional(\App\Models\GoldCategory::max('rate_updated_at'), fn ($d) => \Illuminate\Support\Carbon::parse($d)->toIso8601String()),
        'rates' => \App\Models\GoldCategory::where('is_active', true)->orderByDesc('karat')->get(['id', 'name', 'rate_per_gram'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'rate' => $c->rate_per_gram])->all(),
    ]);
@endphp
<div class="bg-onyx text-gold-300 text-xs" x-data="rateTicker(@js($rateData), '{{ route('api.rates') }}')">
    <div class="container-x flex items-center gap-x-6 gap-y-1 overflow-x-auto whitespace-nowrap py-2">
        <span class="font-medium uppercase tracking-[0.2em] text-gold-400">{{ __('shop.rates.today') }}</span>
        <template x-for="r in rates" :key="r.id">
            <span><span class="text-ivory/70" x-text="r.name"></span> <span class="price font-semibold text-gold-300" x-text="'{{ __('shop.currency') }} ' + Number(r.rate).toFixed(2)"></span><span class="text-ivory/50">/{{ __('shop.rates.gram') }}</span></span>
        </template>
        <span class="ms-auto text-ivory/50" x-show="updatedAt">{{ __('shop.rates.updated') }} <span x-text="new Date(updatedAt).toLocaleString('{{ app()->getLocale() === 'ar' ? 'ar-AE' : 'en-AE' }}', {dateStyle: 'medium', timeStyle: 'short', timeZone: '{{ config('app.timezone') }}'})"></span></span>
    </div>
</div>
