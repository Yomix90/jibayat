@php use App\Support\Format; use App\Domain\DomainePublic\Services\OccupationCalculator; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />
    <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Choisir une occupation : n°, occupant, emplacement…')" :aria-label="__('Chercher une occupation')" />
    <x-card :title="__('Occupations avec des trimestres à liquider')" :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($rows as $r)
                @php($o = $r['occupation'])
                <li wire:key="c-{{ $o->id }}">
                    <button type="button" wire:click="choose({{ $o->id }})" class="flex w-full items-center gap-3 px-4 py-3 text-start hover:bg-bg">
                        <span class="font-mono text-xs font-bold text-ink">N° {{ $o->number }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-ink">{{ $o->taxpayer?->displayName() }} · {{ $o->type }}</span>
                            <span class="block truncate text-xs text-muted">{{ $o->location }} · {{ OccupationCalculator::periodLabel(array_values(array_filter($r['quarters'], fn ($q) => ! $q['est_emis']))) }}</span>
                        </span>
                        <x-badge color="red">{{ Format::money($r['montant_non_declare']) }} DH</x-badge>
                        <x-svg-icon name="chevron-right" class="h-4 w-4 text-muted rtl:rotate-180" />
                    </button>
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="check" :title="__('Rien à liquider')">{{ __('Toutes les occupations sont à jour ou déjà émises.') }}</x-empty-state></li>
            @endforelse
        </ul>
    </x-card>
</div>
