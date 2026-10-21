<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-bolt" heading="Speed check" description="Only admins see this. These are server settings, not the shop's code — fix the red ones first." collapsible>
        <ul class="space-y-2 text-sm">
            @foreach ($problems as $p)
                <li class="flex gap-2">
                    <span @class(['mt-1.5 h-2 w-2 shrink-0 rounded-full', 'bg-danger-500' => $p['level'] === 'danger', 'bg-warning-500' => $p['level'] === 'warning', 'bg-gray-400' => $p['level'] === 'info'])></span>
                    <span>{{ $p['text'] }}</span>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
