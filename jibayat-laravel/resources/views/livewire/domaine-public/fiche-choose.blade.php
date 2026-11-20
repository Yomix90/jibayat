<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />
    <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Chercher une occupation : n°, occupant, emplacement…')" :aria-label="__('Chercher une occupation')" />
    <x-card :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($occupations as $o)
                <li wire:key="f-{{ $o->id }}">
                    <button type="button" wire:click="$set('occupationId', {{ $o->id }})" class="flex w-full items-center gap-3 px-4 py-3 text-start hover:bg-bg">
                        <span class="font-mono text-xs font-bold text-ink">N° {{ $o->number }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-ink">{{ $o->type }} · {{ $o->location }}</span>
                            <span class="block truncate text-xs text-muted">{{ $o->taxpayer?->displayName() }}</span>
                        </span>
                        @unless ($o->is_active)<x-badge>{{ __('Archivée') }}</x-badge>@endunless
                        <x-svg-icon name="chevron-right" class="h-4 w-4 text-muted rtl:rotate-180" />
                    </button>
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="tent" :title="__('Aucune occupation')" /></li>
            @endforelse
        </ul>
    </x-card>
</div>
