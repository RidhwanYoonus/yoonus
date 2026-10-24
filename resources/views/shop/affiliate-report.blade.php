@extends('layouts.app')
@php use App\Pricing\Money; $fmt = fn (int $v) => Money::format($v); $r = 'shop.affiliate.report.'; @endphp

@section('content')
<div class="container-x py-12">
    <a href="{{ route('affiliate.dashboard') }}" class="text-sm text-muted hover:text-gold-700">← {{ __('shop.affiliate.title') }}</a>
    <h1 class="mt-2 font-display text-4xl">{{ __($r.'title') }}</h1>
    <p class="mt-2 text-sm text-muted">{{ __($r.'intro') }}</p>

    <nav class="mt-6 flex gap-2" aria-label="{{ __($r.'title') }}">
        @foreach ($canSeeCustomers ? ['transactions', 'customers'] : ['transactions'] as $t)
            <a href="{{ route('affiliate.report', ['tab' => $t]) }}" @class(['rounded-full px-4 py-2 text-sm font-medium', 'bg-gold-silver text-zinc-900' => $tab === $t, 'bg-sand text-muted hover:text-ink' => $tab !== $t])>
                {{ __($r.'tab_'.$t) }}@if ($t === 'customers') ({{ $customers->count() }})@endif</a>
        @endforeach
    </nav>

    @if ($tab === 'transactions')
        <form method="GET" class="card mt-6 grid gap-3 p-4 sm:grid-cols-5 sm:items-end">
            <input type="hidden" name="tab" value="transactions">
            <div><label class="label" for="from">{{ __($r.'from') }}</label><input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input"></div>
            <div><label class="label" for="to">{{ __($r.'to') }}</label><input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input"></div>
            <div><label class="label" for="type">{{ __($r.'type') }}</label>
                <select id="type" name="type" class="input"><option value="">{{ __($r.'all') }}</option>
                    @foreach (['commission', 'bonus', 'collected'] as $t)<option value="{{ $t }}" @selected(($filters['type'] ?? '') === $t)>{{ __($r.'type_'.$t) }}</option>@endforeach</select></div>
            <button class="btn-gold h-11">{{ __($r.'show') }}</button>
            <a href="{{ route('affiliate.report', array_filter(['tab' => 'transactions', 'from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null, 'type' => $filters['type'] ?? null, 'download' => 'transactions'])) }}" class="btn-outline h-11">⬇ {{ __($r.'download') }}</a>
        </form>

        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div class="card p-4"><p class="label">{{ __($r.'sum_commission') }}</p><p class="price mt-1 text-xl font-semibold">{{ $fmt($sums['commission']) }}</p></div>
            <div class="card p-4"><p class="label">{{ __($r.'sum_bonus') }}</p><p class="price mt-1 text-xl font-semibold">{{ $fmt($sums['bonus']) }}</p></div>
            <div class="card p-4"><p class="label">{{ __($r.'sum_collected') }}</p><p class="price mt-1 text-xl font-semibold">{{ $fmt($sums['collected']) }}</p></div>
        </div>

        <div class="card mt-4 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-sand/60 text-xs uppercase tracking-wider text-muted"><tr>
                    <th class="px-4 py-3 text-start">{{ __('shop.account.date') }}</th><th class="px-4 py-3 text-start">{{ __($r.'type') }}</th>
                    <th class="px-4 py-3 text-start">#</th><th class="px-4 py-3 text-start">{{ __($r.'customer') }}</th><th class="px-4 py-3 text-start">{{ __('shop.affiliate.item') }}</th>
                    <th class="px-4 py-3 text-start">{{ __($r.'details') }}</th><th class="px-4 py-3 text-start">{{ __('shop.account.status') }}</th><th class="px-4 py-3 text-end">{{ __($r.'amount') }}</th>
                </tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3">{{ $row['date']?->locale(app()->getLocale())->isoFormat('ll') }}</td>
                            <td class="px-4 py-3">{{ __($r.'type_'.$row['type']) }}</td>
                            <td class="px-4 py-3" dir="ltr">{{ $row['order'] ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $row['customer'] ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $row['item'] }}</td>
                            <td class="px-4 py-3 text-xs text-muted" dir="auto">{{ $row['details'] ?: '—' }}</td>
                            <td class="px-4 py-3"><span @class(['badge', 'bg-green-100 text-green-800' => in_array($row['status'], ['available', 'paid']), 'bg-red-50 text-red-700' => $row['status'] === 'void', 'bg-sand text-muted' => $row['status'] === 'pending'])>{{ __($r.'status_'.$row['status']) }}</span></td>
                            <td @class(['price whitespace-nowrap px-4 py-3 text-end font-medium', 'text-muted line-through' => $row['status'] === 'void'])>{{ $fmt($row['amount']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-muted">{{ __($r.'none') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-muted">{{ __($r.'customers_note') }}</p>
            <a href="{{ route('affiliate.report', ['tab' => 'customers', 'download' => 'customers']) }}" class="btn-outline h-10 px-4">⬇ {{ __($r.'download') }}</a>
        </div>
        @unless ($contacts)<p class="mt-3 rounded-xl bg-sand px-4 py-3 text-sm">{{ __($r.'contacts_hidden') }}</p>@endunless
        <div class="card mt-4 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-sand/60 text-xs uppercase tracking-wider text-muted"><tr>
                    <th class="px-4 py-3 text-start">{{ __($r.'customer') }}</th><th class="px-4 py-3 text-start">{{ __($r.'contact') }}</th>
                    <th class="px-4 py-3 text-start">{{ __($r.'linked_by') }}</th><th class="px-4 py-3 text-end">{{ __($r.'orders') }}</th>
                    <th class="px-4 py-3 text-end">{{ __($r.'spent') }}</th><th class="px-4 py-3 text-start">{{ __($r.'last_order') }}</th><th class="px-4 py-3"></th>
                </tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($customers as $c)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $c['name'] ?: '—' }}
                                @if ($c['offers_ok'])<span class="ms-1 badge bg-green-100 text-green-800" title="{{ __($r.'offers_ok_note') }}">{{ __($r.'offers_ok') }}</span>@endif</td>
                            <td class="px-4 py-3 text-xs" dir="ltr">@if ($c['phone'])<span class="block">{{ $c['phone'] }}</span>@endif @if ($c['email'])<span class="block text-muted">{{ $c['email'] }}</span>@endif</td>
                            <td class="px-4 py-3 text-xs">{{ __($r.'how_'.$c['how']) }}<span class="block text-muted">{{ $c['joined']?->locale(app()->getLocale())->isoFormat('ll') }}</span></td>
                            <td class="px-4 py-3 text-end">{{ $c['orders'] }}</td>
                            <td class="price whitespace-nowrap px-4 py-3 text-end">{{ $fmt($c['spent']) }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $c['last_order']?->locale(app()->getLocale())->isoFormat('ll') ?? '—' }}</td>
                            <td class="px-4 py-3 text-end">
                                @if ($contacts && $c['phone'])
                                    <a href="{{ \App\Messaging\WhatsApp::link($c['phone'], __($r.'wa_text', ['name' => \Illuminate\Support\Str::before($c['name'], ' '), 'url' => $affiliate->referralLink()])) }}" target="_blank" rel="noopener" class="btn-whatsapp h-9 whitespace-nowrap px-3 text-xs">WhatsApp</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-muted">{{ __($r.'no_customers') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
