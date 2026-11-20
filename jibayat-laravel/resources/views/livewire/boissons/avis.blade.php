@php use App\Domain\Boissons\Services\BeverageCalculator; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />

    <div class="flex flex-wrap items-center gap-3 text-sm">
        <label class="flex items-center gap-2">{{ __('Au moins') }}
            <x-input type="number" min="1" wire:model.live.debounce.400ms="minQuarters" class="!w-20 text-center" :aria-label="__('Nombre minimal de trimestres non déclarés')" />
            {{ __('trimestre(s) non déclaré(s)') }}</label>
        <span class="ms-auto font-semibold">{{ $rows->count() }} établissement(s)</span>
    </div>

    <x-card :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($rows as $r)
                @php($o = $r['outlet'])
                <li class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm" wire:key="a-{{ $o->id }}">
                    <span class="font-mono text-xs font-bold">N° {{ $o->number }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $o->name }} · {{ $o->taxpayer?->displayName() }} <span class="text-xs font-normal text-muted">{{ $o->taxpayer?->phone }}</span></p>
                        <p class="truncate text-xs text-muted">{{ BeverageCalculator::periodLabel($r['quarters']) }}</p>
                    </div>
                    <x-badge color="red">{{ count($r['quarters']) }} trimestre(s)</x-badge>
                    <x-btn variant="secondary" size="sm" icon="printer" :href="route('boissons.avis-imprimer', $o)" target="_blank">{{ __('Avis') }}</x-btn>
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="check" :title="__('Aucun établissement en retard')" /></li>
            @endforelse
        </ul>
    </x-card>
</div>
