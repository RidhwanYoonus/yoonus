{{-- Google Maps pin + directions for a pickup branch. Needs no API key. --}}
<div class="overflow-hidden rounded-2xl border border-line bg-white">
    <iframe src="{{ $branch->mapEmbedUrl() }}" class="{{ $height ?? 'h-64' }} w-full border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="{{ __('shop.pickup.map_title', ['name' => $branch->localized('name')]) }}" allowfullscreen></iframe>
    <div class="flex flex-wrap items-center justify-between gap-3 p-4 text-sm">
        <div>
            <p class="font-medium">{{ $branch->localized('name') }}</p>
            <p class="text-muted">{{ $branch->address }}@unless (str_contains($branch->address, $branch->emirate)), {{ $branch->emirate }}@endunless</p>
            @if ($branch->opening_hours)<p class="text-xs text-muted">{{ __('shop.pickup.hours') }}: {{ $branch->opening_hours }}</p>@endif
        </div>
        <div class="flex gap-2">
            @if ($branch->phone)<a href="tel:{{ preg_replace('/[^+0-9]/', '', $branch->phone) }}" class="btn-outline px-4 py-2 text-xs">{{ __('shop.pickup.call') }}</a>@endif
            <a href="{{ $branch->directionsUrl() }}" target="_blank" rel="noopener" class="btn-dark px-4 py-2 text-xs">{{ __('shop.pickup.directions') }}</a>
        </div>
    </div>
</div>
