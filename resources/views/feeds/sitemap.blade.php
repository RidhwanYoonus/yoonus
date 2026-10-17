{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($urls as $u)
  <url>
    <loc>{{ $u['loc'] }}</loc>
    <xhtml:link rel="alternate" hreflang="en" href="{{ $u['loc'] }}?lang=en"/>
    <xhtml:link rel="alternate" hreflang="ar" href="{{ $u['loc'] }}?lang=ar"/>
@isset($u['lastmod'])
    <lastmod>{{ \Illuminate\Support\Carbon::parse($u['lastmod'])->toAtomString() }}</lastmod>
@endisset
    <changefreq>{{ $u['changefreq'] }}</changefreq>
    <priority>{{ $u['priority'] }}</priority>
  </url>
@endforeach
</urlset>
