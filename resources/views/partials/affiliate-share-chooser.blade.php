{{-- Affiliates with an own price page pick which link goes into a post: their page (with their mark-up) or normal shop prices. --}}
@once
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('affShare', (white) => ({
        white, ask: false, pending: null, done: null, remember: false,
        share(action, texts, key) {
            const saved = sessionStorage.getItem('aff_link_choice');
            if (! this.white) return this.run(action, texts.r, key);
            if (saved) return this.run(action, texts[saved], key);
            this.pending = { action, texts, key }; this.ask = true;
        },
        choose(mode) {
            if (this.remember) sessionStorage.setItem('aff_link_choice', mode);
            const p = this.pending; this.ask = false; this.pending = null;
            if (p) this.run(p.action, p.texts[mode], p.key);
        },
        forget() { sessionStorage.removeItem('aff_link_choice'); this.done = 'forget'; setTimeout(() => this.done = null, 1500); },
        run(action, text, key) {
            if (action === 'wa') { window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank', 'noopener'); return; }
            navigator.clipboard?.writeText(text);
            this.done = key; setTimeout(() => this.done = null, 1500);
        },
        hasChoice() { return !! sessionStorage.getItem('aff_link_choice'); },
    }));
});
</script>
@endonce
<template x-teleport="body">
    <div x-show="ask" x-cloak x-transition.opacity class="fixed inset-0 z-[80] flex items-end justify-center bg-black/50 p-4 sm:items-center" @keydown.escape.window="ask = false" @click.self="ask = false">
        <div class="card w-full max-w-md p-5" role="dialog" aria-modal="true" aria-labelledby="share-choice-title">
            <h3 id="share-choice-title" class="font-display text-xl">{{ __('shop.affiliate.share_which') }}</h3>
            <div class="mt-4 grid gap-3">
                <button type="button" class="rounded-xl border border-gold-400 bg-gold-300/15 p-4 text-start transition hover:border-gold-600" @click="choose('w')">
                    <span class="block font-medium">{{ __('shop.affiliate.share_white') }}</span>
                    <span class="mt-0.5 block text-xs text-muted">{{ __('shop.affiliate.share_white_note') }}</span>
                </button>
                <button type="button" class="rounded-xl border border-line p-4 text-start transition hover:border-gold-400" @click="choose('r')">
                    <span class="block font-medium">{{ __('shop.affiliate.share_normal') }}</span>
                    <span class="mt-0.5 block text-xs text-muted">{{ __('shop.affiliate.share_normal_note') }}</span>
                </button>
            </div>
            <label class="mt-4 flex items-center gap-2 text-sm"><input type="checkbox" x-model="remember" class="accent-gold-600"> {{ __('shop.affiliate.share_remember') }}</label>
            <button type="button" class="btn-outline mt-4 w-full" @click="ask = false">{{ __('shop.affiliate.cancel') }}</button>
        </div>
    </div>
</template>
