{{-- One "in use" mark-up value with the admin's maximum (0 = no limit). --}}
@php($maxValue = (float) $affiliate->{$max})
<span class="block text-xs text-muted">{{ $maxValue > 0 ? __('shop.affiliate.max', ['max' => $unit === '%' ? rtrim(rtrim(number_format($maxValue, 2, '.', ''), '0'), '.').'%' : 'AED '.rtrim(rtrim(number_format($maxValue, 2, '.', ''), '0'), '.')]) : __('shop.affiliate.no_limit') }}</span>
<span class="flex items-center gap-2" dir="ltr">
    @if ($unit !== '%')AED @endif
    <input name="{{ $name }}" type="number" step="0.01" min="0" @if ($maxValue > 0) max="{{ $maxValue }}" @endif value="{{ old($name, (float) $affiliate->{$name}) }}" class="input">
    @if ($unit === '%')% @endif
</span>
