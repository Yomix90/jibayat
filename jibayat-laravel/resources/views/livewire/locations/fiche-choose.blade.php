<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />
    <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Chercher un bail : n°, locataire, CIN, boutique…')" :aria-label="__('Chercher un bail')" />
    <x-card :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($leases as $l)
                <li wire:key="f-{{ $l->id }}">
                    <button type="button" wire:click="$set('leaseId', {{ $l->id }})" class="flex w-full items-center gap-3 px-4 py-3 text-start hover:bg-bg">
                        <span class="font-mono text-xs font-bold text-ink">N° {{ $l->number }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-ink">{{ $l->taxpayer?->displayName() }}</span>
                            <span class="block truncate text-xs text-muted">{{ $l->shop?->number ?? $l->premises_ref }}</span>
                        </span>
                        @unless ($l->is_active)<x-badge>{{ __('Archivé') }}</x-badge>@endunless
                        <x-svg-icon name="chevron-right" class="h-4 w-4 text-muted rtl:rotate-180" />
                    </button>
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="store" :title="__('Aucun bail')" /></li>
            @endforelse
        </ul>
    </x-card>
</div>
