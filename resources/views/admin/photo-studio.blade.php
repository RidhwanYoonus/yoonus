<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex">
<title>Photo studio · {{ $product->name }}</title>
<style>
    :root { --gold: #9a7a2c; --ink: #1c1917; --muted: #78716c; --line: #e7e5e4; --bg: #fafaf9; }
    * { box-sizing: border-box; }
    body { margin: 0; font: 15px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--ink); background: var(--bg); }
    .wrap { max-width: 1200px; margin: 0 auto; padding: 20px 16px 60px; }
    a { color: var(--gold); }
    h1 { font-size: 24px; margin: 6px 0 4px; }
    .muted { color: var(--muted); font-size: 13px; }
    .box { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 16px; }
    .bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin: 16px 0; }
    button { font: inherit; border-radius: 10px; border: 1px solid var(--line); background: #fff; padding: 9px 14px; cursor: pointer; }
    button.gold { background: linear-gradient(135deg, #b8963e, #e3cf9a); border-color: #b8963e; font-weight: 600; }
    button.dark { background: var(--ink); color: #fff; border-color: var(--ink); }
    button.on { outline: 2px solid var(--gold); }
    button:disabled { opacity: .5; cursor: default; }
    .grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); }
    .pair { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .pair > div { aspect-ratio: 1; border-radius: 10px; overflow: hidden; background: #f5f5f4; display: flex; align-items: center; justify-content: center; position: relative; }
    .pair img, .pair canvas { width: 100%; height: 100%; object-fit: contain; }
    .tag { position: absolute; top: 6px; left: 6px; font-size: 11px; background: rgba(0,0,0,.6); color: #fff; border-radius: 6px; padding: 1px 6px; }
    .actions { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
    .status { font-size: 13px; margin-top: 6px; min-height: 20px; }
    .ok { color: #15803d; } .err { color: #b91c1c; }
    .progress { height: 8px; background: #eee; border-radius: 9px; overflow: hidden; width: 260px; }
    .progress > div { height: 100%; background: var(--gold); width: 0; transition: width .2s; }
    .editor { position: fixed; inset: 0; background: rgba(0,0,0,.85); display: none; flex-direction: column; align-items: center; justify-content: center; z-index: 10; padding: 12px; }
    .editor.open { display: flex; }
    .editor canvas { max-width: 96vw; max-height: 72vh; background: repeating-conic-gradient(#ddd 0% 25%, #fff 0% 50%) 50% / 24px 24px; touch-action: none; border-radius: 8px; }
    .editor .tools { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 12px; color: #fff; }
    label.opt { display: inline-flex; gap: 6px; align-items: center; font-size: 14px; }
</style>
</head>
<body>
<div class="wrap">
    <a href="{{ $back }}">← Back to the product</a>
    <h1>Photo studio — {{ $product->name }}</h1>
    <p class="muted">The piece is cut out and placed on a clean white background with a soft shadow, light evened out, all photos the same square size.
        It runs in this browser with a free AI model, so there's no cost and the photos are not sent to any other company.
        The first time, the browser downloads the model (about 45–115 MB) once; after that it is quick. Works best in Chrome or Edge on a computer.</p>

    <div class="box">
        <div class="bar" style="margin:0">
            <button class="gold" id="all">✨ Make all photos white</button>
            <label class="opt"><input type="checkbox" id="optShadow" checked> Soft shadow</label>
            <label class="opt"><input type="checkbox" id="optLight" checked> Even out light</label>
            <label class="opt">Size <input type="range" id="optSize" min="55" max="92" value="74"> <span id="sizeVal">74%</span></label>
        </div>
        <div class="bar" style="margin:10px 0 0">
            <div class="progress"><div id="prog"></div></div>
            <span class="muted" id="modelStatus">The model loads when you start.</span>
        </div>
    </div>

    <div class="grid" style="margin-top:16px" id="grid">
        @forelse ($photos as $p)
            <div class="box" data-index="{{ $p['index'] }}" data-path="{{ $p['path'] }}" data-url="{{ $p['url'] }}" data-original="{{ $p['original'] }}">
                <div class="pair">
                    <div><span class="tag">Now in the shop</span><img src="{{ $p['url'] }}" alt="" data-now></div>
                    <div><span class="tag">New</span><canvas data-out width="10" height="10"></canvas></div>
                </div>
                <div class="status muted" data-status>{{ $p['original'] ? 'Already done in the studio. You can redo it or put the original back.' : 'Not done yet.' }}</div>
                <div class="actions">
                    <button data-run>✨ White background</button>
                    <button data-edit disabled>🖌 Fix edges</button>
                    <button class="dark" data-save disabled>Save to shop</button>
                    <button data-restore @unless ($p['original']) style="display:none" @endunless>↩ Put original back</button>
                </div>
            </div>
        @empty
            <p class="muted">This product has no photos yet. Add photos on the product page first, save, then come back here.</p>
        @endforelse
    </div>
</div>

<div class="editor" id="editor">
    <canvas id="edCanvas"></canvas>
    <div class="tools">
        <button id="edErase" class="on">Erase (remove background)</button>
        <button id="edKeep">Restore (keep part of the piece)</button>
        <label class="opt">Brush <input type="range" id="edSize" min="4" max="120" value="30"></label>
        <button class="gold" id="edDone">Done</button>
    </div>
    <p class="muted" style="color:#ccc;margin-top:8px">Paint over anything that should not be in the photo (hand, tray, table). Faded parts will be removed.</p>
</div>

<script type="module">
const TF = 'https://cdn.jsdelivr.net/npm/@huggingface/transformers@3.8.1';
const csrf = document.querySelector('meta[name=csrf-token]').content;
const routes = { save: @js(route('admin.photo-studio.save', $product)), restore: @js(route('admin.photo-studio.restore', $product)) };
const $ = (s, el = document) => el.querySelector(s);
const prog = $('#prog'), modelStatus = $('#modelStatus');
const opt = { shadow: () => $('#optShadow').checked, light: () => $('#optLight').checked, size: () => +$('#optSize').value / 100 };
$('#optSize').addEventListener('input', () => { $('#sizeVal').textContent = $('#optSize').value + '%'; items.forEach(it => it.mask && render(it)); });
['#optShadow', '#optLight'].forEach(id => $(id).addEventListener('change', () => items.forEach(it => it.mask && render(it))));

// ---- The AI model: BiRefNet (MIT licence, best for jewellery) on WebGPU; ORMBG (Apache-2.0, smaller) otherwise. ----
let segmenter = null, loading = null;
function progress(files) {
    return d => {
        if (d.status !== 'progress' || ! d.total) return;
        files[d.file] = [d.loaded, d.total];
        const all = Object.values(files), done = all.reduce((t, f) => t + f[0], 0), total = all.reduce((t, f) => t + f[1], 0);
        prog.style.width = Math.round(100 * done / total) + '%';
        modelStatus.textContent = 'Downloading the model once: ' + Math.round(done / 1048576) + ' of ' + Math.round(total / 1048576) + ' MB…';
    };
}
function model() {
    return loading ??= (async () => {
        modelStatus.textContent = 'Loading the model…';
        const T = await import(TF);
        T.env.allowLocalModels = false;
        const tries = [];
        if (navigator.gpu) tries.push(['onnx-community/BiRefNet_lite-ONNX', { device: 'webgpu', dtype: 'fp16' }]);
        tries.push(['onnx-community/ormbg-ONNX', { device: 'wasm', dtype: 'q8' }]);
        for (const [id, o] of tries) {
            try {
                segmenter = await T.pipeline('background-removal', id, { ...o, progress_callback: progress({}) });
                segmenter.T = T;
                prog.style.width = '100%';
                modelStatus.textContent = 'Model ready (' + (o.device === 'webgpu' ? 'best quality, using the graphics card' : 'standard quality') + ').';
                return segmenter;
            } catch (e) { console.warn('Model', id, e); }
        }
        loading = null;
        throw new Error('This browser could not run the model. Please use Chrome or Edge on a computer.');
    })();
}

// ---- Photos ----
const MAX = 2000, OUT = 1600;
const items = [...document.querySelectorAll('[data-index]')].map(el => ({ el, index: +el.dataset.index, path: el.dataset.path, url: el.dataset.url }));
const status = (it, text, cls = 'muted') => { const s = $('[data-status]', it.el); s.textContent = text; s.className = 'status ' + cls; };
const canvas = (w, h) => Object.assign(document.createElement('canvas'), { width: w, height: h });

async function loadPhoto(it) {
    const blob = await (await fetch(it.url, { cache: 'reload' })).blob();
    const bmp = await createImageBitmap(blob);
    const s = Math.min(1, MAX / Math.max(bmp.width, bmp.height));
    const c = canvas(Math.round(bmp.width * s), Math.round(bmp.height * s));
    c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
    return c;
}

async function run(it) {
    try {
        setBusy(it, true);
        status(it, 'Loading the model…');
        const seg = await model();
        status(it, 'Finding the piece…');
        it.src ??= await loadPhoto(it);
        const blob = await new Promise(r => it.src.toBlob(r, 'image/jpeg', 0.95));
        const [cut] = await seg(await seg.T.RawImage.fromBlob(blob));
        // The cut-out's transparency is the mask: kept as a canvas the brush can paint on.
        const cc = cut.toCanvas();
        const tmp = canvas(it.src.width, it.src.height), tx = tmp.getContext('2d');
        tx.drawImage(cc, 0, 0, tmp.width, tmp.height);
        const d = tx.getImageData(0, 0, tmp.width, tmp.height), px = d.data, single = cut.channels === 1;
        for (let i = 0; i < px.length; i += 4) {
            let a = single ? px[i] : px[i + 3];
            a = Math.max(0, Math.min(255, (a - 60) * 255 / 170));   // firmer edge: no coloured halo from the old background, thin chains still kept
            px[i] = px[i + 1] = px[i + 2] = 255; px[i + 3] = a;
        }
        tx.putImageData(d, 0, 0);
        it.mask = tmp;
        render(it);
        status(it, 'Done — check it, fix edges if needed, then Save to shop.', 'ok');
        $('[data-edit]', it.el).disabled = false;
        $('[data-save]', it.el).disabled = false;
    } catch (e) {
        status(it, e.message || 'Something went wrong.', 'err');
    } finally { setBusy(it, false); }
}

function setBusy(it, b) { $('[data-run]', it.el).disabled = b; }

// Light: brightness to a pleasant middle (measured on the piece only), a touch of contrast and colour. Gold stays gold.
function lut(meanLum) {
    const g = Math.max(0.72, Math.min(1.35, Math.log(0.56) / Math.log(Math.max(0.05, Math.min(0.95, meanLum)))));
    const t = new Uint8ClampedArray(256);
    for (let v = 0; v < 256; v++) t[v] = ((Math.pow(v / 255, g) * 255) - 128) * 1.07 + 128;
    return t;
}

function render(it) {
    const w = it.src.width, h = it.src.height;
    const cut = canvas(w, h), cx = cut.getContext('2d');
    cx.drawImage(it.src, 0, 0);
    const d = cx.getImageData(0, 0, w, h), px = d.data;
    const m = it.mask.getContext('2d').getImageData(0, 0, w, h).data;
    let sum = 0, n = 0, x0 = w, y0 = h, x1 = 0, y1 = 0;
    for (let y = 0, i = 0; y < h; y++) for (let x = 0; x < w; x++, i += 4) {
        const a = m[i + 3];
        if (a > 40) { if (x < x0) x0 = x; if (x > x1) x1 = x; if (y < y0) y0 = y; if (y > y1) y1 = y; }
        if (a > 200 && (i & 28) === 0) { sum += 0.299 * px[i] + 0.587 * px[i + 1] + 0.114 * px[i + 2]; n++; }
    }
    if (x1 <= x0 || y1 <= y0) { x0 = 0; y0 = 0; x1 = w - 1; y1 = h - 1; }
    const t = opt.light() && n ? lut(sum / n / 255) : null;
    for (let i = 0; i < px.length; i += 4) {
        if (t) {
            let r = t[px[i]], g = t[px[i + 1]], b = t[px[i + 2]];
            const l = 0.299 * r + 0.587 * g + 0.114 * b;   // a little more colour (×1.08)
            px[i] = l + (r - l) * 1.08; px[i + 1] = l + (g - l) * 1.08; px[i + 2] = l + (b - l) * 1.08;
        }
        px[i + 3] = m[i + 3];
    }
    cx.putImageData(d, 0, 0);

    // White square, the piece centred (a little low) with a soft warm shadow.
    const out = $('[data-out]', it.el);
    out.width = OUT; out.height = OUT;
    const o = out.getContext('2d');
    o.fillStyle = '#ffffff'; o.fillRect(0, 0, OUT, OUT);
    const bw = x1 - x0 + 1, bh = y1 - y0 + 1, s = Math.min(OUT * opt.size() / bw, OUT * opt.size() / bh);
    const dw = bw * s, dh = bh * s, dx = (OUT - dw) / 2, dy = (OUT - dh) / 2 + OUT * 0.02;
    o.save();
    if (opt.shadow()) { o.shadowColor = 'rgba(70, 52, 20, 0.22)'; o.shadowBlur = OUT * 0.035; o.shadowOffsetY = OUT * 0.014; }
    o.imageSmoothingQuality = 'high';
    o.drawImage(cut, x0, y0, bw, bh, dx, dy, dw, dh);
    o.restore();
    it.done = true;
}

async function save(it) {
    const btn = $('[data-save]', it.el); btn.disabled = true;
    status(it, 'Saving…');
    try {
        const blob = await new Promise(r => $('[data-out]', it.el).toBlob(r, 'image/jpeg', 0.92));
        const body = new FormData();
        body.append('index', it.index); body.append('path', it.path); body.append('file', blob, 'studio.jpg');
        const r = await fetch(routes.save, { method: 'POST', body, headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
        if (! r.ok) throw new Error((await r.json().catch(() => ({}))).message || 'Could not save (HTTP ' + r.status + ').');
        const j = await r.json();
        it.path = j.path; it.url = j.url; it.el.dataset.path = j.path;
        $('[data-now]', it.el).src = j.url;
        $('[data-restore]', it.el).style.display = '';
        status(it, '✓ Saved — this photo is now in the shop.', 'ok');
    } catch (e) { status(it, e.message, 'err'); btn.disabled = false; }
}

async function restore(it) {
    if (! confirm('Put the original photo back?')) return;
    const r = await fetch(routes.restore, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ index: it.index, path: it.path }) });
    if (! r.ok) return status(it, 'Could not put the original back. Reload the page and try again.', 'err');
    const j = await r.json();
    it.path = j.path; it.url = j.url; it.el.dataset.path = j.path; it.src = null; it.mask = null;
    $('[data-now]', it.el).src = j.url;
    $('[data-restore]', it.el).style.display = 'none';
    $('[data-edit]', it.el).disabled = true; $('[data-save]', it.el).disabled = true;
    status(it, 'Original photo is back in the shop.', 'ok');
}

// ---- Brush to fix edges: erase what should go (hand, tray), restore what the model removed by mistake. ----
const ed = { el: $('#editor'), c: $('#edCanvas'), it: null, mode: 'erase', down: false };
function edDraw() {
    const { c, it } = ed, x = c.getContext('2d');
    x.clearRect(0, 0, c.width, c.height);
    x.globalAlpha = 0.25; x.drawImage(it.src, 0, 0); x.globalAlpha = 1;
    const k = canvas(c.width, c.height), kx = k.getContext('2d');
    kx.drawImage(it.src, 0, 0); kx.globalCompositeOperation = 'destination-in'; kx.drawImage(it.mask, 0, 0);
    x.drawImage(k, 0, 0);
}
function edPaint(e) {
    const r = ed.c.getBoundingClientRect(), sx = ed.c.width / r.width;
    const x = (e.clientX - r.left) * sx, y = (e.clientY - r.top) * sx, rad = +$('#edSize').value * sx / 2;
    const m = ed.it.mask.getContext('2d');
    m.globalCompositeOperation = ed.mode === 'erase' ? 'destination-out' : 'source-over';
    m.fillStyle = '#fff'; m.beginPath(); m.arc(x, y, rad, 0, Math.PI * 2); m.fill();
    m.globalCompositeOperation = 'source-over';
    edDraw();
}
ed.c.addEventListener('pointerdown', e => { ed.down = true; ed.c.setPointerCapture(e.pointerId); edPaint(e); });
ed.c.addEventListener('pointermove', e => ed.down && edPaint(e));
ed.c.addEventListener('pointerup', () => ed.down = false);
$('#edErase').onclick = () => { ed.mode = 'erase'; $('#edErase').classList.add('on'); $('#edKeep').classList.remove('on'); };
$('#edKeep').onclick = () => { ed.mode = 'keep'; $('#edKeep').classList.add('on'); $('#edErase').classList.remove('on'); };
$('#edDone').onclick = () => { ed.el.classList.remove('open'); render(ed.it); $('[data-save]', ed.it.el).disabled = false; };

items.forEach(it => {
    $('[data-run]', it.el).onclick = () => run(it);
    $('[data-save]', it.el).onclick = () => save(it);
    $('[data-restore]', it.el).onclick = () => restore(it);
    $('[data-edit]', it.el).onclick = () => { ed.it = it; ed.c.width = it.src.width; ed.c.height = it.src.height; edDraw(); ed.el.classList.add('open'); };
});
$('#all').onclick = async () => {
    $('#all').disabled = true;
    for (const it of items) await run(it);
    $('#all').disabled = false;
};
</script>
</body>
</html>
