{{-- One ID photo: "Take photo" opens the phone camera, "Choose file" the gallery / files. Large photos are shrunk, then sent
     straight away (so the form keeps it if it comes back with an error) and read to fill the ID number and expiry date. --}}
@php($earlier = \App\Support\IdDocument::pendingByToken(old($name.'_token')))
<div x-data="{
        preview: null, name: @js($earlier['name'] ?? ''), token: @js($earlier ? old($name.'_token') : ''), busy: '', note: '', noteOk: true,
        get off() { return this.needed === false || !! this.useSaved },
        pick(camera) {
            const i = this.$refs.file;
            camera ? i.setAttribute('capture', 'environment') : i.removeAttribute('capture');
            i.accept = camera ? 'image/*' : 'image/*,.pdf';
            i.click();
        },
        async chosen(e) {
            let f = e.target.files[0];
            if (! f) return;
            this.name = f.name; this.token = ''; this.note = '';
            if (f.type.startsWith('image/') && f.size > 700000) {
                try {
                    const img = await createImageBitmap(f);
                    const scale = Math.min(1, 2000 / Math.max(img.width, img.height));
                    const c = Object.assign(document.createElement('canvas'), { width: Math.round(img.width * scale), height: Math.round(img.height * scale) });
                    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                    const blob = await new Promise(r => c.toBlob(r, 'image/jpeg', 0.85));
                    if (blob && blob.size < f.size) {
                        f = new File([blob], f.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' });
                        const dt = new DataTransfer(); dt.items.add(f); e.target.files = dt.files;
                    }
                } catch (err) { /* keep the original photo */ }
            }
            this.preview = f.type.startsWith('image/') ? URL.createObjectURL(f) : null;
            this.upload(f, e.target);
            @if (\App\Support\Settings::bool('id_auto_read', true)) this.read(f); @endif
        },
        async upload(f, input) {
            this.busy = @js(__('shop.id.uploading'));
            try {
                const body = new FormData(); body.append('file', f);
                const r = await fetch(@js(route('id.upload')), { method: 'POST', body, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content } });
                if (r.ok) { this.token = (await r.json()).token; input.value = ''; }   // sent: the form only carries the token now
            } catch (err) { /* the photo still goes with the form */ }
            this.busy = '';
        },
        async read(f) {
            if (! f.type.startsWith('image/') || ! window.shopIdReader) return;
            this.note = @js(__('shop.id.reading')); this.noteOk = true;
            try {
                const filled = window.shopIdReader.fillIdForm(this.$el.closest('form'), await window.shopIdReader.readIdPhoto(f));
                this.note = filled.length ? @js(__('shop.id.read_ok')) : '';
            } catch (err) { this.note = ''; }
        }
    }">
    <label class="label" for="{{ $name }}">{{ $label }}</label>
    <input x-ref="file" id="{{ $name }}" type="file" name="{{ $name }}" accept="image/*,.pdf" class="sr-only" @change="chosen"
           :disabled="off" @if ($required) :required="!off && !token" @endif>
    <input type="hidden" name="{{ $name }}_token" :value="token" :disabled="off || !token">
    <div class="flex flex-wrap items-center gap-2" :class="off && 'pointer-events-none opacity-50'">
        <button type="button" class="btn-dark px-4 py-2 text-xs" @click="pick(true)">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 8.5A2.5 2.5 0 0 1 5.5 6h1.6l1.2-1.8A1.5 1.5 0 0 1 9.5 3.5h5a1.5 1.5 0 0 1 1.2.7L16.9 6h1.6A2.5 2.5 0 0 1 21 8.5v9a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 17.5z"/><circle cx="12" cy="12.5" r="3.5"/></svg>
            {{ __('shop.id.take_photo') }}
        </button>
        <button type="button" class="btn-outline px-4 py-2 text-xs" @click="pick(false)">{{ __('shop.id.choose_file') }}</button>
    </div>
    <div x-show="name" @if (! $earlier) x-cloak @endif class="mt-2 flex items-center gap-3 rounded-xl border border-line p-2 text-xs">
        <template x-if="preview"><img :src="preview" alt="" class="h-14 w-20 rounded-lg object-cover"></template>
        <span class="min-w-0 flex-1 truncate"><span x-text="name">{{ $earlier['name'] ?? '' }}</span>
            <span class="block text-muted" x-show="busy" x-text="busy"></span></span>
        <span class="text-green-700" x-show="!busy">✓</span>
    </div>
    <p x-show="note" x-cloak x-text="note" class="mt-1 text-xs text-gold-700" role="status"></p>
    @error($name)<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
</div>
