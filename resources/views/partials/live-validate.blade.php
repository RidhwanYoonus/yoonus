{{-- Forms marked data-live-validate: a missing or wrong box is pointed out as soon as the customer leaves it, and on
     "send" every such box is marked and the page scrolls to the first one (instead of jumping to the top). --}}
<script>
(() => {
    const t = @js(['required' => __('shop.form.required'), 'email' => __('shop.form.email'), 'check' => __('shop.form.check'), 'photo' => __('shop.form.photo'),
        'emirates' => __('shop.form.emirates_check'), 'summary' => __('shop.auth.fix_above')]);
    const fields = 'input:not([type=hidden]):not([type=submit]):not([type=button]), select, textarea';
    const visible = el => el.offsetParent !== null && ! el.classList.contains('sr-only');
    // Where the message goes: under the box, or under its wrapper (password eye, photo buttons, tick boxes).
    const anchor = el => el.dataset.verifyGuard !== undefined ? el.parentElement
        : el.type === 'file' ? (el.closest('[x-data]') || el.parentElement)
        : (el.type === 'checkbox' || el.type === 'radio') ? (el.closest('label') || el)
        : (el.parentElement.classList.contains('relative') ? el.parentElement : el);
    const message = el => {
        const v = el.validity;
        if (v.valueMissing) return el.dataset.msg || (el.type === 'file' ? t.photo : t.required);
        if (v.typeMismatch && el.type === 'email') return t.email;
        return el.validationMessage || t.check;
    };
    const box = (el, create) => {
        const a = anchor(el);
        let p = a.nextElementSibling;
        if (p && p.dataset.liveError !== undefined) return p;
        if (! create) return null;
        p = Object.assign(document.createElement('p'), { className: 'mt-1 text-xs text-red-700' });
        p.dataset.liveError = ''; p.setAttribute('role', 'alert');
        a.after(p);
        return p;
    };
    const show = (el, text, warn = false) => {
        const p = box(el, true);
        p.textContent = text;
        p.className = 'mt-1 text-xs ' + (warn ? 'text-amber-700' : 'text-red-700');
        if (! warn) { el.setAttribute('aria-invalid', 'true'); el.classList.add('!border-red-400'); }
    };
    const clear = el => { box(el)?.remove(); el.removeAttribute('aria-invalid'); el.classList.remove('!border-red-400'); };
    const luhn = d => { let s = 0; for (let i = 0; i < d.length; i++) { let x = +d[d.length - 1 - i]; if (i % 2) { x *= 2; if (x > 9) x -= 9; } s += x; } return s % 10 === 0; };
    const check = el => {
        if (el.disabled || ! el.willValidate) return clear(el);
        if (! el.validity.valid) return show(el, message(el));
        clear(el);
        // Mobile number typed: remind right away that it needs the code (checkout).
        if (el.name === 'phone') {
            const guard = el.form?.querySelector('[data-verify-guard]');
            if (guard) setTimeout(() => check(guard), 50);
        }
        // Emirates ID: the last digit checks the others, so a typing mistake is caught (a warning only).
        if (el.name === 'id_number') {
            const type = el.form?.querySelector('[name=id_type]')?.value, d = el.value.replace(/\D/g, '');
            if (type === 'emirates_id' && d.length === 15 && d.startsWith('784') && ! luhn(d)) show(el, t.emirates, true);
        }
    };
    const forms = () => document.querySelectorAll('form[data-live-validate]');
    document.addEventListener('focusout', e => { if (e.target.matches?.(fields) && e.target.form?.dataset.liveValidate !== undefined && (e.target.value !== '' || e.target.dataset.touched)) check(e.target); });
    document.addEventListener('focusin', e => { if (e.target.matches?.(fields)) e.target.dataset.touched = '1'; });
    ['input', 'change'].forEach(ev => document.addEventListener(ev, e => { if (e.target.matches?.(fields) && e.target.getAttribute('aria-invalid')) check(e.target); }));
    // Leaving an empty required box also counts (focusout above skips untouched boxes only).
    document.addEventListener('invalid', e => {
        const form = e.target.form;
        if (! form || form.dataset.liveValidate === undefined) return;
        check(e.target);
        if (! form.dataset.reported) {
            form.dataset.reported = '1';
            setTimeout(() => { delete form.dataset.reported; delete form.dataset.hiddenFirst; }, 300);
            const first = [...form.querySelectorAll(fields)].find(f => f.willValidate && ! f.validity.valid);
            if (first && ! visible(first)) {
                // The browser cannot point at a hidden box (e.g. the mobile code): scroll to its message ourselves.
                form.dataset.hiddenFirst = '1';
                anchor(first).scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        if (form.dataset.hiddenFirst) e.preventDefault();   // and stop the browser jumping to a later box
    }, true);
    // Sent back by the server with errors: go to the first box with a message, not the top of the page.
    document.addEventListener('DOMContentLoaded', () => forms().forEach(form => {
        const err = form.querySelector('p.text-red-700:not([data-live-error])');
        if (err) setTimeout(() => err.scrollIntoView({ block: 'center' }), 150);
    }));
})();
</script>
