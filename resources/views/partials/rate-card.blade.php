{{-- Gold-rate picture for affiliates: pick a design (or a background from Settings → Affiliates), then share or download. --}}
@php
    $tz = 'Asia/Dubai';
    $cardData = [
        'rates' => $rates->map(fn ($r) => ['k' => $r->name, 'v' => number_format((float) $r->rate_per_gram, 2)])->values(),
        'store' => ['en' => (string) \App\Support\Settings::get('store_name'), 'ar' => (string) (\App\Support\Settings::get('store_name_ar') ?: \App\Support\Settings::get('store_name'))],
        'date' => ['en' => now($tz)->locale('en')->isoFormat('dddd, D MMMM YYYY · h:mm A'), 'ar' => now($tz)->locale('ar')->isoFormat('dddd، D MMMM YYYY · h:mm A')],
        'links' => ['r' => $affiliate->referralLink(), 'w' => $affiliate->white_page_enabled ? $affiliate->whitePageLink() : null],
        'code' => $affiliate->code,
        'backgrounds' => $backgrounds,
        'text' => [
            'en' => ['title' => "TODAY'S GOLD RATE", 'per' => 'AED per gram', 'shop' => "Shop at today's price", 'code' => 'Code'],
            'ar' => ['title' => 'سعر الذهب اليوم', 'per' => 'درهم للغرام', 'shop' => 'تسوّق بسعر اليوم', 'code' => 'الرمز'],
        ],
        'lang' => app()->getLocale() === 'ar' ? 'ar' : 'en',
    ];
@endphp
@once
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('rateCard', (d) => ({
        d, design: d.backgrounds.length ? 'bg0' : 'onyx', size: 'square', lang: d.lang, link: d.links.w ? 'w' : 'r', busy: false, canShare: false,
        designs: [
            { id: 'onyx', name: 'Onyx' }, { id: 'classic', name: 'Ivory' }, { id: 'emerald', name: 'Emerald' }, { id: 'rose', name: 'Rose gold' }, { id: 'royal', name: 'Royal' },
        ],
        async init() {
            this.canShare = !! (navigator.canShare && navigator.canShare({ files: [new File(['x'], 'x.png', { type: 'image/png' })] }));
            try { await document.fonts.ready; } catch (e) {}
            this.$watch('design', () => this.draw()); this.$watch('size', () => this.draw());
            this.$watch('lang', () => this.draw()); this.$watch('link', () => this.draw());
            this.draw();
        },
        img(src) { return new Promise((ok, no) => { const i = new Image(); i.crossOrigin = 'anonymous'; i.onload = () => ok(i); i.onerror = no; i.src = src; }); },
        async draw() {
            const c = this.$refs.canvas, W = 1080, H = this.size === 'story' ? 1920 : 1350, x = c.getContext('2d'), t = d.text[this.lang], rtl = this.lang === 'ar';
            c.width = W; c.height = H; x.direction = rtl ? 'rtl' : 'ltr';
            const theme = {
                onyx: { bg: ['#0b0b0c', '#1d1a16'], ink: '#f5e7c4', soft: 'rgba(245,231,196,.65)', gold: ['#f7e3a3', '#b8873a'], row: 'rgba(255,255,255,.05)' },
                classic: { bg: ['#fbf8f1', '#efe4cf'], ink: '#2b2116', soft: 'rgba(43,33,22,.6)', gold: ['#c9a24c', '#8a6420'], row: 'rgba(255,255,255,.65)' },
                emerald: { bg: ['#0c3b2e', '#06231b'], ink: '#f4e6bf', soft: 'rgba(244,230,191,.7)', gold: ['#f3dc9a', '#c19a45'], row: 'rgba(255,255,255,.06)' },
                rose: { bg: ['#f8e9e4', '#e7c3b5'], ink: '#3d2420', soft: 'rgba(61,36,32,.6)', gold: ['#c98d76', '#8e5443'], row: 'rgba(255,255,255,.55)' },
                royal: { bg: ['#141a3a', '#3a1230'], ink: '#f6e8c6', soft: 'rgba(246,232,198,.7)', gold: ['#f6dc95', '#c4943c'], row: 'rgba(255,255,255,.06)' },
                photo: { ink: '#ffffff', soft: 'rgba(255,255,255,.8)', gold: ['#fbe7ac', '#d6a84d'], row: 'rgba(0,0,0,.35)' },
            }[this.design.startsWith('bg') ? 'photo' : this.design];

            // Background
            if (this.design.startsWith('bg')) {
                try {
                    const im = await this.img(d.backgrounds[+this.design.slice(2)]);
                    const s = Math.max(W / im.width, H / im.height);
                    x.drawImage(im, (W - im.width * s) / 2, (H - im.height * s) / 2, im.width * s, im.height * s);
                } catch (e) { x.fillStyle = '#111'; x.fillRect(0, 0, W, H); }
                const shade = x.createLinearGradient(0, 0, 0, H);
                shade.addColorStop(0, 'rgba(0,0,0,.55)'); shade.addColorStop(.45, 'rgba(0,0,0,.35)'); shade.addColorStop(1, 'rgba(0,0,0,.7)');
                x.fillStyle = shade; x.fillRect(0, 0, W, H);
            } else {
                const g = x.createRadialGradient(W / 2, H * .3, 50, W / 2, H / 2, H);
                g.addColorStop(0, theme.bg[0]); g.addColorStop(1, theme.bg[1]);
                x.fillStyle = g; x.fillRect(0, 0, W, H);
                // soft light sweep
                const sweep = x.createLinearGradient(0, 0, W, H);
                sweep.addColorStop(0, 'rgba(255,255,255,.08)'); sweep.addColorStop(.5, 'rgba(255,255,255,0)'); sweep.addColorStop(1, 'rgba(255,255,255,.05)');
                x.fillStyle = sweep; x.fillRect(0, 0, W, H);
            }
            const gold = x.createLinearGradient(0, 0, W, 0);
            gold.addColorStop(0, theme.gold[1]); gold.addColorStop(.5, theme.gold[0]); gold.addColorStop(1, theme.gold[1]);

            // Double frame and diamond ornament
            x.strokeStyle = gold; x.lineWidth = 3; x.strokeRect(40, 40, W - 80, H - 80);
            x.lineWidth = 1; x.strokeRect(56, 56, W - 112, H - 112);
            const orn = (cx, cy, r) => { x.beginPath(); x.moveTo(cx, cy - r); x.lineTo(cx + r * .8, cy); x.lineTo(cx, cy + r); x.lineTo(cx - r * .8, cy); x.closePath(); x.fillStyle = gold; x.fill(); };
            orn(W / 2, 56, 14);

            const font = (w, s) => `${w} ${s}px Inter, Cairo, 'Segoe UI', Tahoma, sans-serif`;
            const center = (txt, y, f, color, spacing = 0) => {
                x.font = f; x.fillStyle = color; x.textAlign = 'center';
                if ('letterSpacing' in x) x.letterSpacing = spacing + 'px';
                x.fillText(txt, W / 2, y);
                if ('letterSpacing' in x) x.letterSpacing = '0px';
            };
            const top = this.size === 'story' ? 230 : 150;
            center(d.store[this.lang].toUpperCase(), top, font(600, 34), theme.soft, rtl ? 0 : 8);
            center(t.title, top + 105, font(700, rtl ? 84 : 78), gold, rtl ? 0 : 4);
            center(d.date[this.lang], top + 165, font(400, 30), theme.soft);
            x.fillStyle = gold; x.fillRect(W / 2 - 90, top + 200, 180, 3);

            // Rate rows
            const rows = d.rates.slice(0, 6), rowH = this.size === 'story' ? 150 : 128, gap = 22;
            let y = top + 250 + (this.size === 'story' ? 60 : 0);
            const left = 130, right = W - 130;
            for (const r of rows) {
                x.fillStyle = theme.row;
                x.beginPath(); x.roundRect ? x.roundRect(left - 30, y, right - left + 60, rowH, 28) : x.rect(left - 30, y, right - left + 60, rowH); x.fill();
                x.strokeStyle = gold; x.lineWidth = 1.5; x.stroke();
                const mid = y + rowH / 2 + 18;
                x.font = font(800, 60); x.fillStyle = gold; x.textAlign = rtl ? 'right' : 'left';
                x.fillText(r.k, rtl ? right : left, mid);
                x.textAlign = rtl ? 'left' : 'right';
                x.font = font(700, 62); x.fillStyle = theme.ink;
                const price = 'AED ' + r.v;
                x.direction = 'ltr'; x.textAlign = rtl ? 'left' : 'right';
                x.fillText(price, rtl ? left : right, mid - 8);
                x.direction = rtl ? 'rtl' : 'ltr'; x.textAlign = rtl ? 'left' : 'right';
                x.font = font(400, 24); x.fillStyle = theme.soft;
                x.fillText(t.per, rtl ? left : right, mid + 30);
                y += rowH + gap;
            }

            // Footer: link and code
            const fy = H - (this.size === 'story' ? 260 : 175);
            center(t.shop, fy, font(600, 36), theme.ink);
            const link = (d.links[this.link] || d.links.r).replace(/^https?:\/\//, '');
            x.direction = 'ltr'; center(link, fy + 56, font(700, 38), gold); x.direction = rtl ? 'rtl' : 'ltr';   // a web address always reads left to right
            center(t.code + ': ' + d.code, fy + 104, font(500, 28), theme.soft, 2);
            this.$refs.preview.src = c.toDataURL('image/png');
        },
        blob() { return new Promise(r => this.$refs.canvas.toBlob(r, 'image/png')); },
        async download() {
            const b = await this.blob(); const a = document.createElement('a');
            a.href = URL.createObjectURL(b); a.download = 'gold-rate-' + new Date().toISOString().slice(0, 10) + '.png'; a.click();
        },
        async shareImage() {
            this.busy = true;
            try {
                const f = new File([await this.blob()], 'gold-rate.png', { type: 'image/png' });
                await navigator.share({ files: [f], text: (d.links[this.link] || d.links.r) });
            } catch (e) { if (e && e.name !== 'AbortError') this.download(); }
            this.busy = false;
        },
    }));
});
</script>
@endonce
<div class="card mt-4 p-5" x-data="rateCard(@js($cardData))">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div><p class="label">{{ __('shop.affiliate.rate_card') }}</p><p class="text-sm text-muted">{{ __('shop.affiliate.rate_card_note') }}</p></div>
    </div>
    <div class="mt-4 grid gap-6 md:grid-cols-[1fr_18rem]">
        <div class="flex justify-center rounded-2xl bg-sand/60 p-3">
            <img x-ref="preview" alt="{{ __('shop.affiliate.rate_card') }}" class="max-h-[34rem] w-auto rounded-xl shadow-lg">
            <canvas x-ref="canvas" class="hidden"></canvas>
        </div>
        <div class="space-y-4 text-sm">
            <div>
                <p class="label">{{ __('shop.affiliate.rate_card_design') }}</p>
                <div class="flex flex-wrap gap-2">
                    <template x-for="(b, i) in d.backgrounds" :key="'bg' + i">
                        <button type="button" class="h-12 w-12 overflow-hidden rounded-lg ring-2 transition" :class="design === 'bg' + i ? 'ring-gold-600' : 'ring-transparent'" @click="design = 'bg' + i">
                            <img :src="b" alt="" class="h-full w-full object-cover">
                        </button>
                    </template>
                    <template x-for="t in designs" :key="t.id">
                        <button type="button" class="rounded-full border px-3 py-1.5 text-xs transition" :class="design === t.id ? 'border-gold-600 bg-gold-600 text-white' : 'border-line hover:border-gold-400'" @click="design = t.id" x-text="t.name"></button>
                    </template>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <label><span class="label">{{ __('shop.affiliate.rate_card_size') }}</span>
                    <select x-model="size" class="input"><option value="square">{{ __('shop.affiliate.rate_card_post') }}</option><option value="story">{{ __('shop.affiliate.rate_card_story') }}</option></select></label>
                <label><span class="label">{{ __('shop.affiliate.rate_card_lang') }}</span>
                    <select x-model="lang" class="input"><option value="en">English</option><option value="ar">العربية</option></select></label>
            </div>
            <template x-if="d.links.w">
                <label class="block"><span class="label">{{ __('shop.affiliate.rate_card_link') }}</span>
                    <select x-model="link" class="input"><option value="w">{{ __('shop.affiliate.share_white') }}</option><option value="r">{{ __('shop.affiliate.share_normal') }}</option></select></label>
            </template>
            <div class="flex flex-wrap gap-2">
                <button type="button" x-show="canShare" class="btn-whatsapp px-4" @click="shareImage()" :disabled="busy">{{ __('shop.affiliate.rate_card_share') }}</button>
                <button type="button" class="btn-dark px-4" @click="download()">{{ __('shop.affiliate.rate_card_download') }}</button>
            </div>
            <p class="text-xs text-muted" x-show="!canShare">{{ __('shop.affiliate.rate_card_pc') }}</p>
        </div>
    </div>
</div>
