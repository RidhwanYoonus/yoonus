@php($stale = \App\Models\GoldCategory::where('is_active', true)->get()->filter->isStale())
@if ($stale->isNotEmpty())
    <div class="bg-danger-600 px-4 py-2 text-center text-sm font-medium text-white">
        ⚠ Gold rate for {{ $stale->pluck('name')->join(', ') }} is older than {{ \App\Support\Settings::get('rate_stale_hours', 24) }} hours —
        <a href="{{ \App\Filament\Resources\GoldCategoryResource::getUrl() }}" class="underline">update it now</a>.
        @if (\App\Support\Settings::bool('block_checkout_when_stale')) Checkout is blocked until you do. @endif
    </div>
@endif
