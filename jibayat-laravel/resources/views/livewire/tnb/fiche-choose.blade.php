<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />
    <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Chercher un terrain : n°, redevable, CIN, titre foncier, lotissement…')" :aria-label="__('Chercher un terrain')" />
    <x-card :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($parcels as $p)
                <li wire:key="f-{{ $p->id }}">
                    <button type="button" wire:click="$set('parcelId', {{ $p->id }})" class="flex w-full items-center gap-3 px-4 py-3 text-start hover:bg-bg">
                        <span class="font-mono text-xs font-bold text-ink">T {{ $p->number }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-ink">{{ $p->taxpayer?->displayName() }}</span>
                            <span class="block truncate text-xs text-muted">{{ $p->locationLabel() }}</span>
                        </span>
                        @if (! $p->is_active)<x-badge>{{ __('Supprimé') }}</x-badge>@elseif ($p->is_archived)<x-badge>{{ __('Archivé') }}</x-badge>@elseif ($p->is_census)<x-badge color="gold">{{ __('Recensement') }}</x-badge>@endif
                        <x-svg-icon name="chevron-right" class="h-4 w-4 text-muted rtl:rotate-180" />
                    </button>
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="map-pin" :title="__('Aucun terrain')" /></li>
            @endforelse
        </ul>
    </x-card>
</div>
