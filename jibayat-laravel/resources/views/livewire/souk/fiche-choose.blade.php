<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />
    <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Chercher un contrat : n°, souk, fermier, CIN…')" :aria-label="__('Chercher un contrat')" />
    <x-card :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($contracts as $c)
                <li wire:key="f-{{ $c->id }}">
                    <button type="button" wire:click="$set('contractId', {{ $c->id }})" class="flex w-full items-center gap-3 px-4 py-3 text-start hover:bg-bg">
                        <span class="font-mono text-xs font-bold text-ink">N° {{ $c->number }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-ink">{{ $c->souk_name }}{{ $c->pitch_number ? ' · '.$c->pitch_number : '' }}</span>
                            <span class="block truncate text-xs text-muted">{{ $c->taxpayer?->displayName() }}</span>
                        </span>
                        @if (! $c->is_active)<x-badge>{{ __('Archivé') }}</x-badge>@elseif ($c->status !== 'actif')<x-badge>{{ \App\Domain\Souk\Models\SoukContract::STATUSES[$c->status] ?? $c->status }}</x-badge>@endif
                        <x-svg-icon name="chevron-right" class="h-4 w-4 text-muted rtl:rotate-180" />
                    </button>
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="scroll" :title="__('Aucun contrat')" /></li>
            @endforelse
        </ul>
    </x-card>
</div>
