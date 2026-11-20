@props(['color' => 'var(--orange)', 'count' => null, 'as' => 'h2'])
{{-- Trait coloré court + titre en majuscules + compteur à droite. --}}
<div {{ $attributes->merge(['class' => 'flex items-center justify-between']) }}>
    <{{ $as }} class="section-title">
        <span class="inline-block h-[3px] w-4 rounded-full" style="background: {{ $color }}" aria-hidden="true"></span>
        {{ $slot }}
    </{{ $as }}>
    @if (! is_null($count))
        <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-muted ring-1 ring-line">{{ $count }}</span>
    @endif
</div>
