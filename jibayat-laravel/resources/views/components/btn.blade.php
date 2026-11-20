@props(['variant' => 'primary', 'size' => 'md', 'icon' => null, 'href' => null])
@php
    $base = 'pressable inline-flex select-none items-center justify-center gap-1.5 rounded-full font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-1 disabled:pointer-events-none disabled:opacity-50';
    $sizes = ['sm' => 'min-h-8 px-3 py-1.5 text-xs', 'md' => 'min-h-10 px-4 py-2 text-sm'];
    $variants = [
        'primary' => 'bg-navy text-white shadow-sm hover:bg-[#2B3A52]',
        'gold' => 'bg-gold text-navy shadow-sm hover:brightness-105',
        'secondary' => 'border border-line bg-white text-ink shadow-sm hover:border-gold',
        'danger' => 'bg-red-600 text-white hover:bg-red-700',
        'ghost' => 'text-muted hover:bg-bg hover:text-ink',
    ];
    $classes = $base.' '.$sizes[$size].' '.$variants[$variant];
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>@if($icon)<x-svg-icon :name="$icon" class="h-4 w-4" />@endif{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>@if($icon)<x-svg-icon :name="$icon" class="h-4 w-4" />@endif{{ $slot }}</button>
@endif
