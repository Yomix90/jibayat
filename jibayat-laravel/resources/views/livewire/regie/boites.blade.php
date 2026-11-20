<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>@if ($this->allowed('archives.modifier'))<x-btn icon="plus" wire:click="openForm">{{ __('Nouvelle boîte') }}</x-btn>@endif</x-slot:actions>
    </x-page-header>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($boxes as $b)
            <x-card wire:key="b-{{ $b->id }}">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gold/15 text-[#7a5d16]"><x-svg-icon name="archive" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="font-mono text-xs font-bold">{{ $b->code }}</p>
                        <p class="font-semibold">{{ $b->label }}</p>
                        <p class="text-xs text-muted">{{ $b->year }}@if ($b->location) · {{ $b->location }}@endif · {{ $b->documents_count }} {{ __('pièce(s)') }}</p>
                        @if ($b->description)<p class="mt-1 text-xs text-muted">{{ $b->description }}</p>@endif
                    </div>
                    @if ($b->is_closed)<x-badge>{{ __('Fermée') }}</x-badge>@else<x-badge color="green">{{ __('Ouverte') }}</x-badge>@endif
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <x-btn variant="secondary" size="sm" icon="files" :href="route('regie.documents', ['boite' => $b->id])">{{ __('Contenu') }}</x-btn>
                    <x-btn variant="ghost" size="sm" icon="printer" :href="route('regie.imprimer.boite', $b)" target="_blank">{{ __('Étiquette') }}</x-btn>
                    @if ($this->allowed('archives.modifier'))
                        <x-btn variant="ghost" size="sm" icon="pencil" wire:click="openForm({{ $b->id }})" :aria-label="__('Modifier')" />
                        <x-btn variant="ghost" size="sm" :icon="$b->is_closed ? 'lock-open' : 'lock'" wire:click="toggle({{ $b->id }})">{{ $b->is_closed ? __('Rouvrir') : __('Fermer') }}</x-btn>
                    @endif
                </div>
            </x-card>
        @empty
            <x-empty-state class="sm:col-span-2 lg:col-span-3" icon="archive" :title="__('Aucune boîte d’archive')" />
        @endforelse
    </div>

    <x-panel show="showForm" title="Boîte d’archive">
        <form wire:submit="save" class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <x-field :label="__('Code')" for="bx-c" error="form.code"><x-input id="bx-c" wire:model="form.code" /></x-field>
                <x-field :label="__('Exercice')" for="bx-y" error="form.year"><x-input id="bx-y" type="number" wire:model="form.year" /></x-field>
            </div>
            <x-field :label="__('Libellé')" for="bx-l" error="form.label"><x-input id="bx-l" wire:model="form.label" :placeholder="__('Quittances TNB 1er semestre…')" /></x-field>
            <x-field :label="__('Emplacement')" for="bx-loc" error="form.location"><x-input id="bx-loc" wire:model="form.location" :placeholder="__('Armoire 2, étagère 3…')" /></x-field>
            <x-field :label="__('Description')" for="bx-d" error="form.description"><x-textarea id="bx-d" rows="2" wire:model="form.description" /></x-field>
            <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('showForm', false)">{{ __('Annuler') }}</x-btn><x-btn type="submit" variant="gold" icon="check">{{ __('Enregistrer') }}</x-btn></div>
        </form>
    </x-panel>
</div>
