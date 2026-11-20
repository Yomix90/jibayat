@props(['color' => 'gray'])
@php
    $colors = [
        'gray' => 'bg-bg text-muted ring-line', 'green' => 'bg-green-50 text-green-700 ring-green-200',
        'red' => 'bg-red-50 text-red-700 ring-red-200', 'gold' => 'bg-gold/15 text-[#7a5d16] ring-gold/30',
        'blue' => 'bg-sky-50 text-sky-700 ring-sky-200', 'orange' => 'bg-accent/10 text-accent ring-accent/30',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-bold ring-1 '.$colors[$color]]) }}>{{ $slot }}</span>
