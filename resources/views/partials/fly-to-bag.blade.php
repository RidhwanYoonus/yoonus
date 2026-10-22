{{-- "Add to bag": the product photo flies into the bag icon, the red count pops, then the bag page opens. --}}
<script>
(() => {
    const visible = el => el && el.offsetParent !== null && el.getBoundingClientRect().width > 0;
    document.addEventListener('submit', e => {
        const form = e.target.closest('form[data-add-to-bag]');
        if (! form || form.dataset.flying) return;
        const scope = form.closest('[data-fly-scope]') || document.querySelector('[data-fly-scope]');
        const img = scope && [...scope.querySelectorAll('img')].find(visible);
        const bag = [...document.querySelectorAll('[data-cart-icon]')].find(visible);
        if (! img || ! bag || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        e.preventDefault();
        form.dataset.flying = '1';
        form.querySelectorAll('button').forEach(b => b.disabled = true);

        // Photo scrolled out of sight (e.g. the sticky button on phones): start from the button that was tapped.
        let from = img.getBoundingClientRect();
        const btn = e.submitter || form.querySelector('button');
        if ((from.bottom < 40 || from.top > innerHeight - 40) && btn) from = btn.getBoundingClientRect();
        const to = bag.getBoundingClientRect();
        const size = Math.max(64, Math.min(from.width, from.height, 220));
        const ghost = img.cloneNode();
        Object.assign(ghost.style, { position: 'fixed', zIndex: 9999, pointerEvents: 'none', objectFit: 'cover', borderRadius: '18px',
            width: size + 'px', height: size + 'px', left: (from.left + (from.width - size) / 2) + 'px', top: (from.top + (from.height - size) / 2) + 'px',
            boxShadow: '0 18px 40px -12px rgba(122,95,34,.55)' });
        ghost.removeAttribute('x-show'); ghost.removeAttribute('loading');
        document.body.appendChild(ghost);
        const dx = to.left + to.width / 2 - (from.left + from.width / 2), dy = to.top + to.height / 2 - (from.top + from.height / 2);
        const fly = ghost.animate([
            { transform: 'translate(0,0) scale(1)', opacity: 1 },
            { transform: `translate(${dx * 0.5}px, ${dy * 0.5 - 90}px) scale(.55)`, opacity: .95, offset: .55 },
            { transform: `translate(${dx}px, ${dy}px) scale(.08)`, opacity: .35 },
        ], { duration: 750, easing: 'cubic-bezier(.45,.05,.35,1)', fill: 'forwards' });

        let sent = false;
        const go = () => { if (! sent) { sent = true; HTMLFormElement.prototype.submit.call(form); } };
        fly.onfinish = () => {
            ghost.remove();
            bag.animate([{ transform: 'scale(1)' }, { transform: 'scale(1.3)' }, { transform: 'scale(1)' }], { duration: 300 });
            let badge = bag.querySelector('[data-cart-count]');
            if (badge) badge.textContent = (parseInt(badge.textContent, 10) || 0) + (parseInt(form.querySelector('[name=qty]')?.value, 10) || 1);
            setTimeout(go, 280);
        };
        setTimeout(go, 1600);   // never leave the customer waiting
    });
})();
</script>
