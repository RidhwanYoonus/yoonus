<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex">
<title>Photo studio · {{ $product->name }}</title>
@php($embed = request()->boolean('embed'))
@php($icon = fn ($d) => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$d.'</svg>')
@php($sparkle = $icon('<path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>'))
<style>
    :root { --gold: #a07f2f; --gold2: #d9bf7d; --ink: #1c1917; --muted: #78716c; --line: #ebe7e1; --bg: #f7f5f1; --card: #fff; }
    * { box-sizing: border-box; }
    body { margin: 0; font: 15px/1.5 "Segoe UI", system-ui, -apple-system, Roboto, sans-serif; color: var(--ink); background: var(--bg); -webkit-font-smoothing: antialiased; }
    a { color: var(--gold); text-decoration: none; }
    .top { position: sticky; top: 0; z-index: 5; background: rgba(255,255,255,.92); backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); }
    .top-in { max-width: 1180px; margin: 0 auto; padding: 10px 16px; display: flex; align-items: center; gap: 12px; }
    .thumb { width: 42px; height: 42px; border-radius: 10px; object-fit: cover; border: 1px solid var(--line); background: #eee; }
    .title { min-width: 0; flex: 1; }
    .title b { display: block; font-size: 16px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .title span { font-size: 12px; color: var(--muted); }
    .wrap { max-width: 1180px; margin: 0 auto; padding: 18px 16px 110px; }
    .muted { color: var(--muted); font-size: 13px; }
    .card { background: var(--card); border: 1px solid var(--line); border-radius: 18px; padding: 18px; box-shadow: 0 1px 2px rgba(28,25,23,.04); }
    .step { display: flex; align-items: center; gap: 10px; font-weight: 600; margin: 0 0 12px; }
    .step i { font-style: normal; width: 26px; height: 26px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; background: linear-gradient(135deg, var(--gold), var(--gold2)); color: #fff; }
    .row { display: flex; flex-wrap: wrap; gap: 10px 18px; align-items: center; }
    button, .btn { font: inherit; display: inline-flex; align-items: center; justify-content: center; gap: 7px; border-radius: 12px; border: 1px solid var(--line); background: #fff; color: var(--ink); padding: 10px 16px; cursor: pointer; transition: transform .05s, box-shadow .15s; }
    button:active { transform: scale(.98); }
    .gold { background: linear-gradient(135deg, #b8943a, #e6d08f); border-color: #b8943a; font-weight: 600; color: #231a06; box-shadow: 0 6px 16px -8px rgba(160,127,47,.8); }
    .dark { background: var(--ink); color: #fff; border-color: var(--ink); }
    .ghost { background: transparent; }
    button:disabled { opacity: .45; cursor: default; box-shadow: none; }
    .layout { display: grid; gap: 16px; grid-template-columns: 1fr; }
    .layout main { order: -1; }   /* phones: the photos first, the settings below */
    @media (min-width: 900px) { .layout { grid-template-columns: 340px 1fr; align-items: start; } .layout main { order: 0; } .side { position: sticky; top: 80px; } }
    .swatches { display: grid; grid-template-columns: repeat(auto-fill, minmax(64px, 1fr)); gap: 10px; }
    .sw { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 0; border: 0; background: none; font-size: 11px; color: var(--muted); cursor: pointer; text-align: center; line-height: 1.2; }
    .sw .dot { width: 54px; height: 54px; border-radius: 14px; border: 1px solid var(--line); background-size: cover; background-position: center; position: relative; overflow: hidden; box-shadow: inset 0 0 0 1px rgba(0,0,0,.03); }
    .sw.on .dot { outline: 3px solid var(--gold); outline-offset: 2px; }
    .sw.on { color: var(--ink); font-weight: 600; }
    .sw input[type=color] { position: absolute; inset: -10px; width: 80px; height: 80px; border: 0; padding: 0; cursor: pointer; opacity: 0; }
    .sw .x { position: absolute; top: 0; right: 0; background: rgba(0,0,0,.65); color: #fff; font-size: 11px; line-height: 1; padding: 3px 5px; border-radius: 0 0 0 8px; }
    .sw .plus { display: flex; align-items: center; justify-content: center; font-size: 26px; color: var(--gold); border-style: dashed; }
    .switch { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 0; border-top: 1px solid var(--line); font-size: 14px; }
    .switch:first-of-type { border-top: 0; }
    input[type=range] { accent-color: var(--gold); width: 150px; }
    input[type=checkbox] { accent-color: var(--gold); width: 18px; height: 18px; }
    .model { display: flex; align-items: center; gap: 10px; margin-top: 12px; padding: 10px 12px; border-radius: 12px; background: var(--bg); font-size: 13px; color: var(--muted); }
    .progress { flex: 0 0 90px; height: 6px; background: #e7e2d9; border-radius: 9px; overflow: hidden; }
    .progress > div { height: 100%; background: linear-gradient(90deg, var(--gold), var(--gold2)); width: 0; transition: width .2s; }
    .grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }
    .photo { padding: 12px; }
    .stage { position: relative; aspect-ratio: 1; border-radius: 14px; overflow: hidden; background: repeating-conic-gradient(#f1eee8 0% 25%, #fbfaf8 0% 50%) 50% / 22px 22px; cursor: zoom-in; }
    .stage img, .stage canvas { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; }
    .stage canvas { display: none; }
    .stage.has-new canvas { display: block; }
    .stage.has-new img.main { display: none; }
    .before { position: absolute; left: 10px; bottom: 10px; width: 30%; aspect-ratio: 1; border-radius: 10px; border: 2px solid #fff; box-shadow: 0 4px 14px rgba(0,0,0,.25); overflow: hidden; background: #eee; display: none; }
    .before img { width: 100%; height: 100%; object-fit: cover; }
    .stage.has-new .before { display: block; }
    .tag { position: absolute; top: 10px; left: 10px; font-size: 11px; font-weight: 600; letter-spacing: .02em; background: rgba(28,25,23,.72); color: #fff; border-radius: 999px; padding: 3px 10px; }
    .tag.new { background: linear-gradient(135deg, #b8943a, #e6d08f); color: #231a06; }
    .zoom { position: absolute; top: 8px; right: 8px; width: 34px; height: 34px; border-radius: 50%; background: rgba(255,255,255,.9); border: 0; padding: 0; box-shadow: 0 2px 8px rgba(0,0,0,.15); }
    .status { font-size: 13px; margin: 10px 2px 0; min-height: 20px; }
    .ok { color: #15803d; } .err { color: #b91c1c; }
    .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 10px; }
    .actions .wide { grid-column: 1 / -1; }
    .mobile-bar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 6; padding: 10px 16px calc(10px + env(safe-area-inset-bottom)); background: rgba(255,255,255,.95); border-top: 1px solid var(--line); display: flex; gap: 10px; }
    .mobile-bar button { flex: 1; }
    @media (min-width: 900px) { .mobile-bar { display: none; } }
    .desk-only { display: none; } @media (min-width: 900px) { .desk-only { display: inline-flex; } }
    /* Big viewer: before / after / compare */
    .viewer { position: fixed; inset: 0; z-index: 20; background: rgba(12,10,8,.94); display: none; flex-direction: column; }
    .viewer.open { display: flex; }
    .v-top { display: flex; align-items: center; gap: 8px; padding: 12px 14px; color: #fff; }
    .tabs { display: inline-flex; background: rgba(255,255,255,.1); border-radius: 999px; padding: 3px; margin: 0 auto; }
    .tabs button { border: 0; background: none; color: #ddd; border-radius: 999px; padding: 7px 16px; font-size: 14px; }
    .tabs button.on { background: #fff; color: var(--ink); font-weight: 600; }
    .v-close { background: rgba(255,255,255,.12); color: #fff; border: 0; width: 40px; height: 40px; border-radius: 50%; padding: 0; font-size: 20px; }
    .v-body { flex: 1; display: flex; align-items: center; justify-content: center; padding: 0 12px 16px; min-height: 0; }
    .v-frame { position: relative; height: min(100%, 92vw); aspect-ratio: 1; max-width: 100%; border-radius: 14px; overflow: hidden; background: #222; touch-action: none; }
    .v-frame img, .v-frame canvas { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; }
    .v-frame .after { clip-path: inset(0 0 0 var(--cut, 50%)); }
    .v-line { position: absolute; top: 0; bottom: 0; left: var(--cut, 50%); width: 2px; background: #fff; transform: translateX(-1px); pointer-events: none; }
    .v-line::after { content: '⇆'; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 38px; height: 38px; border-radius: 50%; background: #fff; color: var(--ink); display: flex; align-items: center; justify-content: center; font-size: 18px; box-shadow: 0 2px 10px rgba(0,0,0,.4); }
    .v-label { position: absolute; bottom: 10px; font-size: 12px; font-weight: 600; color: #fff; background: rgba(0,0,0,.55); border-radius: 999px; padding: 3px 10px; }
    .editor { position: fixed; inset: 0; background: rgba(12,10,8,.94); display: none; flex-direction: column; align-items: center; justify-content: center; z-index: 21; padding: 12px; }
    .editor.open { display: flex; }
    .editor canvas { max-width: 96vw; max-height: 68vh; background: repeating-conic-gradient(#ddd 0% 25%, #fff 0% 50%) 50% / 24px 24px; touch-action: none; border-radius: 12px; }
    .editor .tools { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: center; margin-top: 12px; color: #fff; }
    .editor .tools button.on { outline: 2px solid var(--gold2); }
</style>
</head>
<body>
<header class="top">
    <div class="top-in">
        @unless ($embed)<a href="{{ $back }}" class="btn ghost" style="padding:8px 10px" title="Back to the product">←</a>@endunless
        @if ($photos)<img class="thumb" src="{{ $photos[0]['url'] }}" alt="">@endif
        <div class="title"><b>{{ $product->name }}</b><span>Photo studio · {{ count($photos) }} {{ count($photos) === 1 ? 'photo' : 'photos' }}</span></div>
        <button class="gold desk-only" data-all>{!! $sparkle !!} Do all photos</button>
    </div>
</header>

<div class="wrap">
    <div class="layout">
        <aside class="side" style="display:grid;gap:16px">
            <div class="card">
                <p class="step"><i>1</i> Choose a background <span id="bgName" class="muted" style="font-weight:400"></span></p>
                <div class="swatches" id="swatches">
                    @foreach ([['white', 'White', '#ffffff'], ['ivory', 'Ivory', '#f7f1e6'], ['grey', 'Soft grey', '#e9e9e7'], ['champagne', 'Champagne', 'radial-gradient(circle at 50% 40%, #fffaf0, #ead9b4)'],
                        ['blush', 'Blush', 'radial-gradient(circle at 50% 40%, #fff6f4, #f1d3cf)'], ['sky', 'Pearl blue', 'radial-gradient(circle at 50% 40%, #f7fbff, #d6e2ee)'],
                        ['emerald', 'Emerald velvet', 'radial-gradient(circle at 50% 40%, #1f5a4a, #0b2a22)'], ['navy', 'Blue velvet', 'radial-gradient(circle at 50% 40%, #29467e, #0d1b3a)'],
                        ['black', 'Black velvet', 'radial-gradient(circle at 50% 40%, #3a3a3a, #0c0c0c)']] as [$k, $label, $css])
                        <button type="button" class="sw" data-bg="{{ $k }}" aria-label="{{ $label }}"><span class="dot" style="background: {{ $css }}"></span>{{ $label }}</button>
                    @endforeach
                    <label class="sw" aria-label="Any colour"><span class="dot" id="colourDot" style="background: conic-gradient(#e25555, #e9c46a, #6bbf59, #4fb3d9, #5a6fe0, #c25ad1, #e25555)"><input type="color" id="bgColour" value="#f3e9d8"></span>Any colour</label>
                    @foreach ($backgrounds as $b)
                        <button type="button" class="sw" data-photo="{{ $b['url'] }}" aria-label="{{ $b['label'] }}"><span class="dot" style="background-image: url('{{ $b['url'] }}')">@if ($b['path'])<span class="x" data-remove="{{ $b['path'] }}" title="Remove this background">✕</span>@endif</span>{{ $b['path'] ? 'Store photo' : 'Showroom' }}</button>
                    @endforeach
                    <label class="sw" aria-label="Add your store photo"><span class="dot plus">+</span>Your store<input type="file" id="bgUpload" accept="image/*" hidden></label>
                </div>
                <div id="photoOpts" style="display:none;margin-top:12px">
                    <div class="switch"><span>Blur the store</span><input type="range" id="bgBlur" min="0" max="24" value="10"></div>
                    <div class="switch"><span>Brighter</span><input type="range" id="bgLight" min="-30" max="40" value="10"></div>
                </div>
            </div>
            <div class="card">
                <p class="step"><i>2</i> Finishing</p>
                <label class="switch"><span>Soft shadow under the piece</span><input type="checkbox" id="optShadow" checked></label>
                <label class="switch"><span>Even out the light</span><input type="checkbox" id="optLight" checked></label>
                <div class="switch"><span>Size of the piece <b id="sizeVal">74%</b></span><input type="range" id="optSize" min="55" max="92" value="74"></div>
                <div class="model"><div class="progress"><div id="prog"></div></div><span id="modelStatus">The AI model loads when you start (first time about 45–115 MB, then quick).</span></div>
            </div>
            <p class="muted" style="margin:0 4px">Free AI in your own browser — nothing is sent to another company. Works best in Chrome or Edge on a computer. Tap any photo to see it big.</p>
        </aside>

        <main>
            <p class="step" style="margin-left:4px"><i>3</i> Your photos <span class="muted" style="font-weight:400">— check, then Save to shop</span></p>
            <div class="grid" id="grid">
                @forelse ($photos as $p)
                    <div class="card photo" data-index="{{ $p['index'] }}" data-path="{{ $p['path'] }}" data-url="{{ $p['url'] }}" data-original="{{ $p['original'] }}">
                        <div class="stage" data-stage>
                            <img class="main" src="{{ $p['url'] }}" alt="" data-now>
                            <canvas data-out width="10" height="10"></canvas>
                            <div class="before"><img src="{{ $p['url'] }}" alt="" data-before></div>
                            <span class="tag" data-tag>{{ $p['original'] ? 'In the shop (studio)' : 'In the shop' }}</span>
                            <button type="button" class="zoom" data-zoom aria-label="View big">{!! $icon('<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3M11 8v6M8 11h6"/>') !!}</button>
                        </div>
                        <div class="status muted" data-status>{{ $p['original'] ? 'Done in the studio before. Redo it or put the original back.' : 'Not done yet.' }}</div>
                        <div class="actions">
                            <button class="gold wide" data-run>{!! $sparkle !!} <span>Make it beautiful</span></button>
                            <button data-edit disabled>{!! $icon('<path d="M4 20c2 0 4-1 4-3.5S6 13 6 13l8.5-8.5a2.1 2.1 0 013 3L9 16"/>') !!} Fix edges</button>
                            <button class="dark" data-save disabled>{!! $icon('<path d="M5 12l4 4L19 6"/>') !!} Save to shop</button>
                            <button class="ghost wide" data-restore @unless ($p['original']) style="display:none" @endunless>{!! $icon('<path d="M9 14L4 9l5-5"/><path d="M4 9h11a5 5 0 010 10h-3"/>') !!} Put original back</button>
                        </div>
                    </div>
                @empty
                    <div class="card"><p class="muted" style="margin:0">This product has no photos yet. Add photos on the product page, save, then open the studio again.</p></div>
                @endforelse
            </div>
        </main>
    </div>
</div>

<div class="mobile-bar"><button class="gold" data-all>{!! $sparkle !!} Do all photos</button></div>

<div class="viewer" id="viewer" role="dialog" aria-modal="true" aria-label="Photo">
    <div class="v-top">
        <div class="tabs" id="vTabs">
            <button data-mode="before">Before</button><button data-mode="after">After</button><button data-mode="compare">Compare</button>
        </div>
        <button class="v-close" id="vClose" aria-label="Close">✕</button>
    </div>
    <div class="v-body"><div class="v-frame" id="vFrame"></div></div>
</div>

<div class="editor" id="editor">
    <canvas id="edCanvas"></canvas>
    <div class="tools">
        <button id="edErase" class="on">Erase (remove background)</button>
        <button id="edKeep">Restore (keep part of the piece)</button>
        <label style="display:inline-flex;gap:6px;align-items:center">Brush <input type="range" id="edSize" min="4" max="120" value="30"></label>
        <button class="gold" id="edDone">Done</button>
    </div>
    <p class="muted" style="color:#ccc;margin-top:8px;text-align:center">Paint over anything that should not be in the photo (hand, tray, table). Faded parts will be removed.</p>
</div>

<script type="module">
const TF = 'https://cdn.jsdelivr.net/npm/@huggingface/transformers@3.8.1';
const csrf = document.querySelector('meta[name=csrf-token]').content;
const routes = { save: @js(route('admin.photo-studio.save', $product)), restore: @js(route('admin.photo-studio.restore', $product)),
    bgAdd: @js(route('admin.photo-studio.bg.add')), bgRemove: @js(route('admin.photo-studio.bg.remove')) };
const $ = (s, el = document) => el.querySelector(s);
const prog = $('#prog'), modelStatus = $('#modelStatus');
const opt = { shadow: () => $('#optShadow').checked, light: () => $('#optLight').checked, size: () => +$('#optSize').value / 100 };
$('#optSize').addEventListener('input', () => { $('#sizeVal').textContent = $('#optSize').value + '%'; items.forEach(it => it.mask && render(it)); });
['#optShadow', '#optLight'].forEach(id => $(id).addEventListener('change', () => { items.forEach(it => it.cut = null); items.forEach(it => it.mask && render(it)); }));

// ---- Background: colours, soft gradients, or a (blurred) photo of the store. Last choice is remembered. ----
const BG = {
    white: ['White', '#ffffff'], ivory: ['Ivory', '#f7f1e6'], grey: ['Soft grey', '#e9e9e7'],
    champagne: ['Champagne glow', ['#fffaf0', '#ead9b4']], blush: ['Blush', ['#fff6f4', '#f1d3cf']], sky: ['Pearl blue', ['#f7fbff', '#d6e2ee']],
    emerald: ['Emerald velvet', ['#1f5a4a', '#0b2a22'], true], navy: ['Royal blue velvet', ['#29467e', '#0d1b3a'], true], black: ['Black velvet', ['#3a3a3a', '#0c0c0c'], true],
};
const remembered = (() => { try { return JSON.parse(localStorage.getItem('studioBg') || 'null'); } catch (e) { return null; } })();
let bg = remembered && (remembered.key || remembered.colour) ? remembered : { key: 'white' };
const bgPhotos = {};
function rerender() { items.forEach(it => it.mask && render(it)); }
function remember() { try { localStorage.setItem('studioBg', JSON.stringify({ key: bg.key, colour: bg.colour })); } catch (e) {} }
function markBg() {
    document.querySelectorAll('.sw').forEach(b => b.classList.toggle('on', !! ((bg.key && b.dataset.bg === bg.key) || (bg.photo && b.dataset.photo === bg.photo) || (bg.colour && b.contains($('#bgColour'))))));
    $('#photoOpts').style.display = bg.photo ? '' : 'none';
    $('#bgName').textContent = bg.photo ? '· store photo' : bg.colour ? '· ' + bg.colour : '· ' + BG[bg.key][0];
    if (bg.colour) $('#colourDot').style.background = bg.colour;
}
function isDark() {
    if (bg.photo) return +$('#bgLight').value < -10;
    const hex = bg.colour || (Array.isArray(BG[bg.key][1]) ? BG[bg.key][1][1] : BG[bg.key][1]);
    const n = parseInt(hex.slice(1), 16);
    return (0.299 * (n >> 16) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255)) < 110;
}
async function photoBitmap(url) {
    return bgPhotos[url] ??= createImageBitmap(await (await fetch(url)).blob());
}
async function paintBackground(o) {
    if (bg.photo) {
        const img = await photoBitmap(bg.photo);
        const s = Math.max(OUT / img.width, OUT / img.height), w = img.width * s, h = img.height * s;
        const b = +$('#bgBlur').value;
        o.imageSmoothingQuality = 'high';
        if (b && 'filter' in o && CSS.supports?.('filter', 'blur(1px)')) {
            // Real blur (Chrome, Edge, Firefox); drawn a little larger so the edges do not fade.
            const pad = b * 6;
            o.filter = `blur(${b * 2.5}px)`;
            o.drawImage(img, (OUT - w) / 2 - pad, (OUT - h) / 2 - pad, w + pad * 2, h + pad * 2);
            o.filter = 'none';
        } else {
            // Other browsers: draw small, then scale up smoothly.
            const k = 1 + b * 1.5, small = canvas(Math.max(8, Math.round(OUT / k)), Math.max(8, Math.round(OUT / k)));
            const sx = small.getContext('2d'); sx.imageSmoothingQuality = 'high';
            sx.drawImage(img, (OUT - w) / 2 / k, (OUT - h) / 2 / k, w / k, h / k);
            o.drawImage(small, 0, 0, OUT, OUT);
        }
        const l = +$('#bgLight').value;
        if (l) { o.fillStyle = l > 0 ? `rgba(255,255,255,${l / 100})` : `rgba(0,0,0,${-l / 100})`; o.fillRect(0, 0, OUT, OUT); }
        return;
    }
    const v = bg.colour || BG[bg.key][1];
    if (Array.isArray(v)) {
        const g = o.createRadialGradient(OUT / 2, OUT * 0.4, OUT * 0.05, OUT / 2, OUT * 0.45, OUT * 0.75);
        g.addColorStop(0, v[0]); g.addColorStop(1, v[1]);
        o.fillStyle = g;
    } else { o.fillStyle = v; }
    o.fillRect(0, 0, OUT, OUT);
}
document.querySelectorAll('[data-bg]').forEach(b => b.onclick = () => { bg = { key: b.dataset.bg }; remember(); markBg(); rerender(); });
$('#bgColour').addEventListener('input', e => { bg = { colour: e.target.value }; remember(); markBg(); rerender(); });
function bindPhoto(b) { b.onclick = e => { if (e.target.dataset.remove) return; bg = { photo: b.dataset.photo }; markBg(); rerender(); }; }
document.querySelectorAll('[data-photo]').forEach(bindPhoto);
['#bgBlur', '#bgLight'].forEach(id => $(id).addEventListener('input', rerender));
document.querySelectorAll('[data-remove]').forEach(x => x.onclick = async e => {
    e.stopPropagation();
    if (! confirm('Remove this background photo?')) return;
    await fetch(routes.bgRemove, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ path: x.dataset.remove }) });
    x.closest('.sw').remove();
    if (bg.photo) { bg = { key: 'white' }; markBg(); rerender(); }
});
$('#bgUpload').addEventListener('change', async e => {
    const f = e.target.files[0]; if (! f) return;
    const body = new FormData(); body.append('file', f);
    const r = await fetch(routes.bgAdd, { method: 'POST', body, headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
    if (! r.ok) { alert('Could not add this photo.'); return; }
    const j = await r.json();
    const b = Object.assign(document.createElement('button'), { type: 'button', className: 'sw', title: 'Store photo' });
    b.dataset.photo = j.url;
    const dot = Object.assign(document.createElement('span'), { className: 'dot' }); dot.style.backgroundImage = `url('${j.url}')`;
    b.append(dot, 'Store photo');
    e.target.closest('.sw').before(b); bindPhoto(b);
    bg = { photo: j.url }; markBg(); rerender();
});

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
        it.mask = tmp; it.cut = null;
        await render(it);
        status(it, 'Done — check it, fix edges if needed, then Save to shop.', 'ok');
        $('[data-edit]', it.el).disabled = false;
        $('[data-save]', it.el).disabled = false;
    } catch (e) {
        status(it, e.message || 'Something went wrong.', 'err');
    } finally { setBusy(it, false); }
}

function setBusy(it, b) { $('[data-run]', it.el).disabled = b; $('[data-run] span', it.el).textContent = b ? 'Working…' : (it.done ? 'Do it again' : 'Make it beautiful'); }
// Tell the product page (when the studio is open inside it) to show the new photos.
const tellProduct = () => { try { window.parent !== window && window.parent.postMessage({ type: 'studio-saved' }, location.origin); } catch (e) {} };

// Light: brightness to a pleasant middle (measured on the piece only), a touch of contrast and colour. Gold stays gold.
function lut(meanLum) {
    const g = Math.max(0.72, Math.min(1.35, Math.log(0.56) / Math.log(Math.max(0.05, Math.min(0.95, meanLum)))));
    const t = new Uint8ClampedArray(256);
    for (let v = 0; v < 256; v++) t[v] = ((Math.pow(v / 255, g) * 255) - 128) * 1.07 + 128;
    return t;
}

async function render(it) {
    if (! it.cut) buildCut(it);
    const { cut, x0, y0, bw, bh } = it.cut;
    // Background, then the piece centred (a little low) with a soft shadow.
    const out = $('[data-out]', it.el);
    const draft = canvas(OUT, OUT), o = draft.getContext('2d');
    await paintBackground(o);
    const s = Math.min(OUT * opt.size() / bw, OUT * opt.size() / bh);
    const dw = bw * s, dh = bh * s, dx = (OUT - dw) / 2, dy = (OUT - dh) / 2 + OUT * 0.02;
    o.save();
    if (opt.shadow()) { o.shadowColor = isDark() ? 'rgba(0, 0, 0, 0.55)' : 'rgba(70, 52, 20, 0.22)'; o.shadowBlur = OUT * 0.035; o.shadowOffsetY = OUT * 0.014; }
    o.imageSmoothingQuality = 'high';
    o.drawImage(cut, x0, y0, bw, bh, dx, dy, dw, dh);
    o.restore();
    out.width = OUT; out.height = OUT;
    out.getContext('2d').drawImage(draft, 0, 0);
    it.done = true;
    $('[data-stage]', it.el).classList.add('has-new');
    const tag = $('[data-tag]', it.el); tag.textContent = 'New — not saved yet'; tag.classList.add('new');
    if (viewer.it === it) showViewer();
}

// The piece cut out with the light evened (kept until the mask or the light option changes).
function buildCut(it) {
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
    it.cut = { cut, x0, y0, bw: x1 - x0 + 1, bh: y1 - y0 + 1 };
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
        const tag = $('[data-tag]', it.el); tag.textContent = '✓ Saved in the shop'; tag.classList.add('new');
        status(it, '✓ Saved — this photo is now in the shop.', 'ok');
        tellProduct();
    } catch (e) { status(it, e.message, 'err'); btn.disabled = false; }
}

async function restore(it) {
    if (! confirm('Put the original photo back?')) return;
    const r = await fetch(routes.restore, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ index: it.index, path: it.path }) });
    if (! r.ok) return status(it, 'Could not put the original back. Reload the page and try again.', 'err');
    const j = await r.json();
    it.path = j.path; it.url = j.url; it.el.dataset.path = j.path; it.src = null; it.mask = null; it.cut = null;
    $('[data-now]', it.el).src = j.url; $('[data-before]', it.el).src = j.url;
    $('[data-stage]', it.el).classList.remove('has-new'); it.done = false;
    const tag = $('[data-tag]', it.el); tag.textContent = 'In the shop'; tag.classList.remove('new');
    $('[data-restore]', it.el).style.display = 'none';
    tellProduct();
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
$('#edDone').onclick = () => { ed.el.classList.remove('open'); ed.it.cut = null; render(ed.it); $('[data-save]', ed.it.el).disabled = false; };

// ---- Big viewer: before, after, or both with a slider. ----
const viewer = { el: $('#viewer'), frame: $('#vFrame'), it: null, mode: 'after', cut: 50 };
function showViewer() {
    const { it, frame } = viewer;
    const hasNew = !! it.done;
    let mode = hasNew ? viewer.mode : 'before';
    document.querySelectorAll('#vTabs button').forEach(b => { b.classList.toggle('on', b.dataset.mode === mode); b.disabled = ! hasNew && b.dataset.mode !== 'before'; });
    frame.innerHTML = '';
    const before = Object.assign(document.createElement('img'), { src: hasNew ? $('[data-before]', it.el).src : $('[data-now]', it.el).src, alt: '' });
    const label = (t, side) => Object.assign(document.createElement('span'), { className: 'v-label', textContent: t, style: side + ':10px' });
    if (mode === 'before' || ! hasNew) { frame.append(before, label(hasNew ? 'Before' : 'In the shop', 'left')); return; }
    const after = canvas(OUT, OUT); after.getContext('2d').drawImage($('[data-out]', it.el), 0, 0); after.className = 'after';
    if (mode === 'after') { after.className = ''; frame.append(after, label('After', 'right')); return; }
    frame.style.setProperty('--cut', viewer.cut + '%');
    frame.append(before, after, Object.assign(document.createElement('div'), { className: 'v-line' }), label('Before', 'left'), label('After', 'right'));
}
function openViewer(it) { viewer.it = it; viewer.mode = it.done ? 'compare' : 'before'; showViewer(); viewer.el.classList.add('open'); }
document.querySelectorAll('#vTabs button').forEach(b => b.onclick = () => { viewer.mode = b.dataset.mode; showViewer(); });
$('#vClose').onclick = () => { viewer.el.classList.remove('open'); viewer.it = null; };
document.addEventListener('keydown', e => { if (e.key === 'Escape') $('#vClose').click(); });
const slide = e => {
    if (viewer.mode !== 'compare') return;
    const r = viewer.frame.getBoundingClientRect();
    viewer.cut = Math.max(0, Math.min(100, (e.clientX - r.left) / r.width * 100));
    viewer.frame.style.setProperty('--cut', viewer.cut + '%');
};
viewer.frame.addEventListener('pointerdown', e => { viewer.frame.setPointerCapture(e.pointerId); viewer.drag = true; slide(e); });
viewer.frame.addEventListener('pointermove', e => viewer.drag && slide(e));
viewer.frame.addEventListener('pointerup', () => viewer.drag = false);

items.forEach(it => {
    $('[data-stage]', it.el).onclick = () => openViewer(it);
    $('[data-run]', it.el).onclick = () => run(it);
    $('[data-save]', it.el).onclick = () => save(it);
    $('[data-restore]', it.el).onclick = () => restore(it);
    $('[data-edit]', it.el).onclick = () => { ed.it = it; ed.c.width = it.src.width; ed.c.height = it.src.height; edDraw(); ed.el.classList.add('open'); };
});
markBg();
document.querySelectorAll('[data-all]').forEach(btn => btn.onclick = async () => {
    document.querySelectorAll('[data-all]').forEach(b => b.disabled = true);
    for (const it of items) await run(it);
    document.querySelectorAll('[data-all]').forEach(b => b.disabled = false);
});
</script>
</body>
</html>
