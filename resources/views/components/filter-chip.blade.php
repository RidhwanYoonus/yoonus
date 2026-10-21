@props(['name', 'value', 'checked' => false, 'type' => 'checkbox'])
<label class="cursor-pointer">
    <input type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" @checked($checked) class="peer sr-only">
    <span class="inline-block rounded-full border border-line px-3.5 py-1.5 text-xs transition peer-checked:border-gold-600 peer-checked:bg-gold-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-gold-300 hover:border-gold-400">{{ $slot }}</span>
</label>
