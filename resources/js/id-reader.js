// Reads the Emirates ID / passport photo in the customer's own browser (Tesseract OCR; the photo is not sent anywhere
// for this) and fills ID number, expiry date and nationality. The customer checks the boxes before sending.

const ISO3 = {
    IND: 'IN', PAK: 'PK', BGD: 'BD', LKA: 'LK', NPL: 'NP', PHL: 'PH', CHN: 'CN', RUS: 'RU', GBR: 'GB', USA: 'US', SAU: 'SA', OMN: 'OM',
    KWT: 'KW', QAT: 'QA', BHR: 'BH', EGY: 'EG', JOR: 'JO', LBN: 'LB', IRQ: 'IQ', IRN: 'IR', TUR: 'TR', D: 'DE', DEU: 'DE', FRA: 'FR', ITA: 'IT',
    ESP: 'ES', NLD: 'NL', CHE: 'CH', SWE: 'SE', POL: 'PL', UKR: 'UA', KAZ: 'KZ', UZB: 'UZ', AFG: 'AF', IDN: 'ID', MYS: 'MY', SGP: 'SG',
    THA: 'TH', VNM: 'VN', KOR: 'KR', JPN: 'JP', AUS: 'AU', NZL: 'NZ', CAN: 'CA', BRA: 'BR', MEX: 'MX', ZAF: 'ZA', NGA: 'NG', KEN: 'KE',
    ETH: 'ET', SDN: 'SD', MAR: 'MA', DZA: 'DZ', TUN: 'TN', SYR: 'SY', YEM: 'YE', IRL: 'IE', BEL: 'BE', AUT: 'AT', PRT: 'PT', GRC: 'GR',
    ROU: 'RO', AZE: 'AZ', ARM: 'AM', GEO: 'GE', ARE: 'AE',
};

// MRZ letters often read wrongly as digits and the other way round.
const digits = s => s.replace(/[OQD]/g, '0').replace(/[IL|]/g, '1').replace(/Z/g, '2').replace(/S/g, '5').replace(/B/g, '8').replace(/G/g, '6');
const yymmdd = (s, future) => {
    s = digits(s);
    if (! /^\d{6}$/.test(s)) return null;
    let y = 2000 + +s.slice(0, 2);
    if (! future && y > new Date().getFullYear()) y -= 100;
    const m = +s.slice(2, 4), d = +s.slice(4, 6);
    if (m < 1 || m > 12 || d < 1 || d > 31) return null;
    return `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
};

/** @returns {{type?: string, number?: string, expiry?: string, country?: string, name?: string}} */
export function parseIdText(text) {
    const out = {};
    const raw = String(text || '').toUpperCase();
    const lines = raw.split(/\n+/).map(l => l.replace(/\s+/g, '').replace(/[«‹(]/g, '<')).filter(l => l.length >= 25 && (l.match(/</g) || []).length >= 2);

    // Passport (2 lines of 44): P<NNNSURNAME<<GIVEN / number, nationality, birth, sex, expiry.
    const p1 = lines.findIndex(l => /^P[A-Z<][A-Z<]{3}/.test(l) && l.includes('<<'));
    if (p1 >= 0 && lines[p1 + 1]) {
        const l2 = lines[p1 + 1];
        const number = l2.slice(0, 9).replace(/</g, '');
        if (/^[A-Z0-9]{6,9}$/.test(number)) {
            out.type = 'passport';
            out.number = number;
            const nat = l2.slice(10, 13).replace(/</g, '');
            if (ISO3[nat]) out.country = ISO3[nat];
            const exp = yymmdd(l2.slice(21, 27), true);
            if (exp) out.expiry = exp;
            const [sur, given] = lines[p1].slice(5).split('<<');
            out.name = [given, sur].filter(Boolean).map(n => n.replace(/</g, ' ').trim()).join(' ').replace(/\s+/g, ' ');
        }
    }

    // Emirates ID back (3 lines of 30): ILARE… with the 784 number / birth, sex, expiry / name.
    const e1 = lines.findIndex(l => /^[I1][LD]ARE/.test(l));
    if (e1 >= 0) {
        const m = digits(lines[e1].slice(5)).match(/784\d{12}/);
        if (m) out.number = m[0];
        const l2 = lines[e1 + 1];
        if (l2) {
            const exp = yymmdd(l2.slice(8, 14), true);
            if (exp) out.expiry = exp;
        }
        if (lines[e1 + 2]) {
            const [sur, given] = lines[e1 + 2].split('<<');
            out.name = [given, sur].filter(Boolean).map(n => n.replace(/</g, ' ').trim()).join(' ').replace(/\s+/g, ' ');
        }
        out.type = 'emirates_id';
    }

    // Emirates ID front: 784-1990-1234567-1 and dates such as 12/05/2027 (the latest one is the expiry).
    if (! out.number) {
        const m = raw.replace(/[OQ]/g, '0').match(/784[\s\-.]*\d{4}[\s\-.]*\d{7}[\s\-.]*\d/);
        if (m) { out.number = m[0].replace(/\D/g, ''); out.type = 'emirates_id'; }
    }
    if (out.number && out.type === 'emirates_id' && /^784\d{12}$/.test(out.number)) {
        const n = out.number;
        out.number = `${n.slice(0, 3)}-${n.slice(3, 7)}-${n.slice(7, 14)}-${n.slice(14)}`;
    }
    if (! out.expiry) {
        const today = new Date().toISOString().slice(0, 10);
        const dates = [...raw.matchAll(/\b(\d{2})[\/.\-](\d{2})[\/.\-](\d{4})\b/g)]
            .map(([, d, m, y]) => `${y}-${m}-${d}`).filter(d => /^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/.test(d) && d > today).sort();
        if (dates.length) out.expiry = dates[dates.length - 1];
    }

    return out;
}

let loading = null;
const loadTesseract = () => loading ??= new Promise((resolve, reject) => {
    if (window.Tesseract) return resolve(window.Tesseract);
    const s = Object.assign(document.createElement('script'), { src: 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js', async: true });
    s.onload = () => resolve(window.Tesseract);
    s.onerror = () => { loading = null; reject(new Error('OCR could not load')); };
    document.head.appendChild(s);
});

/** Reads one photo; resolves to parseIdText() of what it saw ({} when nothing useful). */
export async function readIdPhoto(file) {
    if (! file || ! file.type.startsWith('image/')) return {};
    const Tesseract = await loadTesseract();
    const { data } = await Tesseract.recognize(file, 'eng');

    return parseIdText(data.text);
}

/** Puts what was read into the empty ID boxes of the same form. Returns the boxes it filled. */
export function fillIdForm(form, found) {
    if (! form || ! found) return [];
    const filled = [];
    const set = (name, value) => {
        const el = form.querySelector(`[name="${name}"]`);
        if (! el || el.disabled || ! value) return;
        if (el.tagName === 'SELECT' && ! [...el.options].some(o => o.value === value && ! o.disabled)) return;
        if (el.tagName !== 'SELECT' && el.value.trim() !== '') return;   // never overwrite what the customer typed
        el.value = value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        filled.push(name);
    };
    set('id_type', found.type);
    set('id_number', found.number);
    if (found.expiry && found.expiry > new Date().toISOString().slice(0, 10)) set('id_expiry', found.expiry);
    set('id_country', found.country);

    return filled;
}
