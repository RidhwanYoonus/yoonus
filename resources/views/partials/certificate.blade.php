@php
    $lab = $product->certificateLab;
    $verify = $lab?->verifyUrl($product->certificate_number);
    $file = $product->certificate_file ? \Illuminate\Support\Facades\Storage::disk('public')->url($product->certificate_file) : null;
    $specs = array_filter([
        'carat' => filled($product->diamond_carat) ? rtrim(rtrim((string) $product->diamond_carat, '0'), '.').' ct' : null,
        'shape' => $product->diamond_shape, 'color' => $product->diamond_color,
        'clarity' => $product->diamond_clarity, 'cut' => $product->diamond_cut,
    ], 'filled');
@endphp
<div class="mt-6 card overflow-hidden">
    <div class="flex items-center justify-between gap-3 border-b border-line bg-sand/60 px-5 py-3">
        <div class="flex flex-wrap items-center gap-2">
            <p class="text-xs font-medium uppercase tracking-wider text-muted">{{ __('shop.certificate.title') }}</p>
            @if ($origin = $product->diamondOriginLabel())
                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $product->diamond_origin === 'natural' ? 'bg-emerald-50 text-emerald-800' : 'bg-sky-50 text-sky-800' }}">{{ $origin }}</span>
            @endif
        </div>
        @if ($lab)
            <span class="inline-flex items-center gap-2 text-sm font-medium">
                @if ($lab->logoUrl())<img src="{{ $lab->logoUrl() }}" alt="{{ $lab->label() }}" class="h-6 w-auto max-w-[5rem] object-contain" loading="lazy">@endif
                <span title="{{ $lab->name }}">{{ $lab->label() }}</span>
            </span>
        @endif
    </div>
    <dl class="grid grid-cols-2 gap-x-6 gap-y-2 px-5 py-4 text-sm sm:grid-cols-3">
        @if ($product->certificate_number)
            <div><dt class="text-xs text-muted">{{ __('shop.certificate.number') }}</dt><dd class="font-medium" dir="ltr">{{ $product->certificate_number }}</dd></div>
        @endif
        @foreach ($specs as $key => $value)
            <div><dt class="text-xs text-muted">{{ __('shop.certificate.'.$key) }}</dt><dd class="font-medium" dir="ltr">{{ $value }}</dd></div>
        @endforeach
    </dl>
    @if ($verify || $file)
        <div class="flex flex-wrap gap-x-5 gap-y-2 border-t border-line px-5 py-3 text-sm">
            @if ($verify)<a href="{{ $verify }}" target="_blank" rel="noopener nofollow" class="text-gold-700 underline">{{ __('shop.certificate.verify') }}</a>@endif
            @if ($file)<a href="{{ $file }}" target="_blank" rel="noopener" class="text-gold-700 underline">{{ __('shop.certificate.view') }}</a>@endif
        </div>
    @endif
</div>
