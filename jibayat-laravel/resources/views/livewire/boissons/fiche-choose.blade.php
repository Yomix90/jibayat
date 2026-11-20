<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />
    <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Chercher un établissement : nom, n°, exploitant, CIN…')" :aria-label="__('Chercher un établissement')" />
    <x-card :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($outlets as $o)
                <li wire:key="f-{{ $o->id }}">
                    <button type="button" wire:click="$set('outletId', {{ $o->id }})" class="flex w-full items-center gap-3 px-4 py-3 text-start hover:bg-bg">
                        <span class="font-mono text-xs font-bold text-ink">N° {{ $o->number }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-ink">{{ $o->name }}</span>
                            <span class="block truncate text-xs text-muted">{{ $o->taxpayer?->displayName() }}{{ $o->address ? ' · '.$o->address : '' }}</span>
                        </span>
                        @if (! $o->is_active)<x-badge>{{ __('Supprimé') }}</x-badge>@elseif ($o->status === 'cesse')<x-badge>{{ __('Cessation') }}</x-badge>@endif
                        <x-svg-icon name="chevron-right" class="h-4 w-4 text-muted rtl:rotate-180" />
                    </button>
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="coffee" :title="__('Aucun établissement')" /></li>
            @endforelse
        </ul>
    </x-card>
</div>
