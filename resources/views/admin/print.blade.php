<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} — {{ $shop }}</title>
    <style>
        body { font: 12px/1.4 system-ui, -apple-system, "Segoe UI", Tahoma, Arial, sans-serif; color: #111; margin: 24px; background: #fff; }
        h1 { font-size: 18px; margin: 0; }
        h2 { font-size: 14px; margin: 20px 0 0; }
        .meta { color: #555; margin: 4px 0 16px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: start; vertical-align: top; }
        th { background: #f3f0e8; }
        tr:nth-child(even) td { background: #fafafa; }
        .bar { margin-bottom: 16px; display: flex; gap: 8px; }
        .bar button { padding: 6px 14px; border: 1px solid #b8860b; background: #b8860b; color: #fff; border-radius: 6px; cursor: pointer; }
        .bar button.alt { background: #fff; color: #333; border-color: #ccc; }
        @media print { .bar { display: none; } body { margin: 0; } thead { display: table-header-group; } tr { break-inside: avoid; } }
        @page { size: auto; margin: 12mm; }
    </style>
</head>
<body>
    <div class="bar"><button onclick="window.print()">Print</button><button class="alt" onclick="window.close()">Close</button></div>
    <h1>{{ $title }}</h1>
    <p class="meta">{{ $shop }} · printed {{ now()->format('d M Y H:i') }}</p>
    @foreach ($tables as $t)
        @if ($t['title'])<h2>{{ $t['title'] }}</h2>@endif
        <p class="meta">{{ count($t['rows']) }} {{ count($t['rows']) === 1 ? 'row' : 'rows' }}@if (count($t['rows']) >= 5000) (first 5,000 — use Export for all)@endif</p>
        <table>
            <thead><tr>@foreach ($t['headings'] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
            <tbody>
                @forelse ($t['rows'] as $row)<tr>@foreach ($row as $cell)<td dir="auto">{{ $cell }}</td>@endforeach</tr>
                @empty<tr><td colspan="{{ max(1, count($t['headings'])) }}">No rows.</td></tr>@endforelse
            </tbody>
        </table>
    @endforeach
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
