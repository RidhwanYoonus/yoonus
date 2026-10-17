@php use App\Support\Settings; @endphp
<footer class="mt-24 bg-onyx text-ivory/80">
    <div class="container-x grid gap-10 py-14 md:grid-cols-4">
        <div class="md:col-span-2">
            <p class="font-display text-2xl text-gold-300">{{ \App\Support\Seo::storeName() }}</p>
            <p class="mt-3 max-w-md text-sm text-ivory/60">{{ __('shop.footer.about') }}</p>
            <form method="POST" action="{{ route('newsletter.subscribe') }}" class="mt-6 flex max-w-md gap-2">
                @csrf
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                <input type="email" name="email" required placeholder="{{ __('shop.newsletter.placeholder') }}" class="input flex-1 rounded-full border-ivory/20 bg-white/5 text-ivory" aria-label="{{ __('shop.newsletter.placeholder') }}">
                <button class="btn-gold">{{ __('shop.newsletter.subscribe') }}</button>
            </form>
        </div>
        <div class="text-sm">
            <p class="eyebrow text-gold-400">{{ __('shop.footer.help') }}</p>
            <ul class="mt-4 space-y-2">
                <li><a href="{{ route('order.track') }}" class="hover:text-gold-300">{{ __('shop.nav.track') }}</a></li>
                <li><a href="{{ route('gift-cards') }}" class="hover:text-gold-300">{{ __('shop.nav.gift_cards') }}</a></li>
                <li><a href="{{ route('compare') }}" class="hover:text-gold-300">{{ __('shop.nav.compare') }}</a></li>
                <li><a href="{{ \App\Messaging\WhatsApp::link(Settings::get('whatsapp_number'), __('shop.whatsapp.hello')) }}" target="_blank" rel="noopener" class="hover:text-gold-300">WhatsApp</a></li>
            </ul>
        </div>
        <div class="text-sm">
            <p class="eyebrow text-gold-400">{{ __('shop.footer.visit') }}</p>
            <p class="mt-4 text-ivory/60">{{ Settings::get('store_address') }}</p>
            <p class="mt-2 text-ivory/60" dir="ltr">{{ Settings::get('store_phone') }}</p>
            <p class="mt-2 text-ivory/60">{{ __('shop.footer.trn') }}: <span dir="ltr">{{ Settings::get('store_trn') }}</span></p>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="container-x flex flex-wrap items-center justify-between gap-2 py-5 text-xs text-ivory/50">
            <p>© {{ date('Y') }} {{ Settings::get('store_legal_name') }}. {{ __('shop.footer.vat_note') }}</p>
            <p>{{ __('shop.footer.payments') }}</p>
        </div>
    </div>
</footer>
