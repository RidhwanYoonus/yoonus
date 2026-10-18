@extends('layouts.app')
@section('content')
@php use App\Support\Policies; @endphp
<div class="container-x grid gap-10 py-14 lg:grid-cols-[15rem_1fr]">
    <nav class="text-sm lg:sticky lg:top-24 lg:self-start" aria-label="{{ __('shop.policy.all') }}">
        <p class="eyebrow">{{ __('shop.policy.all') }}</p>
        <ul class="mt-4 space-y-2">
            @foreach (Policies::PAGES as $k => $page)
                <li><a href="{{ route('policy.show', $page['slug']) }}" @class(['block rounded-lg px-3 py-2 hover:bg-sand', 'bg-sand font-medium text-ink' => $k === $key, 'text-muted' => $k !== $key])>{{ Policies::title($k) }}</a></li>
            @endforeach
        </ul>
    </nav>
    <article class="max-w-3xl">
        <h1 class="font-display text-4xl">{{ $title }}</h1>
        <p class="mt-2 text-sm text-muted">{{ __('shop.policy.updated', ['date' => \Illuminate\Support\Carbon::parse(Policies::UPDATED)->translatedFormat('j F Y')]) }}</p>
        @if ($key === 'refund')
            <p class="mt-6 rounded-2xl border border-line bg-sand px-5 py-4 font-medium">{{ __('shop.policy.final_sale') }}</p>
        @endif
        <div class="policy mt-8 space-y-8 leading-relaxed">
            @if ($custom)
                {!! $custom !!}
            @else
                @foreach ($sections as $i => [$heading, $lines])
                    <section>
                        <h2 class="font-display text-2xl">{{ $i + 1 }}. {{ $heading }}</h2>
                        @php $list = []; @endphp
                        @foreach ($lines as $line)
                            @if (str_starts_with($line, '- '))
                                @php $list[] = substr($line, 2); @endphp
                            @else
                                @if ($list)<ul class="mt-3 list-disc space-y-1.5 ps-6">@foreach ($list as $li)<li>{{ $li }}</li>@endforeach</ul>@php $list = []; @endphp @endif
                                <p class="mt-3">{{ $line }}</p>
                            @endif
                        @endforeach
                        @if ($list)<ul class="mt-3 list-disc space-y-1.5 ps-6">@foreach ($list as $li)<li>{{ $li }}</li>@endforeach</ul>@endif
                    </section>
                @endforeach
            @endif
        </div>
    </article>
</div>
@endsection
