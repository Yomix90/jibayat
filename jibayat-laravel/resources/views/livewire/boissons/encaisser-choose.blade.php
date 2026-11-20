@php use App\Domain\Boissons\Services\BeverageCalculator; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />

    <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Choisir un établissement : nom, n°, exploitant, CIN…')" :aria-label="__('Chercher un établissement')" />

    <x-card :title="__('Établissements avec des trimestres à déclarer')" :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($rows as $r)
                @php($o = $r['outlet'])
                <li wire:key="c-{{ $o->id }}">
                    <button type="button" wire:click="choose({{ $o->id }})" class="flex w-full items-center gap-3 px-4 py-3 text-start hover:bg-bg">
                        <span class="font-mono text-xs font-bold text-ink">N° {{ $o->number }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-ink">{{ $o->name }} · {{ $o->taxpayer?->displayName() }}</span>
                            <span class="block truncate text-xs text-muted">{{ BeverageCalculator::periodLabel($r['quarters']) }}</span>
                        </span>
                        <x-badge color="red">{{ count($r['quarters']) }} trimestre(s)</x-badge>
                        <x-svg-icon name="chevron-right" class="h-4 w-4 text-muted rtl:rotate-180" />
                    </button>
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="check" :title="__('Aucun trimestre à déclarer')">{{ __('Tous les établissements sont à jour ou déjà émis.') }}</x-empty-state></li>
            @endforelse
        </ul>
    </x-card>
</div>
