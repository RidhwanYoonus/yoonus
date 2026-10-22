{{-- Carat · colour · clarity over a diamond piece's photo. $large for the product page. --}}
@if ($specs = $product->diamondSpecs())
    <div class="pointer-events-none absolute z-[2] {{ ($large ?? false) ? 'bottom-4 start-4' : 'bottom-3 start-3 hidden transition duration-300 sm:block lg:group-hover:opacity-0' }}">
        <div class="flex items-stretch divide-x divide-white/25 overflow-hidden rounded-xl bg-onyx/70 text-white shadow-lg ring-1 ring-white/15 backdrop-blur-md rtl:divide-x-reverse">
            <span class="flex items-center {{ ($large ?? false) ? 'px-3' : 'px-2' }}" aria-hidden="true">
                <svg class="{{ ($large ?? false) ? 'h-5 w-5' : 'h-3.5 w-3.5' }} text-gold-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 3h12l4 6-10 12L2 9l4-6z"/><path d="M2 9h20M12 21 8 9l4-6 4 6-4 12"/></svg>
            </span>
            @foreach ($specs as [$label, $value])
                <span class="flex flex-col justify-center {{ ($large ?? false) ? 'px-4 py-2' : 'px-2.5 py-1' }}">
                    <span class="{{ ($large ?? false) ? 'text-[0.625rem]' : 'text-[0.5rem]' }} uppercase tracking-[0.15em] text-white/60">{{ $label }}</span>
                    <span class="{{ ($large ?? false) ? 'text-base' : 'text-xs' }} font-semibold leading-tight" dir="ltr">{{ $value }}</span>
                </span>
            @endforeach
        </div>
    </div>
@elseif (($large ?? false) && $product->hasDiamond())
    {{-- Diamond piece without carat/colour/clarity entered: still say it on the photo. --}}
    <div class="pointer-events-none absolute bottom-4 start-4 z-[2]">
        <div class="flex items-center gap-2 rounded-xl bg-onyx/70 px-4 py-2.5 text-white shadow-lg ring-1 ring-white/15 backdrop-blur-md">
            <svg class="h-5 w-5 text-gold-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M6 3h12l4 6-10 12L2 9l4-6z"/><path d="M2 9h20M12 21 8 9l4-6 4 6-4 12"/></svg>
            <span class="text-sm font-semibold">{{ $product->diamondOriginLabel() ?? \App\Models\StoneType::label('Diamond') }}</span>
        </div>
    </div>
@endif
