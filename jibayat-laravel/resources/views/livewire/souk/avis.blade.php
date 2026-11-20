@php use App\Support\Format; use App\Domain\Souk\Services\SoukCalculator; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />
    <div class="flex flex-wrap items-center gap-3 text-sm">
        <label class="flex items-center gap-2">{{ __('Au moins') }}
            <x-input type="number" min="1" wire:model.live.debounce.400ms="minMonths" class="!w-20 text-center" :aria-label="__('Nombre minimal de mois impayés')" />
            {{ __('mois impayé(s)') }}</label>
        <span class="ms-auto font-semibold">{{ $rows->count() }} contrat(s) · <span class="text-red-600">{{ Format::money($total) }} DH</span></span>
    </div>
    <x-card :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($rows as $r)
                @php($c = $r['contract'])
                <li class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm" wire:key="a-{{ $c->id }}">
                    <span class="font-mono text-xs font-bold">N° {{ $c->number }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $c->taxpayer?->displayName() }} <span class="text-xs font-normal text-muted">{{ $c->taxpayer?->phone }}</span></p>
                        <p class="truncate text-xs text-muted">{{ $c->souk_name }} · {{ SoukCalculator::periodLabel($r['months']) }}</p>
                    </div>
                    <p class="font-bold tabular-nums text-red-600">{{ Format::money($r['total_impaye']) }} DH</p>
                    <x-btn variant="secondary" size="sm" icon="printer" :href="route('souk.avis-imprimer', $c)" target="_blank">{{ __('Avis') }}</x-btn>
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="check" :title="__('Aucun contrat en retard')" /></li>
            @endforelse
        </ul>
    </x-card>
</div>
