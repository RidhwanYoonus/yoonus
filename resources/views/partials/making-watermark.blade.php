{{-- Pieces with a very low making charge (Settings → Store & tax): a soft gold stamp across the photo while in stock. --}}
@if ($text = $product->makingWatermark())
    <div class="pointer-events-none absolute inset-0 z-[1] flex items-center justify-center" aria-hidden="true">
        <span class="-rotate-[16deg] select-none whitespace-nowrap rounded-2xl border-gold-500/70 bg-white/40 font-display font-bold uppercase text-[#7a5a14]/80 shadow-[0_0_0_5px_rgba(255,255,255,.25)] backdrop-blur-[1px] {{ ($large ?? false) ? 'border-[3px] px-5 py-2 text-2xl tracking-[0.14em] sm:px-8 sm:py-3 sm:text-5xl' : 'border-[2px] px-2 py-1 text-[0.7rem] tracking-[0.1em] sm:px-3 sm:py-1.5 sm:text-lg' }}">{{ $text }}</span>
    </div>
@endif
