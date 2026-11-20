@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            @if ($this->allowed('tnb.ajouter'))<x-btn icon="plus" wire:click="createParcel">{{ __('Terrain recensé') }}</x-btn>@endif
        </x-slot:actions>
    </x-page-header>

    <p class="text-sm text-muted">{{ __('Terrains repérés sur le terrain ou par le cadastre, sans dossier officiel. « Transférer » les ajoute à la liste TNB et crée le dossier du redevable s\'il n\'existe pas.') }}</p>

    <div class="flex flex-wrap items-center gap-3">
        <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Redevable, CIN, titre foncier, adresse…')" :aria-label="__('Rechercher')" class="sm:max-w-sm" />
        <span class="ms-auto text-sm font-semibold">{{ $rows->count() }} terrain(s) recensé(s)</span>
    </div>

    <x-card :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($rows as $p)
                <li class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm" wire:key="r-{{ $p->id }}">
                    <span class="font-mono text-xs font-bold">T {{ $p->number }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $p->taxpayer?->displayName() }}
                            @if ($p->taxpayer?->displayNameAr())<span class="font-ar font-normal text-muted" lang="ar"> · {{ $p->taxpayer->displayNameAr() }}</span>@endif</p>
                        <p class="truncate text-xs text-muted">{{ $p->locationLabel() }} · {{ Format::money($p->area) }} m² · zone {{ $p->zone }}</p>
                    </div>
                    <div class="flex gap-1">
                        <x-btn variant="ghost" size="sm" icon="file-text" :href="route('tnb.fiche', ['terrain' => $p->id])">{{ __('Fiche') }}</x-btn>
                        @if ($this->allowed('tnb.modifier'))<x-btn variant="ghost" size="sm" icon="pencil" wire:click="editParcel({{ $p->id }})" aria-label="Modifier le terrain {{ $p->number }}" />@endif
                        @if ($this->allowed('tnb.ajouter'))
                            <x-btn variant="gold" size="sm" icon="folder-input" wire:click="promote({{ $p->id }})" wire:confirm="Transférer le terrain n° {{ $p->number }} vers la liste TNB ?">{{ __('Transférer') }}</x-btn>
                        @endif
                        @if ($this->allowed('tnb.supprimer'))
                            <x-btn variant="ghost" size="sm" icon="trash-2" wire:click="remove({{ $p->id }})" wire:confirm="Retirer le terrain n° {{ $p->number }} du recensement ?" :aria-label="__('Retirer')" />
                        @endif
                    </div>
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="clipboard-list" :title="__('Aucun terrain recensé')">{{ __('Ajoutez les terrains repérés sans dossier.') }}</x-empty-state></li>
            @endforelse
        </ul>
    </x-card>

    @include('livewire.tnb.partials.parcel-form', ['census' => true])
</div>
