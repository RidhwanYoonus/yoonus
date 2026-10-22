@php
    $rateData = \Illuminate\Support\Facades\Cache::remember('home.rates', 60, fn () => [
        'updated_at' => optional(\App\Models\GoldCategory::max('rate_updated_at'), fn ($d) => \Illuminate\Support\Carbon::parse($d)->toIso8601String()),
        'rates' => \App\Models\GoldCategory::where('is_active', true)->orderByDesc('karat')->get(['id', 'name', 'rate_per_gram'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'rate' => $c->rate_per_gram])->all(),
    ]);
@endphp
{{-- Live gold rates: large, high-contrast chips so customers can read today's price at a glance. --}}
<div class="border-b border-gold-500/30 bg-onyx text-sm text-white" x-data="rateTicker(@js($rateData), '{{ route('api.rates') }}')">
    {{-- Phones and tablets: the prices move like a news ticker (tap/hover pauses). Large screens: all shown, centred. --}}
    <div class="container-x flex items-center gap-2 py-2 sm:gap-3 lg:justify-center">
        <span class="z-[1] flex shrink-0 items-center gap-2 rounded-md bg-gold-silver px-3 py-1.5 text-xs font-bold uppercase tracking-[0.14em] text-zinc-900">
            <span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-600 opacity-60"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-600"></span></span>
            {{ __('shop.rates.today') }}
        </span>
        <div class="rate-ticker min-w-0 flex-1 overflow-hidden lg:flex-none lg:overflow-visible">
            <div class="rate-track flex w-max items-center gap-2 whitespace-nowrap sm:gap-3">
                @foreach ([false, true] as $copy)
                    <div class="flex items-center gap-2 sm:gap-3 {{ $copy ? 'lg:hidden' : '' }}" @if ($copy) aria-hidden="true" @endif>
                        <template x-for="r in rates" :key="r.id">
                            <span class="flex shrink-0 items-baseline gap-1.5 rounded-md border border-white/15 bg-white/[0.06] px-3 py-1.5">
                                <span class="text-xs font-bold uppercase text-rate" x-text="r.name"></span>
                                <span class="price text-[0.95rem] font-bold text-white sm:text-base" x-text="Number(r.rate).toFixed(2)"></span>
                                <span class="text-xs text-white/75">{{ __('shop.currency') }}/{{ __('shop.rates.gram') }}</span>
                            </span>
                        </template>
                        <span class="shrink-0 ps-1 text-xs text-white/75" x-show="updatedAt">{{ __('shop.rates.updated') }} <span class="font-medium text-white" x-text="new Date(updatedAt).toLocaleString('{{ app()->getLocale() === 'ar' ? 'ar-AE' : 'en-AE' }}', {dateStyle: 'medium', timeStyle: 'short', timeZone: '{{ config('app.timezone') }}'})"></span></span>
                        <span class="w-6 shrink-0 lg:hidden" aria-hidden="true"></span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
<style>
    @keyframes rate-ticker { from { transform: translateX(0); } to { transform: translateX(-50%); } }
    @keyframes rate-ticker-rtl { from { transform: translateX(0); } to { transform: translateX(50%); } }
    @media (max-width: 1023px) {
        .rate-track { animation: rate-ticker 22s linear infinite; }
        [dir="rtl"] .rate-track { animation-name: rate-ticker-rtl; }
        .rate-ticker:hover .rate-track, .rate-ticker:active .rate-track { animation-play-state: paused; }
    }
    @media (prefers-reduced-motion: reduce) { .rate-track { animation: none !important; } .rate-ticker { overflow-x: auto; } }
</style>
