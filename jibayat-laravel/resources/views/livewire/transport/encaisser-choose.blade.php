@php use App\Domain\Boissons\Services\BeverageCalculator; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />
    <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Choisir un véhicule : immatriculation, propriétaire, CIN…')" :aria-label="__('Chercher un véhicule')" />
    <x-card :title="__('Véhicules avec des trimestres à payer')" :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($rows as $r)
                @php($v = $r['vehicle'])
                <li wire:key="c-{{ $v->id }}">
                    <button type="button" wire:click="choose({{ $v->id }})" class="flex w-full items-center gap-3 px-4 py-3 text-start hover:bg-bg">
                        <span class="font-mono text-xs font-bold text-ink" dir="ltr">{{ $v->registration }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-ink">{{ $v->taxpayer?->displayName() }} · {{ $v->type }}</span>
                            <span class="block truncate text-xs text-muted">{{ BeverageCalculator::periodLabel($r['quarters']) }}</span>
                        </span>
                        <x-badge color="red">{{ count($r['quarters']) }} trimestre(s)</x-badge>
                        <x-svg-icon name="chevron-right" class="h-4 w-4 text-muted rtl:rotate-180" />
                    </button>
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="check" :title="__('Aucun trimestre à payer')">{{ __('Tous les véhicules sont à jour ou déjà émis.') }}</x-empty-state></li>
            @endforelse
        </ul>
    </x-card>
</div>
