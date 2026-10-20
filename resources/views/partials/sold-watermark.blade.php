{{-- Big "SOLD" stamp across a sold piece's photo. $large for the product page. --}}
<div class="pointer-events-none absolute inset-0 z-[1] flex items-center justify-center bg-ink/10" aria-hidden="true">
    <span class="-rotate-[18deg] select-none rounded-2xl border-[3px] border-red-700/70 bg-white/35 font-display font-bold uppercase tracking-[0.25em] text-red-700/75 shadow-[0_0_0_6px_rgba(255,255,255,.25)] backdrop-blur-[1px] {{ ($large ?? false) ? 'px-10 py-4 text-6xl sm:text-8xl border-[5px]' : 'px-5 py-2 text-3xl sm:text-4xl' }}">{{ __('shop.certificate.sold_stamp') }}</span>
</div>
