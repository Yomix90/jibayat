<div class="space-y-6">
    <x-page-header route="parametres.arretes" :title="$taxOrder->number.' — '.$taxOrder->title"
                   :subtitle="'Effet le '.$taxOrder->effective_date->format('d/m/Y').($taxOrder->end_date ? ' · fin le '.$taxOrder->end_date->format('d/m/Y') : '')">
        <x-slot:crumb>{{ $taxOrder->number }}</x-slot:crumb>
        <x-slot:actions>
            @if ($taxOrder->status === 'actif')<x-badge color="gold">{{ __('Arrêté actif') }}</x-badge>@else<x-badge>{{ $taxOrder->status === 'remplace' ? 'Remplacé' : ucfirst($taxOrder->status) }}</x-badge>@endif
            <x-btn variant="secondary" size="sm" icon="chevron-left" :href="route('parametres.arretes')">{{ __('Tous les arrêtés') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    @if ($taxOrder->notes)
        <p class="surface p-4 text-sm text-muted">{{ $taxOrder->notes }}</p>
    @endif

    @if ($this->canWrite())
        <div class="flex flex-wrap gap-2">
            <x-btn variant="secondary" size="sm" icon="plus" wire:click="$set('showRubricForm', true)">{{ __('Ajouter une rubrique') }}</x-btn>
            <x-btn variant="secondary" size="sm" icon="copy" wire:click="loadDefaults"
                   wire:confirm="Ajouter les tarifs types de référence manquants (TNB, boissons, stationnement, ODP, fourrière) ?">{{ __('Tarifs types') }}</x-btn>
            <x-btn variant="ghost" size="sm" icon="trash-2" wire:click="clearTariffs"
                   wire:confirm="Supprimer TOUS les tarifs de cet arrêté ?">{{ __('Vider les tarifs') }}</x-btn>
            <x-btn variant="ghost" size="sm" icon="trash-2" class="!text-red-600" wire:click="$set('showDeleteForm', true)">{{ __('Supprimer l\'arrêté') }}</x-btn>
        </div>
    @endif

    @forelse ($groups as $module => $tariffs)
        @php($rubric = $tariffs->first()->rubric)
        <x-card :padding="false" wire:key="grp-{{ $module }}">
            <x-slot:title>{{ $rubric->label }} <span class="ms-1 font-mono text-xs font-medium text-muted">{{ $module }} · {{ $rubric->code }}</span></x-slot:title>
            @if ($this->canWrite())
                <x-slot:actions>
                    <x-btn variant="secondary" size="sm" icon="plus" wire:click="newTariff({{ $rubric->id }})">{{ __('Tarif') }}</x-btn>
                    <x-btn variant="ghost" size="sm" icon="trash-2" wire:click="removeModule('{{ $module }}')"
                           wire:confirm="Supprimer les {{ $tariffs->count() }} tarif(s) du module {{ $module }} de cet arrêté ?">{{ __('Retirer') }}</x-btn>
                </x-slot:actions>
            @endif
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                        <tr>
                            <th class="px-4 py-2 text-start">{{ __('Libellé') }}</th>
                            <th class="px-4 py-2 text-end">{{ __('Valeur') }}</th>
                            <th class="px-4 py-2 text-start">{{ __('Unité') }}</th>
                            <th class="px-4 py-2 text-start">{{ __('Surface') }}</th>
                            <th class="px-4 py-2 text-start">{{ __('Période') }}</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($tariffs as $t)
                            <tr wire:key="t-{{ $t->id }}" @class(['opacity-60' => ! $t->is_active])>
                                <td class="px-4 py-2.5">
                                    <p class="font-semibold text-ink">{{ $t->label }}</p>
                                    <p class="font-mono text-[11px] text-muted">{{ $t->code }}</p>
                                </td>
                                <td class="px-4 py-2.5 text-end font-semibold tabular-nums">{{ rtrim(rtrim(number_format((float) $t->value, 4, ',', ' '), '0'), ',') }}</td>
                                <td class="px-4 py-2.5">{{ $t->unit }}</td>
                                <td class="px-4 py-2.5 text-xs text-muted">
                                    @if ((float) $t->surface_min > 0 || $t->surface_max !== null)
                                        {{ number_format((float) $t->surface_min, 0, ',', ' ') }} → {{ $t->surface_max !== null ? number_format((float) $t->surface_max, 0, ',', ' ') : '∞' }} m²
                                    @else — @endif
                                </td>
                                <td class="px-4 py-2.5 text-xs text-muted">
                                    {{ $t->start_date->format('d/m/Y') }} → {{ $t->end_date?->format('d/m/Y') ?? '…' }}
                                    @unless ($t->is_active)<x-badge class="ms-1">{{ __('Inactif') }}</x-badge>@endunless
                                </td>
                                <td class="px-4 py-2.5 text-end">
                                    @if ($this->canWrite())
                                        <div class="flex justify-end gap-1">
                                            <x-btn variant="ghost" size="sm" icon="pencil" wire:click="editTariff({{ $t->id }})" aria-label="Modifier {{ $t->label }}" />
                                            <x-btn variant="ghost" size="sm" icon="trash-2" wire:click="deleteTariff({{ $t->id }})"
                                                   wire:confirm="Supprimer le tarif « {{ $t->label }} » ?" aria-label="Supprimer {{ $t->label }}" />
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @empty
        <x-empty-state icon="scroll" :title="__('Aucun tarif dans cet arrêté')">{{ __('Ajoutez une rubrique ou chargez les tarifs types.') }}</x-empty-state>
    @endforelse

    @if ($history->isNotEmpty())
        <details class="surface">
            <summary class="cursor-pointer px-4 py-3 text-sm font-bold text-ink">Historique des tarifs des autres arrêtés ({{ $history->count() }})</summary>
            <div class="overflow-x-auto border-t border-line">
                <table class="w-full text-xs">
                    <tbody class="divide-y divide-line">
                        @foreach ($history as $h)
                            <tr wire:key="h-{{ $h->id }}">
                                <td class="px-4 py-2 font-mono">{{ $h->rubric->module }}</td>
                                <td class="px-4 py-2">{{ $h->label }}</td>
                                <td class="px-4 py-2 text-end tabular-nums">{{ rtrim(rtrim(number_format((float) $h->value, 4, ',', ' '), '0'), ',') }} {{ $h->unit }}</td>
                                <td class="px-4 py-2 text-muted">{{ $h->taxOrder?->number ?? 'sans arrêté' }}</td>
                                <td class="px-4 py-2 text-muted">{{ $h->start_date->format('d/m/Y') }} → {{ $h->end_date?->format('d/m/Y') ?? '…' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

    <x-panel show="showTariffForm" :title="$tariffId ? 'Modifier le tarif' : 'Nouveau tarif'">
        <form wire:submit="saveTariff" class="space-y-4">
            @unless ($tariffId)
                <x-field :label="__('Libellé')" for="label" error="label" :hint="__('Pour la TNB, indiquez la zone (« Zone A »…) : le calcul s\'en sert.')">
                    <x-input id="label" wire:model="label" />
                </x-field>
            @else
                <p class="text-sm font-semibold text-ink">{{ tr($label) }}</p>
            @endunless
            <div class="grid grid-cols-2 gap-3">
                <x-field :label="__('Valeur')" for="value" error="value"><x-input id="value" type="number" step="0.0001" min="0" wire:model="value" /></x-field>
                <x-field :label="__('Unité')" for="unit" error="unit">
                    <x-input id="unit" wire:model="unit" list="units" />
                    <datalist id="units">
                        @foreach (['DH', '%', 'DH/m²', 'DH/an', 'DH/mois', 'DH/jour', 'DH/trimestre', 'DH/m²/an', 'DH/m²/mois'] as $u)<option value="{{ $u }}">@endforeach
                    </datalist>
                </x-field>
                <x-field :label="__('Date de début')" for="start_date" error="start_date"><x-input id="start_date" type="date" wire:model="start_date" /></x-field>
                <x-field :label="__('Date de fin')" for="end_date" error="end_date"><x-input id="end_date" type="date" wire:model="end_date" /></x-field>
                <x-field :label="__('Surface min (m²)')" for="surface_min" error="surface_min"><x-input id="surface_min" type="number" step="0.01" min="0" wire:model="surface_min" /></x-field>
                <x-field :label="__('Surface max (m²)')" for="surface_max" error="surface_max"><x-input id="surface_max" type="number" step="0.01" min="0" wire:model="surface_max" /></x-field>
            </div>
            @unless ($tariffId)
                <p class="text-xs text-muted">{{ __('Le tarif ouvert de même libellé dans cette rubrique sera clôturé à la date de début.') }}</p>
            @endunless
            <div class="flex justify-end gap-2 pt-2">
                <x-btn variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-btn>
                <x-btn type="submit" icon="check">{{ __('Enregistrer') }}</x-btn>
            </div>
        </form>
    </x-panel>

    <x-panel show="showRubricForm" :title="__('Ajouter une rubrique à l\'arrêté')">
        <form wire:submit="addRubric" class="space-y-4">
            <x-field :label="__('Rubrique')" for="add_rubric_id" error="add_rubric_id">
                <x-select id="add_rubric_id" wire:model="add_rubric_id">
                    <option value="">{{ __('— Choisir —') }}</option>
                    @foreach ($missingRubrics as $r)<option value="{{ $r->id }}">{{ $r->module }} — {{ $r->label }}</option>@endforeach
                </x-select>
            </x-field>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="add_copy" class="rounded border-line text-accent focus:ring-gold">
                {{ __('Recopier ses derniers tarifs (sinon un tarif à 0 à compléter)') }}
            </label>
            <div class="flex justify-end gap-2 pt-2">
                <x-btn variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-btn>
                <x-btn type="submit" icon="plus">{{ __('Ajouter') }}</x-btn>
            </div>
        </form>
    </x-panel>

    <x-panel show="showDeleteForm" title="Supprimer l'arrêté {{ $taxOrder->number }}">
        <form wire:submit="deleteOrder" class="space-y-4">
            <p class="text-sm text-muted">{{ __('Tous ses tarifs seront supprimés. Si c\'est l\'arrêté actif, l\'arrêté le plus récent redeviendra actif. Cette action est journalisée.') }}</p>
            <x-field :label="__('Motif (obligatoire)')" for="delete_reason" error="delete_reason"><x-input id="delete_reason" wire:model="delete_reason" /></x-field>
            <div class="flex justify-end gap-2 pt-2">
                <x-btn variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-btn>
                <x-btn type="submit" variant="danger" icon="trash-2">{{ __('Supprimer définitivement') }}</x-btn>
            </div>
        </form>
    </x-panel>
</div>
