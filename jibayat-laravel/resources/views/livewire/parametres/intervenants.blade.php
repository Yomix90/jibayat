<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        @if ($this->canWrite())
            <x-slot:actions><x-btn icon="plus" wire:click="create">{{ __('Ajouter') }}</x-btn></x-slot:actions>
        @endif
    </x-page-header>

    <div class="flex gap-1 rounded-full border border-line bg-white p-1 text-sm font-semibold" role="tablist">
        @foreach (['services' => 'Services ('.$services->count().')', 'employes' => 'Employés ('.$employees->count().')', 'valeurs' => 'Valeurs de timbres ('.$values->count().')'] as $key => $label)
            <button type="button" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" wire:click="setTab('{{ $key }}')"
                    @class(['flex-1 rounded-full px-3 py-2 transition', 'bg-navy text-white' => $tab === $key, 'text-muted hover:text-ink' => $tab !== $key])>{{ tr($label) }}</button>
        @endforeach
    </div>

    @php
        $rows = match ($tab) { 'services' => $services, 'employes' => $employees, default => $values };
    @endphp

    <x-card :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($rows as $row)
                <li class="flex flex-wrap items-center gap-3 px-4 py-3" wire:key="{{ $tab }}-{{ $row->id }}">
                    <div @class(['min-w-0 flex-1', 'opacity-60' => ! $row->is_active])>
                        @if ($tab === 'services')
                            <p class="font-semibold text-ink">{{ $row->name }}</p>
                            <p class="text-xs text-muted">{{ $row->employees_count }} employé(s)</p>
                        @elseif ($tab === 'employes')
                            <p class="font-semibold text-ink">{{ $row->last_name }} {{ $row->first_name }}</p>
                            <p class="text-xs text-muted">{{ $row->registration_number ? 'Matricule '.$row->registration_number.' · ' : '' }}{{ $row->service?->name ?? 'Sans service' }}</p>
                        @else
                            <p class="font-semibold text-ink">{{ $row->label }}</p>
                            <p class="text-xs text-muted">{{ ucfirst($row->type) }} · {{ number_format((float) $row->unit_value, 2, ',', ' ') }} DH × {{ $row->units_per_book }} par carnet
                                = <strong>{{ number_format((float) $row->unit_value * $row->units_per_book, 2, ',', ' ') }} DH</strong></p>
                        @endif
                    </div>
                    @if ($row->is_active)<x-badge color="green">{{ __('Actif') }}</x-badge>@else<x-badge>{{ __('Inactif') }}</x-badge>@endif
                    @if ($this->canWrite())
                        <div class="flex gap-1">
                            <x-btn variant="ghost" size="sm" icon="pencil" wire:click="edit({{ $row->id }})">{{ __('Modifier') }}</x-btn>
                            <x-btn variant="ghost" size="sm" wire:click="toggle({{ $row->id }})">{{ $row->is_active ? 'Désactiver' : 'Activer' }}</x-btn>
                            <x-btn variant="ghost" size="sm" icon="trash-2" wire:click="delete({{ $row->id }})" wire:confirm="Supprimer cet élément ?" :aria-label="__('Supprimer')" />
                        </div>
                    @endif
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="building-2" :title="__('Rien pour l\'instant')" /></li>
            @endforelse
        </ul>
    </x-card>

    <x-panel show="showForm" :title="($editingId ? 'Modifier' : 'Ajouter').' — '.['services' => 'service', 'employes' => 'employé', 'valeurs' => 'valeur de timbre'][$tab]">
        <form wire:submit="save" class="space-y-4">
            @if ($tab === 'services')
                <x-field :label="__('Nom du service')" for="f-name" error="form.name"><x-input id="f-name" wire:model="form.name" /></x-field>
            @elseif ($tab === 'employes')
                <div class="grid grid-cols-2 gap-3">
                    <x-field :label="__('Nom')" for="f-ln" error="form.last_name"><x-input id="f-ln" wire:model="form.last_name" /></x-field>
                    <x-field :label="__('Prénom')" for="f-fn" error="form.first_name"><x-input id="f-fn" wire:model="form.first_name" /></x-field>
                </div>
                <x-field :label="__('Matricule')" for="f-rn" error="form.registration_number"><x-input id="f-rn" wire:model="form.registration_number" /></x-field>
                <x-field :label="__('Service')" for="f-sv" error="form.service_id">
                    <x-select id="f-sv" wire:model="form.service_id">
                        <option value="">{{ __('— Aucun —') }}</option>
                        @foreach ($services->where('is_active', true) as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                    </x-select>
                </x-field>
            @else
                <x-field :label="__('Désignation')" for="f-lb" error="form.label"><x-input id="f-lb" wire:model="form.label" /></x-field>
                <x-field :label="__('Type')" for="f-tp" error="form.type">
                    <x-select id="f-tp" wire:model="form.type"><option value="timbre">{{ __('Timbre') }}</option><option value="ticket">{{ __('Ticket') }}</option><option value="vignette">{{ __('Vignette') }}</option></x-select>
                </x-field>
                <div class="grid grid-cols-2 gap-3">
                    <x-field :label="__('Valeur unitaire (DH)')" for="f-uv" error="form.unit_value"><x-input id="f-uv" type="number" step="0.01" min="0" wire:model="form.unit_value" /></x-field>
                    <x-field :label="__('Unités par carnet')" for="f-up" error="form.units_per_book"><x-input id="f-up" type="number" min="1" wire:model="form.units_per_book" /></x-field>
                </div>
            @endif
            <div class="flex justify-end gap-2 pt-2">
                <x-btn variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-btn>
                <x-btn type="submit" icon="check">{{ __('Enregistrer') }}</x-btn>
            </div>
        </form>
    </x-panel>
</div>
