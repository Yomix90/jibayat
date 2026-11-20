@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />

    <div class="flex flex-wrap items-center gap-3 text-sm">
        <label class="flex items-center gap-2">{{ __('Au moins') }}
            <x-input type="number" min="1" wire:model.live.debounce.400ms="minMonths" class="!w-20 text-center" :aria-label="__('Nombre minimal de mois impayés')" />
            {{ __('mois impayé(s)') }}</label>
        <span class="ms-auto font-semibold">{{ $rows->count() }} bail(s) · <span class="text-red-600">{{ Format::money($total) }} DH</span></span>
    </div>

    <x-card :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($rows as $r)
                @php($l = $r['lease'])
                <li class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm" wire:key="a-{{ $l->id }}">
                    <span class="font-mono text-xs font-bold">N° {{ $l->number }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $l->taxpayer?->displayName() }} <span class="text-xs font-normal text-muted">{{ $l->taxpayer?->phone }}</span></p>
                        <p class="truncate text-xs text-muted">{{ $l->shop?->number ?? $l->premises_ref }} · {{ $r['periode'] }}</p>
                    </div>
                    <p class="font-bold tabular-nums text-red-600">{{ Format::money($r['total_du']) }} DH</p>
                    <x-btn variant="secondary" size="sm" icon="printer" :href="route('locations.avis-imprimer', $l)" target="_blank">{{ __('Avis') }}</x-btn>
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="check" :title="__('Aucun bail en retard')" /></li>
            @endforelse
        </ul>
    </x-card>
</div>
