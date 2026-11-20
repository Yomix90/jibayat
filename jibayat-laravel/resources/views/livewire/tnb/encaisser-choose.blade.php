@php use App\Support\Format; use App\Domain\Tnb\Services\TnbCalculator; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />

    <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Choisir un terrain : n°, redevable, CIN, titre foncier, lotissement…')" :aria-label="__('Chercher un terrain')" />

    <x-card :title="__('Terrains avec des années à déclarer')" :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($rows as $r)
                @php($p = $r['parcel'])
                <li wire:key="c-{{ $p->id }}">
                    <button type="button" wire:click="choose({{ $p->id }})" class="flex w-full items-center gap-3 px-4 py-3 text-start hover:bg-bg">
                        <span class="font-mono text-xs font-bold text-ink">T {{ $p->number }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-ink">{{ $p->taxpayer?->displayName() }}</span>
                            <span class="block truncate text-xs text-muted">{{ $p->locationLabel() }} · {{ TnbCalculator::periodLabel(array_column($r['not_declared'], 'annee')) }}</span>
                        </span>
                        <x-badge color="red">{{ Format::money($r['total_not_declared']) }} DH</x-badge>
                        <x-svg-icon name="chevron-right" class="h-4 w-4 text-muted rtl:rotate-180" />
                    </button>
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="check" :title="__('Aucune année à déclarer')">{{ __('Tous les terrains sont à jour ou déjà émis.') }}</x-empty-state></li>
            @endforelse
        </ul>
    </x-card>
</div>
