<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />
    <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Chercher un véhicule : immatriculation, propriétaire, CIN…')" :aria-label="__('Chercher un véhicule')" />
    <x-card :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($vehicles as $v)
                <li wire:key="f-{{ $v->id }}">
                    <button type="button" wire:click="$set('vehicleId', {{ $v->id }})" class="flex w-full items-center gap-3 px-4 py-3 text-start hover:bg-bg">
                        <span class="font-mono text-xs font-bold text-ink" dir="ltr">{{ $v->registration }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-ink">{{ $v->taxpayer?->displayName() }}</span>
                            <span class="block truncate text-xs text-muted">N° {{ $v->number }} · {{ $v->type }}</span>
                        </span>
                        @unless ($v->is_active)<x-badge>{{ __('Supprimé') }}</x-badge>@endunless
                        <x-svg-icon name="chevron-right" class="h-4 w-4 text-muted rtl:rotate-180" />
                    </button>
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="bus" :title="__('Aucun véhicule')" /></li>
            @endforelse
        </ul>
    </x-card>
</div>
