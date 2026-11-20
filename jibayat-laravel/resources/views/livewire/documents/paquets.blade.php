<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>@if ($this->allowed('regie.ajouter'))<x-btn icon="plus" wire:click="create">{{ __('Réception d\'un paquet') }}</x-btn>@endif</x-slot:actions>
    </x-page-header>
    <x-select wire:model.live="status" class="sm:max-w-xs" :aria-label="__('Statut')"><option value="">{{ __('Tous les paquets') }}</option><option value="recu">{{ __('Reçus (fermés)') }}</option><option value="ouvert">{{ __('Ouverts') }}</option></x-select>
    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr><th class="px-4 py-2 text-start">{{ __('Paquet') }}</th><th class="px-4 py-2 text-start">{{ __('Valeur') }}</th><th class="px-4 py-2 text-start">{{ __('Numéros') }}</th><th class="px-4 py-2 text-start">{{ __('Carnets') }}</th><th class="px-4 py-2"></th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($packs as $p)
                        <tr wire:key="p-{{ $p->id }}">
                            <td class="px-4 py-2.5"><p class="font-mono font-bold">{{ $p->number }}</p><p class="text-xs text-muted">Reçu le {{ $p->received_on?->format('d/m/Y') }}</p></td>
                            <td class="px-4 py-2.5">{{ $p->stampValue?->label }}</td>
                            <td class="px-4 py-2.5 text-xs">{{ $p->first_number }} → {{ $p->last_number }}<br><span class="text-muted">{{ $p->quantity }} vignettes</span></td>
                            <td class="px-4 py-2.5">@if ($p->status === 'recu')<x-badge>{{ __('Fermé') }}</x-badge>@else<span class="text-xs">{{ $p->books_count }} : {{ $p->stock_count }} en stock · {{ $p->assigned_count }} affecté(s) · {{ $p->consumed_count }} consommé(s)</span>@endif</td>
                            <td class="px-4 py-2.5">
                                <div class="flex justify-end gap-1">
                                    @if ($p->status === 'recu' && $this->allowed('regie.modifier'))
                                        <x-btn variant="gold" size="sm" icon="package-open" wire:click="open({{ $p->id }})" wire:confirm="Ouvrir le paquet {{ $p->number }} et générer ses carnets ?">{{ __('Ouvrir') }}</x-btn>
                                        <x-btn variant="ghost" size="sm" icon="pencil" wire:click="edit({{ $p->id }})" aria-label="Modifier le paquet {{ $p->number }}" />
                                    @endif
                                    @if ($p->status === 'recu' && $this->allowed('regie.supprimer'))
                                        <x-btn variant="ghost" size="sm" icon="trash-2" wire:click="delete({{ $p->id }})" wire:confirm="Supprimer le paquet {{ $p->number }} ?" aria-label="Supprimer le paquet {{ $p->number }}" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4"><x-empty-state icon="package" :title="__('Aucun paquet')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-panel show="showForm" :title="$editingId ? 'Modifier le paquet' : 'Réception d’un paquet'">
        <form wire:submit="save" class="space-y-3">
            <x-field :label="__('Valeur (timbre / ticket)')" for="pk-value" error="form.stamp_value_id">
                <x-select id="pk-value" wire:model="form.stamp_value_id">@foreach ($values as $v)<option value="{{ $v->id }}">{{ $v->label }} — {{ $v->units_per_book }} unités par carnet</option>@endforeach</x-select>
            </x-field>
            <div class="grid grid-cols-2 gap-2">
                <x-field :label="__('N° du paquet')" for="pk-number" error="form.number"><x-input id="pk-number" wire:model="form.number" /></x-field>
                <x-field :label="__('Date de réception')" for="pk-date" error="form.received_on"><x-input id="pk-date" type="date" wire:model="form.received_on" /></x-field>
                <x-field :label="__('Premier n°')" for="pk-first" error="form.first_number"><x-input id="pk-first" type="number" min="0" wire:model="form.first_number" /></x-field>
                <x-field :label="__('Dernier n°')" for="pk-last" error="form.last_number"><x-input id="pk-last" type="number" min="0" wire:model="form.last_number" /></x-field>
            </div>
            <p class="text-xs text-muted">{{ __('Le nombre de vignettes est calculé (dernier − premier + 1). À l\'ouverture, le paquet est découpé en 10 carnets.') }}</p>
            <div class="flex justify-end gap-2"><x-btn variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-btn><x-btn type="submit" icon="check">{{ __('Enregistrer') }}</x-btn></div>
        </form>
    </x-panel>
</div>
