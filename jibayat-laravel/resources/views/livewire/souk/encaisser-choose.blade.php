@php use App\Support\Format; use App\Domain\Souk\Services\SoukCalculator; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />
    <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Choisir un contrat : n°, souk, fermier, CIN…')" :aria-label="__('Chercher un contrat')" />
    <x-card :title="__('Contrats avec des redevances à encaisser')" :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($rows as $r)
                @php($c = $r['contract'])
                <li wire:key="c-{{ $c->id }}">
                    <button type="button" wire:click="choose({{ $c->id }})" class="flex w-full items-center gap-3 px-4 py-3 text-start hover:bg-bg">
                        <span class="font-mono text-xs font-bold text-ink">N° {{ $c->number }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-ink">{{ $c->taxpayer?->displayName() }} · {{ $c->souk_name }}</span>
                            <span class="block truncate text-xs text-muted">{{ SoukCalculator::periodLabel(array_values(array_filter($r['months'], fn ($m) => ! $m['est_emis']))) }}</span>
                        </span>
                        <x-badge color="red">{{ Format::money($r['total_non_declare']) }} DH</x-badge>
                        <x-svg-icon name="chevron-right" class="h-4 w-4 text-muted rtl:rotate-180" />
                    </button>
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="check" :title="__('Aucune redevance à encaisser')">{{ __('Tous les contrats sont à jour ou déjà émis.') }}</x-empty-state></li>
            @endforelse
        </ul>
    </x-card>
</div>
