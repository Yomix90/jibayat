@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />

    <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Choisir un bail : n°, locataire, CIN, boutique…')" :aria-label="__('Chercher un bail')" />

    <x-card :title="__('Baux avec des loyers à encaisser')" :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($rows as $r)
                <li wire:key="c-{{ $r['lease']->id }}">
                    <button type="button" wire:click="choose({{ $r['lease']->id }})" class="flex w-full items-center gap-3 px-4 py-3 text-start hover:bg-bg">
                        <span class="font-mono text-xs font-bold text-ink">N° {{ $r['lease']->number }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-ink">{{ $r['lease']->taxpayer?->displayName() }}</span>
                            <span class="block truncate text-xs text-muted">{{ $r['lease']->shop?->number ?? $r['lease']->premises_ref }} · {{ $r['periode'] }}</span>
                        </span>
                        <x-badge color="red">{{ Format::money($r['total_non_declare']) }} DH</x-badge>
                        <x-svg-icon name="chevron-right" class="h-4 w-4 text-muted rtl:rotate-180" />
                    </button>
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="check" :title="__('Aucun loyer à encaisser')">{{ __('Tous les baux sont à jour ou déjà émis.') }}</x-empty-state></li>
            @endforelse
        </ul>
    </x-card>
</div>
