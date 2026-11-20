@props(['icon' => 'info', 'title' => 'Aucun élément'])
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-dashed border-line bg-white/60 px-4 py-8 text-center']) }}>
    <x-svg-icon :name="$icon" class="mx-auto h-8 w-8 text-muted" />
    <p class="mt-2 text-sm font-semibold text-ink">{{ tr($title) }}</p>
    @if (trim($slot))<p class="mt-1 text-xs text-muted">{{ $slot }}</p>@endif
</div>
