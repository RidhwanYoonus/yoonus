@php
    $rateData = \Illuminate\Support\Facades\Cache::remember('home.rates', 60, fn () => [
        'updated_at' => optional(\App\Models\GoldCategory::max('rate_updated_at'), fn ($d) => \Illuminate\Support\Carbon::parse($d)->toIso8601String()),
        'rates' => \App\Models\GoldCategory::where('is_active', true)->orderByDesc('karat')->get(['id', 'name', 'rate_per_gram'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'rate' => $c->rate_per_gram])->all(),
    ]);
@endphp
<div class="bg-onyx text-[0.6875rem] text-on-dark/70 sm:text-xs" x-data="rateTicker(@js($rateData), '{{ route('api.rates') }}')">
    <div class="container-x no-scrollbar flex items-center gap-x-5 overflow-x-auto whitespace-nowrap py-2 lg:justify-center">
        <span class="flex items-center gap-1.5 font-medium uppercase tracking-[0.2em] text-gold-400">
            <span class="relative flex h-1.5 w-1.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-gold-400 opacity-60"></span><span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-gold-400"></span></span>
            {{ __('shop.rates.today') }}
        </span>
        <template x-for="r in rates" :key="r.id">
            <span class="border-s border-white/10 ps-5"><span x-text="r.name"></span> <span class="price font-semibold text-gold-300" x-text="Number(r.rate).toFixed(2)"></span> <span class="text-on-dark/40">{{ __('shop.currency') }}/{{ __('shop.rates.gram') }}</span></span>
        </template>
        <span class="hidden border-s border-white/10 ps-5 text-on-dark/40 md:inline" x-show="updatedAt">{{ __('shop.rates.updated') }} <span x-text="new Date(updatedAt).toLocaleString('{{ app()->getLocale() === 'ar' ? 'ar-AE' : 'en-AE' }}', {dateStyle: 'medium', timeStyle: 'short', timeZone: '{{ config('app.timezone') }}'})"></span></span>
    </div>
</div>
