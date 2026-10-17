@php
    use App\Support\Settings;
    $event = session('analytics_event');
@endphp
{{-- Every tag below is switched on from Admin → Settings → Marketing; no code edits needed. --}}
<script>window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);}</script>
@if ($gtm = Settings::get('gtm_id'))
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',@js($gtm));</script>
@endif
@if ($ga = Settings::get('ga4_id'))
<script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($ga) }}"></script>
<script>gtag('js', new Date()); gtag('config', @js($ga), { currency: 'AED' });</script>
@endif
@if ($fb = Settings::get('meta_pixel_id'))
<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init', @js($fb));fbq('track','PageView');</script>
@endif
@if ($tt = Settings::get('tiktok_pixel_id'))
<script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};ttq.load(@js($tt));ttq.page();}(window,document,'ttq');</script>
@endif
@if ($snap = Settings::get('snap_pixel_id'))
<script>(function(e,t,n){if(e.snaptr)return;var a=e.snaptr=function(){a.handleRequest?a.handleRequest.apply(a,arguments):a.queue.push(arguments)};a.queue=[];var s='script';var r=t.createElement(s);r.async=!0;r.src=n;var u=t.getElementsByTagName(s)[0];u.parentNode.insertBefore(r,u);})(window,document,'https://sc-static.net/scevent.min.js');snaptr('init', @js($snap));snaptr('track','PAGE_VIEW');</script>
@endif
@if ($os = Settings::get('onesignal_app_id'))
<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script>window.OneSignalDeferred = window.OneSignalDeferred || []; OneSignalDeferred.push(async function(OneSignal) { await OneSignal.init({ appId: @js($os) }); });</script>
@endif
<script>
    // One helper fans each ecommerce event out to every enabled platform.
    window.shopTrack = function (name, d) {
        const item = { item_id: d.sku, item_name: d.item_name, price: d.value, quantity: d.qty || 1 };
        dataLayer.push({ event: name, ecommerce: { currency: 'AED', value: d.value, transaction_id: d.transaction_id, items: d.items || [item] } });
        if (window.gtag) gtag('event', name, { currency: 'AED', value: d.value, transaction_id: d.transaction_id, items: d.items || [item] });
        const fbMap = { view_item: 'ViewContent', add_to_cart: 'AddToCart', begin_checkout: 'InitiateCheckout', purchase: 'Purchase' };
        if (window.fbq && fbMap[name]) fbq('track', fbMap[name], { currency: 'AED', value: d.value, content_ids: [d.sku].filter(Boolean), content_type: 'product' }, d.transaction_id ? { eventID: d.transaction_id } : {});
        const ttMap = { view_item: 'ViewContent', add_to_cart: 'AddToCart', begin_checkout: 'InitiateCheckout', purchase: 'CompletePayment' };
        if (window.ttq && ttMap[name]) ttq.track(ttMap[name], { currency: 'AED', value: d.value, content_id: d.sku });
        const snapMap = { view_item: 'VIEW_CONTENT', add_to_cart: 'ADD_CART', begin_checkout: 'START_CHECKOUT', purchase: 'PURCHASE' };
        if (window.snaptr && snapMap[name]) snaptr('track', snapMap[name], { currency: 'AED', price: d.value, transaction_id: d.transaction_id, item_ids: [d.sku].filter(Boolean) });
    };
    @if ($event)
        document.addEventListener('DOMContentLoaded', () => shopTrack(@js($event['name']), @js($event)));
    @endif
</script>
