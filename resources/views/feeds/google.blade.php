{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
<channel>
  <title>{{ \App\Support\Settings::get('store_name') }}</title>
  <link>{{ url('/') }}</link>
  <description>{{ \App\Support\Settings::get('store_name') }} product feed (AED, VAT inclusive)</description>
@foreach ($items as $i)
  <item>
    <g:id>{{ $i['id'] }}</g:id>
    <g:title>{{ $i['title'] }}</g:title>
    <g:description>{{ $i['description'] }}</g:description>
    <g:link>{{ $i['link'] }}</g:link>
    <g:image_link>{{ $i['image'] }}</g:image_link>
@foreach ($i['extra_images'] as $img)
    <g:additional_image_link>{{ $img }}</g:additional_image_link>
@endforeach
    <g:availability>{{ $i['availability'] }}</g:availability>
    <g:price>{{ $i['price'] }}</g:price>
@if ($i['sale_price'])
    <g:sale_price>{{ $i['sale_price'] }}</g:sale_price>
@endif
    <g:condition>new</g:condition>
    <g:brand>{{ $i['brand'] }}</g:brand>
    <g:identifier_exists>no</g:identifier_exists>
    <g:google_product_category>188</g:google_product_category>
    <g:product_type>{{ $i['product_type'] }}</g:product_type>
@if ($i['material'])
    <g:material>{{ $i['material'] }}</g:material>
@endif
    <g:product_detail>
      <g:attribute_name>Weight</g:attribute_name>
      <g:attribute_value>{{ $i['weight'] }}</g:attribute_value>
    </g:product_detail>
  </item>
@endforeach
</channel>
</rss>
