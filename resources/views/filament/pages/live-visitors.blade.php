<x-filament-panels::page>
    <div wire:poll.10s>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(10rem,1fr));gap:1rem">
            @foreach ($this->stats() as $label => $value)
                <x-filament::section compact>
                    <p class="text-xs text-gray-500">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-semibold" style="display:flex;align-items:center;gap:.5rem">
                        @if ($loop->first)<span style="width:.625rem;height:.625rem;border-radius:9999px;background:#22c55e;box-shadow:0 0 0 4px rgba(34,197,94,.25)"></span>@endif
                        {{ $value }}
                    </p>
                </x-filament::section>
            @endforeach
        </div>
    </div>
    <div class="text-sm" style="display:flex;flex-wrap:wrap;align-items:center;gap:1rem">
        <label style="display:flex;align-items:center;gap:.5rem">Active in the last
            <x-filament::input.wrapper class="w-32">
                <x-filament::input.select wire:model.live="minutes">
                    <option value="2">2 minutes</option><option value="5">5 minutes</option><option value="15">15 minutes</option><option value="30">30 minutes</option><option value="60">1 hour</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>
        <label style="display:flex;align-items:center;gap:.5rem"><x-filament::input.checkbox wire:model.live="withStaff" /> Include admin & staff</label>
    </div>
    {{ $this->table }}
</x-filament-panels::page>
