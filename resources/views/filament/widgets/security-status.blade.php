<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-shield-exclamation" heading="Security check" description="Only admins see this. Fix the red items before the shop goes online.">
        <ul class="space-y-2 text-sm">
            @foreach ($problems as $p)
                <li class="flex gap-2">
                    <span @class(['mt-1.5 h-2 w-2 shrink-0 rounded-full', 'bg-danger-500' => $p['level'] === 'danger', 'bg-warning-500' => $p['level'] === 'warning', 'bg-gray-400' => $p['level'] === 'info'])></span>
                    <span>{{ $p['text'] }}</span>
                </li>
            @endforeach
        </ul>
        <p class="mt-3 text-xs text-gray-500"><a href="{{ \App\Filament\Resources\SecurityEventResource::getUrl() }}" class="underline">Open the security log →</a></p>
    </x-filament::section>
</x-filament-widgets::widget>
