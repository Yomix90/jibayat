@php use App\Domain\Fourriere\Services\ImpoundCalculator; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />
    <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Chercher un dossier : immatriculation, n°, dépôt, propriétaire…')" :aria-label="__('Chercher un dossier')" />
    <x-card :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($cases as $c)
                <li wire:key="f-{{ $c->id }}">
                    <button type="button" wire:click="$set('caseId', {{ $c->id }})" class="flex w-full items-center gap-3 px-4 py-3 text-start hover:bg-bg">
                        <span class="font-mono text-xs font-bold text-ink" dir="ltr">{{ $c->registration }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-ink">Dossier {{ $c->number }} · {{ $c->vehicle_type }}</span>
                            <span class="block truncate text-xs text-muted">Entré le {{ $c->impounded_on?->format('d/m/Y') }}{{ $c->owner_name ? ' · '.$c->owner_name : '' }}</span>
                        </span>
                        <x-badge>{{ ImpoundCalculator::STATUSES[$c->status] ?? $c->status }}</x-badge>
                        <x-svg-icon name="chevron-right" class="h-4 w-4 text-muted rtl:rotate-180" />
                    </button>
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="car" :title="__('Aucun dossier')" /></li>
            @endforelse
        </ul>
    </x-card>
</div>
