@php
    use App\Support\Settings;
    $rtl = app()->getLocale() === 'ar';
    $seo = $seo ?? \App\Support\Seo::page(Settings::get('store_name'));
    $otherLocale = $rtl ? 'en' : 'ar';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $seo['title'] }}</title>
    <meta name="description" content="{{ $seo['description'] }}">
    <link rel="canonical" href="{{ $seo['canonical'] }}">
    <link rel="alternate" hreflang="en" href="{{ $seo['canonical'] }}?lang=en">
    <link rel="alternate" hreflang="ar" href="{{ $seo['canonical'] }}?lang=ar">
    <link rel="alternate" hreflang="x-default" href="{{ $seo['canonical'] }}">

    <meta property="og:type" content="{{ $seo['type'] }}">
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta property="og:description" content="{{ $seo['description'] }}">
    <meta property="og:url" content="{{ $seo['canonical'] }}">
    <meta property="og:image" content="{{ url($seo['image']) }}">
    <meta property="og:locale" content="{{ $rtl ? 'ar_AE' : 'en_AE' }}">
    <meta property="og:site_name" content="{{ \App\Support\Seo::storeName() }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seo['title'] }}">
    <meta name="twitter:description" content="{{ $seo['description'] }}">
    <meta name="twitter:image" content="{{ url($seo['image']) }}">
    @stack('meta')

    @foreach ($seo['json_ld'] as $schema)
        <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endforeach

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @if ($rtl)
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    @else
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @endif
    {{-- Apply the saved (or system) colour mode before first paint, so there is no white flash. --}}
    <script>try{var t=localStorage.getItem('theme');if(t==='dark'||(!t&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark')}catch(e){}</script>
    <meta name="color-scheme" content="light dark">
    @if ($favicon = Settings::imageUrl('brand_favicon'))
        <link rel="icon" href="{{ $favicon }}">
        <link rel="apple-touch-icon" href="{{ $favicon }}">
    @else
        <link rel="icon" href="{{ asset('favicon.ico') }}">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.pixels')
    {{-- Faster page changes: the browser fetches a shop page when the shopper hovers / starts tapping its link. --}}
    <script type="speculationrules">{"prefetch":[{"source":"document","where":{"and":[{"href_matches":"/*"},{"not":{"href_matches":["/cart/*","/checkout*","/logout","/admin*","/affiliate*","/orders/*","/payments/*","/w/*","/l/*","/*\\?*aff=*"]}},{"not":{"selector_matches":"[download],[target=_blank],[rel~=nofollow]"}}]},"eagerness":"moderate"}]}</script>
</head>
@php($focus = $focus ?? false)
<body class="min-h-screen flex flex-col {{ $focus ? 'pb-24' : 'pb-16' }} lg:pb-0">
    @if (\App\Support\Impersonation::active())
        {{-- Admin looking in as an affiliate / customer (only the admin sees this bar). --}}
        <div class="sticky top-0 z-50 flex flex-wrap items-center justify-center gap-3 bg-red-700 px-4 py-2 text-sm text-white">
            <span>🔒 Admin view — logged in as <b>{{ auth()->user()?->name }}</b> ({{ auth()->user()?->email }}). They are not told; orders can't be placed.</span>
            <form method="POST" action="{{ route('impersonate.leave') }}">@csrf<button class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-red-700">Back to admin</button></form>
        </div>
    @endif
    @include('partials.rate-banner')
    @include('partials.header')
    @if (isset($affiliateContext) && $affiliateContext->whitePage && $affiliateContext->affiliate && \App\Support\Settings::bool('affiliate_banner', true))
        <div class="border-b border-line bg-sand/60 py-2 text-center text-xs text-muted">{{ __('shop.affiliate.shopping_with', ['name' => $affiliateContext->affiliate->name()]) }}</div>
    @endif

    @if (session('status'))
        <div class="container-x mt-4"><div class="rounded-xl border border-gold-300 bg-gold-300/20 px-4 py-3 text-sm" role="status">{{ session('status') }}</div></div>
    @endif
    @if ($errors->any())
        <div class="container-x mt-4"><div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
            @foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div></div>
    @endif

    <main class="flex-1">@yield('content')</main>

    @include('partials.footer')
    @if (Settings::bool('whatsapp_button') && Settings::get('whatsapp_number'))
        @include('partials.whatsapp-button', ['hideOnMobile' => $focus])
    @endif
    @unless ($focus)
        @include('partials.mobile-nav')
    @endunless
    @include('partials.fly-to-bag')
    @stack('scripts')
</body>
</html>
