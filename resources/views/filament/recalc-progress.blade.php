<x-filament-widgets::widget>
    <x-filament::section>
      <div wire:poll.{{ $batch && ! $batch->finished() ? "2s" : "10s" }}>
        @if (! $history)
            <p class="text-sm text-gray-500">No rate changes yet. Use <strong>Update rate</strong> on each karat (or <strong>Fetch rate</strong> for all at once); every product of that karat re-prices.</p>
        @else
            @php($pct = $batch ? $batch->progress() : 100)
            <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                <span>Last update: <strong>{{ $history->goldCategory->name }} → AED {{ $history->rate_per_gram }}/g</strong> · {{ $history->effective_from->diffForHumans() }} · {{ $history->changedBy?->name ?? 'system' }}</span>
                <span>{{ $products }} product(s) · {{ $batch && ! $batch->finished() ? 'recalculating…' : 'prices up to date' }} @if($batch?->failedJobs) · <span class="text-danger-600">{{ $batch->failedJobs }} failed</span>@endif</span>
            </div>
            <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div class="h-2 rounded-full bg-primary-500 transition-all" style="width: {{ $pct }}%"></div>
            </div>
        @endif
      </div>
    </x-filament::section>
</x-filament-widgets::widget>
