@props(['icon', 'size' => 44, 'fill' => 'rgba(255,255,255,.16)', 'stroke' => 'rgba(255,255,255,.55)', 'iconClass' => null])
{{-- Emblème d'espace : étoile à 8 branches (octogramme) contenant une icône blanche. --}}
<span {{ $attributes->merge(['class' => 'relative inline-grid shrink-0 place-items-center']) }} style="width: {{ $size }}px; height: {{ $size }}px">
    <svg viewBox="0 0 64 64" class="absolute inset-0 h-full w-full" aria-hidden="true">
        <path d="M32.00,1.00 L40.99,10.29 L53.92,10.08 L53.71,23.01 L63.00,32.00 L53.71,40.99 L53.92,53.92 L40.99,53.71 L32.00,63.00 L23.01,53.71 L10.08,53.92 L10.29,40.99 L1.00,32.00 L10.29,23.01 L10.08,10.08 L23.01,10.29Z" fill="{{ $fill }}" stroke="{{ $stroke }}" stroke-width="1.5" stroke-linejoin="round"/>
    </svg>
    <x-svg-icon :name="$icon" :class="$iconClass ?? 'relative h-[45%] w-[45%] text-white'" />
    {{ $slot }}
</span>
