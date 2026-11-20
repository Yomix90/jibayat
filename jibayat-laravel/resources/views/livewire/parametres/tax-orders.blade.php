<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        @if ($this->canWrite())
            <x-slot:actions><x-btn icon="plus" wire:click="create">{{ __('Nouvel arrêté') }}</x-btn></x-slot:actions>
        @endif
    </x-page-header>

    <p class="text-sm text-muted">
        {{ __('Un seul arrêté est actif à la fois. En créer un nouveau clôt le précédent à la date d\'effet choisie et recopie, si vous le souhaitez, les derniers tarifs de chaque rubrique.') }}
    </p>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($orders as $o)
            <a href="{{ route('parametres.arretes.show', $o) }}" wire:key="ord-{{ $o->id }}"
               @class(['block rounded-2xl border bg-white p-4 transition hover:shadow-md', 'border-gold ring-2 ring-gold/30' => $o->status === 'actif', 'border-line' => $o->status !== 'actif'])>
                <div class="flex items-start justify-between gap-2">
                    <p class="font-mono text-sm font-bold text-ink">{{ $o->number }}</p>
                    @if ($o->status === 'actif')<x-badge color="gold">{{ __('Actif') }}</x-badge>@else<x-badge>{{ $o->status === 'remplace' ? 'Remplacé' : ucfirst($o->status) }}</x-badge>@endif
                </div>
                <p class="mt-1 text-sm font-semibold text-ink">{{ $o->title }}</p>
                <p class="mt-2 text-xs text-muted">
                    Effet le {{ $o->effective_date->format('d/m/Y') }}@if ($o->end_date) · fin le {{ $o->end_date->format('d/m/Y') }}@endif
                </p>
                <p class="mt-1 text-xs text-muted">{{ $o->tariffs_count }} tarif(s)</p>
            </a>
        @empty
            <x-empty-state icon="scroll" :title="__('Aucun arrêté fiscal')" class="sm:col-span-2 lg:col-span-3" />
        @endforelse
    </div>

    <x-panel show="showForm" :title="__('Nouvel arrêté fiscal')">
        <form wire:submit="save" class="space-y-4">
            <x-field :label="__('Titre')" for="title" error="title"><x-input id="title" wire:model="title" /></x-field>
            <x-field :label="__('Date d\'effet')" for="effective_date" error="effective_date"><x-input id="effective_date" type="date" wire:model="effective_date" /></x-field>
            <x-field :label="__('Notes')" for="notes" error="notes"><x-textarea id="notes" wire:model="notes" /></x-field>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model.live="copy_tariffs" class="rounded border-line text-accent focus:ring-gold">
                {{ __('Recopier les derniers tarifs connus (avec la nouvelle date d\'effet)') }}
            </label>
            @if ($copy_tariffs)
                <fieldset class="rounded-xl border border-line p-3">
                    <legend class="px-1 text-xs font-semibold text-muted">Rubriques à recopier (aucune cochée = toutes)</legend>
                    <div class="grid gap-1.5 sm:grid-cols-2">
                        @foreach ($rubrics as $r)
                            <label class="flex items-center gap-2 text-sm" wire:key="cr-{{ $r->id }}">
                                <input type="checkbox" value="{{ $r->id }}" wire:model="rubric_ids" class="rounded border-line text-accent focus:ring-gold">
                                <span class="truncate">{{ $r->label }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif
            <div class="flex justify-end gap-2 pt-2">
                <x-btn variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-btn>
                <x-btn type="submit" icon="check">{{ __('Créer l\'arrêté') }}</x-btn>
            </div>
        </form>
    </x-panel>
</div>
