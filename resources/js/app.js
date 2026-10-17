import './bootstrap';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

Alpine.plugin(collapse);

// Countdown for campaign banners: x-data="countdown('2026-12-02T00:00:00Z')"
Alpine.data('countdown', (endsAt) => ({
    d: '00', h: '00', m: '00', s: '00', done: false,
    init() { this.tick(); this.timer = setInterval(() => this.tick(), 1000); },
    destroy() { clearInterval(this.timer); },
    tick() {
        const diff = Math.max(0, new Date(endsAt) - new Date());
        if (diff === 0) { this.done = true; clearInterval(this.timer); }
        const pad = (n) => String(Math.floor(n)).padStart(2, '0');
        this.d = pad(diff / 864e5); this.h = pad((diff / 36e5) % 24); this.m = pad((diff / 6e4) % 60); this.s = pad((diff / 1e3) % 60);
    },
}));

// Live gold rate ticker, refreshed from /api/rates every minute.
Alpine.data('rateTicker', (initial, url) => ({
    rates: initial.rates, updatedAt: initial.updated_at,
    init() { setInterval(() => this.refresh(), 60000); },
    async refresh() {
        try {
            const r = await fetch(url, { headers: { Accept: 'application/json' } });
            const data = await r.json();
            this.rates = data.rates; this.updatedAt = data.updated_at;
        } catch (e) { /* keep last known rates */ }
    },
}));

// Checkout: re-quote on every change so totals, VAT and BNPL eligibility stay correct.
Alpine.data('checkout', (cfg) => ({
    form: cfg.form, q: cfg.quote, loading: false, timer: null,
    init() {
        this.$watch('form', () => { clearTimeout(this.timer); this.timer = setTimeout(() => this.requote(), 350); });
    },
    async requote() {
        this.loading = true;
        try {
            const r = await fetch(cfg.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                body: JSON.stringify(this.form),
            });
            if (r.ok) {
                this.q = await r.json();
                if (!this.q.methods[this.form.payment_method]) this.form.payment_method = Object.keys(this.q.methods)[0];
            }
        } finally { this.loading = false; }
    },
}));

window.Alpine = Alpine;
Alpine.start();
