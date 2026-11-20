@props(['title' => null, 'padding' => true])
<section {{ $attributes->merge(['class' => 'surface']) }}>
    @if ($title || isset($actions))
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3">
            @if ($title)<h2 class="text-[15px] font-semibold text-ink">{{ tr($title) }}</h2>@endif
            @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
        </div>
    @endif
    <div @class(['p-4' => $padding])>{{ $slot }}</div>
</section>
