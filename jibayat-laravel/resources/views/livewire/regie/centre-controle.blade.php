@php
    $levels = ['danger' => ['circle-alert', 'text-red-600', 'bg-red-50', 'À corriger'], 'warning' => ['triangle-alert', 'text-[#8a5a12]', 'bg-gold/10', 'À surveiller'], 'info' => ['info', 'text-sky-700', 'bg-sky-50', 'Pour information']];
    $counts = collect($issues)->countBy('level');
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />

    <section class="grid grid-cols-3 gap-3" aria-label="{{ __('Indicateurs') }}">
        @foreach ($levels as $k => [$icon, $color, $bg, $label])
            <div class="surface flex items-center gap-3 p-4"><span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $bg }} {{ $color }}"><x-svg-icon :name="$icon" /></span>
                <div><p class="text-xs font-semibold text-muted">{{ __($label) }}</p><p class="text-xl font-bold tabular-nums">{{ $counts[$k] ?? 0 }}</p></div></div>
        @endforeach
    </section>

    <x-card :padding="false">
        <ul class="divide-y divide-line text-sm">
            @forelse ($issues as $i)
                @php([$icon, $color] = $levels[$i['level']])
                <li class="flex items-start gap-3 px-4 py-3">
                    <x-svg-icon :name="$icon" class="mt-0.5 h-5 w-5 shrink-0 {{ $color }}" />
                    <div class="min-w-0 flex-1"><p class="font-semibold">{{ $i['title'] }}</p><p class="text-xs text-muted">{{ $i['detail'] }}</p></div>
                    @if ($i['route'] && Route::has($i['route']))<x-btn variant="secondary" size="sm" icon="arrow-right" :href="route($i['route'], $i['params'])">{{ __('Corriger') }}</x-btn>@endif
                </li>
            @empty
                <li class="p-6"><x-empty-state icon="circle-check" :title="__('Rien à signaler : la régie est à jour')" /></li>
            @endforelse
        </ul>
    </x-card>
</div>
