{{-- The Photo studio inside the product page. When a photo is saved there, the product form shows it straight away. --}}
<div x-data x-on:message.window="if ($event.origin === window.location.origin && $event.data?.type === 'studio-saved') $wire.studioSaved()">
    <iframe src="{{ $url }}" title="Photo studio" style="width:100%;height:calc(100dvh - 190px);min-height:520px;border:0;border-radius:14px;background:#f7f5f1" allow="cross-origin-isolated"></iframe>
</div>
