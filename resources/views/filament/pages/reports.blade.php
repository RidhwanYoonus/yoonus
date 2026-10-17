<x-filament-panels::page>
    @php($aed = fn ($v) => 'AED '.number_format((float) $v, 2))
    <div class="flex items-center gap-3">
        <label class="text-sm">Period</label>
        <x-filament::input.wrapper class="w-48">
            <x-filament::input.select wire:model.live="days">
                <option value="7">Last 7 days</option><option value="30">Last 30 days</option><option value="90">Last 90 days</option><option value="365">Last 12 months</option>
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>

    <div class="grid gap-4 md:grid-cols-4">
        @foreach ([['Revenue', $aed($kpis['revenue'])], ['Paid orders', $kpis['orders']], ['Average order', $aed($kpis['aov'])], ['VAT collected', $aed($kpis['vat'])],
                   ['Discounts given', $aed($kpis['discounts'])], ['Conversion (orders ÷ carts)', $kpis['conversion'].'%'], ['Abandoned carts', $kpis['abandoned_count'].' · '.$aed($kpis['abandoned_value'])], ['Recovered by reminders', $kpis['recovered']]] as [$label, $value])
            <x-filament::section><p class="text-xs text-gray-500">{{ $label }}</p><p class="mt-1 text-xl font-semibold">{{ $value }}</p></x-filament::section>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section heading="Top products">
            <table class="w-full text-sm"><thead><tr class="text-left text-gray-500"><th>Product</th><th>Karat</th><th class="text-right">Units</th><th class="text-right">Revenue</th></tr></thead>
                <tbody>@forelse ($products as $p)<tr class="border-t border-gray-100 dark:border-white/5"><td class="py-1.5">{{ $p->product_name }}</td><td>{{ $p->karat }}</td><td class="text-right">{{ $p->units }}</td><td class="text-right">{{ $aed($p->revenue) }}</td></tr>@empty<tr><td colspan="4" class="py-4 text-gray-500">No sales in this period.</td></tr>@endforelse</tbody>
            </table>
        </x-filament::section>
        <div class="space-y-6">
            <x-filament::section heading="Sales by category">
                <table class="w-full text-sm"><tbody>@foreach ($categories as $c)<tr class="border-t border-gray-100 dark:border-white/5"><td class="py-1.5">{{ $c->category }}</td><td class="text-right">{{ $c->units }} pcs</td><td class="text-right">{{ $aed($c->revenue) }}</td></tr>@endforeach</tbody></table>
            </x-filament::section>
            <x-filament::section heading="Sales by karat">
                <table class="w-full text-sm"><tbody>@foreach ($karats as $k)<tr class="border-t border-gray-100 dark:border-white/5"><td class="py-1.5">{{ $k->karat }}</td><td class="text-right">{{ number_format($k->grams, 3) }} g</td><td class="text-right">{{ $aed($k->revenue) }}</td></tr>@endforeach</tbody></table>
            </x-filament::section>
        </div>
    </div>

    <x-filament::section heading="Gold rate vs. sales">
        <p class="text-sm">Correlation between the 22K rate and daily revenue ({{ $days }} days):
            <strong>{{ $correlation === null ? 'not enough data yet' : $correlation }}</strong>
            @if ($correlation !== null)<span class="text-gray-500">— {{ $correlation <= -0.3 ? 'sales tend to rise when gold falls' : ($correlation >= 0.3 ? 'sales tend to rise with the rate' : 'little relationship') }}</span>@endif
        </p>
    </x-filament::section>
</x-filament-panels::page>
