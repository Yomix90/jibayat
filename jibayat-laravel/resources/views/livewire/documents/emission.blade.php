@php use App\Support\Format; use App\Domain\Documents\Services\IssueSlipService; $months = IssueSlipService::MONTHS; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-select wire:model.live="year" :aria-label="__('Année')" class="!w-28">@foreach ($years as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach</x-select>
            @if ($this->allowed('config.gerer'))<x-btn variant="secondary" icon="settings" wire:click="openConfig">{{ __('1ère / 2ème partie') }}</x-btn> @endif
            @if ($this->allowed('emission.modifier'))<x-btn variant="secondary" icon="refresh-cw" wire:click="recalculate" wire:loading.attr="disabled">Recalculer {{ $year }}</x-btn> @endif
            @if ($this->allowed('emission.ajouter'))<x-btn variant="gold" icon="upload" wire:click="openImport">{{ __('Importer un bordereau GIR') }}</x-btn> @endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="Année {{ $year }}">
        @foreach ([['Total encaissé '.$year, Format::money($summary['total_annee']).' DH'], ['Mois importés', $summary['nb_mois_importes'].' / 12'],
                   ['Bordereaux émis', $summary['nb_bordereaux']], ['Moyenne mensuelle', Format::money($summary['moyenne_mensuelle']).' DH']] as [$l, $v])
            <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ tr($l) }}</p><p class="mt-1 text-lg font-bold tabular-nums">{{ $v }}</p></div>
        @endforeach
    </section>

    <section aria-label="{{ __('Mois') }}">
        <x-section-title :count="$summary['nb_mois_importes']">Mois de l'exercice {{ $year }}</x-section-title>
        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ($summary['mois'] as $m)
                @if ($m['statut'] === 'importe')
                    <button type="button" wire:click="$set('month', {{ $m['mois'] }})" wire:key="m-{{ $m['mois'] }}"
                            class="pressable surface p-3 text-start {{ $month === $m['mois'] ? 'ring-2 ring-gold' : '' }}">
                        <p class="text-xs font-bold uppercase tracking-[.06em] text-muted">{{ tr($m['nom']) }}</p>
                        <p class="mt-1 font-bold tabular-nums">{{ Format::money($m['total']) }}</p>
                        <p class="text-xs text-muted">{{ $m['nb_rubriques'] }} bordereau(x)</p>
                    </button>
                @else
                    <button type="button" wire:key="m-{{ $m['mois'] }}" @if ($this->allowed('emission.ajouter')) wire:click="openImport({{ $m['mois'] }})" @else disabled @endif
                            class="pressable rounded-2xl border border-dashed border-line bg-white/60 p-3 text-start">
                        <p class="text-xs font-bold uppercase tracking-[.06em] text-muted">{{ tr($m['nom']) }}</p>
                        <p class="mt-1 text-xs text-muted">{{ __('Non importé') }}</p>
                    </button>
                @endif
            @endforeach
        </div>
    </section>

    @if ($statement)
        <x-card :padding="false">
            <x-slot:title>{{ tr($months[$statement->month]) }} {{ $statement->year }} · {{ $slips->count() }} bordereau(x)</x-slot:title>
            <x-slot:actions>
                <x-btn variant="secondary" size="sm" icon="printer" :href="route('documents.emission-imprimer-mois', [$statement->year, $statement->month])" target="_blank">{{ __('Imprimer le mois') }}</x-btn>
                @if ($this->allowed('emission.ajouter'))<x-btn variant="secondary" size="sm" icon="plus" wire:click="$set('showAdd', true)">{{ __('Rubrique') }}</x-btn> @endif
                @if ($this->allowed('emission.supprimer'))<x-btn variant="ghost" size="sm" icon="trash-2" wire:click="deleteMonth" wire:confirm="Supprimer l'import de {{ tr($months[$statement->month]) }} {{ $statement->year }} et ses bordereaux ? Les numéros et reports de l'année seront recalculés.">{{ __('Supprimer le mois') }}</x-btn> @endif
            </x-slot:actions>
            <div class="grid gap-x-6 gap-y-1 border-b border-line px-4 py-3 text-xs sm:grid-cols-4">
                <p><span class="text-muted">{{ __('Source :') }}</span> {{ $statement->source_file }}</p>
                <p><span class="text-muted">{{ __('Total des émissions collectives :') }}</span> <strong class="tabular-nums">{{ Format::money($globals['total_present_global']) }}</strong></p>
                <p><span class="text-muted">1ère partie ({{ $globals['premiere_partie_mode'] }}) :</span> <strong class="tabular-nums">{{ Format::money($globals['premiere_partie_global']) }}</strong></p>
                <p><span class="text-muted">2ème partie ({{ $globals['deuxieme_partie_mode'] }}) :</span> <strong class="tabular-nums">{{ $globals['deuxieme_partie_global'] === null ? '—' : Format::money($globals['deuxieme_partie_global']) }}</strong></p>
                <p class="sm:col-span-4"><span class="text-muted">Cumul de l'année à fin {{ mb_strtolower($months[$statement->month]) }} :</span> <strong class="tabular-nums">{{ Format::money($cumulative) }} DH</strong></p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                        <tr><th class="px-3 py-2 text-start">N°</th><th class="px-3 py-2 text-start">{{ __('Rubrique') }}</th><th class="px-3 py-2 text-end">{{ __('Report antérieur') }}</th>
                            <th class="px-3 py-2 text-end">{{ __('Présent bordereau') }}</th><th class="px-3 py-2 text-end">{{ __('Total') }}</th><th class="px-3 py-2"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($slips as $s)
                            <tr wire:key="s-{{ $s->id }}">
                                <td class="px-3 py-2 font-mono text-xs font-bold">{{ $s->number }}</td>
                                <td class="px-3 py-2"><p class="font-semibold">{{ $s->rubric }}</p><p class="text-xs text-muted"><span class="font-mono">{{ $s->budget_code }}</span> · {{ $s->title }}</p></td>
                                <td class="px-3 py-2 text-end tabular-nums text-muted">{{ Format::money($s->carried_forward) }}</td>
                                <td class="px-3 py-2 text-end tabular-nums">
                                    @if ($editCode === $s->budget_code)
                                        <form wire:submit="saveAmount" class="flex items-center justify-end gap-1">
                                            <x-input type="number" step="0.01" min="0" wire:model="editAmount" class="!w-32 text-end" :aria-label="__('Nouveau montant')" autofocus />
                                            <x-btn type="submit" variant="gold" size="sm" icon="check" :aria-label="__('Enregistrer')" />
                                            <x-btn variant="ghost" size="sm" icon="x" wire:click="$set('editCode', null)" :aria-label="__('Annuler')" />
                                        </form>
                                        @error('editAmount')<p class="text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                                    @else
                                        <span class="font-semibold">{{ Format::money($s->current_amount) }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-end font-bold tabular-nums">{{ Format::money($s->total) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-end">
                                    <x-btn variant="ghost" size="sm" icon="printer" :href="route('documents.emission-imprimer', $s)" target="_blank" aria-label="Imprimer le bordereau {{ $s->number }}" />
                                    @if ($this->allowed('emission.modifier'))<x-btn variant="ghost" size="sm" icon="pencil" wire:click="startEdit('{{ $s->budget_code }}', '{{ (float) $s->current_amount }}')" :aria-label="__('Modifier le montant')" />@endif
                                    @if ($this->allowed('emission.supprimer'))<x-btn variant="ghost" size="sm" icon="trash-2" wire:click="deleteLine('{{ $s->budget_code }}')" wire:confirm="Retirer la rubrique {{ $s->budget_code }} ({{ $s->rubric }}) de {{ tr($months[$statement->month]) }} ?" :aria-label="__('Retirer la rubrique')" />@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-gold bg-bg font-bold"><tr><td colspan="2" class="px-3 py-2">{{ __('Total du mois') }}</td>
                        <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($slips->sum('carried_forward')) }}</td>
                        <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($slips->sum('current_amount')) }}</td>
                        <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($slips->sum('total')) }}</td><td></td></tr></tfoot>
                </table>
            </div>
        </x-card>
    @elseif ($month)
        <x-card><x-empty-state icon="stamp" title="{{ tr($months[$month]) }} {{ $year }} n’est pas encore importé" /></x-card>
    @endif

    <x-panel show="showImport" :title="__('Importer un bordereau de versement GIR')">
        <form wire:submit="import" class="space-y-3">
            <x-field :label="__('Fichier Excel (.xls, .xlsx)')" for="gir-file" error="file">
                <input id="gir-file" type="file" wire:model="file" accept=".xls,.xlsx"
                       class="block w-full text-sm file:me-3 file:rounded-full file:border-0 file:bg-navy file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
            </x-field>
            <div class="grid grid-cols-2 gap-3">
                <x-field label="Mois (vide = détecté)" for="i-month" error="importMonth">
                    <x-select id="i-month" wire:model="importMonth"><option value="">{{ __('Automatique') }}</option>@foreach ($months as $n => $name)<option value="{{ $n }}">{{ $name }}</option>@endforeach</x-select>
                </x-field>
                <x-field :label="__('Année')" for="i-year" error="importYear"><x-input id="i-year" type="number" wire:model="importYear" /></x-field>
            </div>
            <x-field :label="__('Montant total des émissions collectives (facultatif)')" for="i-coll" error="collective" :hint="__('Remplace le TOTAL GÉNÉRAL lu dans le fichier.')">
                <x-input id="i-coll" type="number" step="0.01" min="0" wire:model="collective" />
            </x-field>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="replace" class="rounded border-line text-accent focus:ring-gold"> {{ __('Remplacer l\'import existant du mois') }}</label>
            <div class="flex justify-end gap-2 pt-2">
                <x-btn variant="secondary" wire:click="$set('showImport', false)">{{ __('Annuler') }}</x-btn>
                <x-btn type="submit" variant="gold" icon="upload" wire:loading.attr="disabled" wire:target="file,import">{{ __('Importer et générer') }}</x-btn>
            </div>
        </form>
    </x-panel>

    <x-panel show="showConfig" title="Configuration {{ $year }} : 1ère et 2ème partie">
        <form wire:submit="saveConfig" class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <x-field :label="__('1ère partie')" for="c-p1"><x-select id="c-p1" wire:model.live="config.first_part_mode"><option value="auto">{{ __('Automatique (cumul des mois antérieurs)') }}</option><option value="manuel">{{ __('Montant saisi') }}</option></x-select></x-field>
                <x-field :label="__('Montant')" for="c-v1" error="config.first_part_value"><x-input id="c-v1" type="number" step="0.01" min="0" wire:model="config.first_part_value" :disabled="($config['first_part_mode'] ?? 'auto') !== 'manuel'" /></x-field>
                <x-field :label="__('2ème partie')" for="c-p2"><x-select id="c-p2" wire:model.live="config.second_part_mode"><option value="auto">{{ __('Automatique (total du mois)') }}</option><option value="manuel">{{ __('Montant saisi') }}</option><option value="vide">{{ __('Vide') }}</option></x-select></x-field>
                <x-field :label="__('Montant')" for="c-v2" error="config.second_part_value"><x-input id="c-v2" type="number" step="0.01" min="0" wire:model="config.second_part_value" :disabled="($config['second_part_mode'] ?? 'vide') !== 'manuel'" /></x-field>
            </div>
            <x-field :label="__('Notes')" for="c-notes"><x-textarea id="c-notes" rows="2" wire:model="config.notes" /></x-field>
            <p class="text-xs text-muted">L'enregistrement recalcule tous les bordereaux de l'année {{ $year }}.</p>
            <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('showConfig', false)">{{ __('Annuler') }}</x-btn><x-btn type="submit" variant="gold" icon="check">{{ __('Enregistrer') }}</x-btn></div>
        </form>
    </x-panel>

    <x-panel show="showAdd" :title="__('Ajouter une rubrique')">
        <form wire:submit="addLine" class="space-y-3">
            <x-field :label="__('Code budgétaire')" for="a-code" error="addCode">
                <x-input id="a-code" wire:model.live.debounce.300ms="addCode" list="codes" inputmode="numeric" />
                <datalist id="codes">@foreach (IssueSlipService::RUBRICS as $code => [$r, $t])<option value="{{ $code }}">{{ $r }} — {{ $t }}</option>@endforeach</datalist>
            </x-field>
            @if (isset(IssueSlipService::RUBRICS[$addCode]))
                <p class="text-xs text-muted">{{ IssueSlipService::RUBRICS[$addCode][1] }}</p>
            @else
                <x-field :label="__('Nature de la recette')" for="a-nature" error="addNature"><x-input id="a-nature" wire:model="addNature" /></x-field>
            @endif
            <x-field :label="__('Montant (DH)')" for="a-amount" error="addAmount"><x-input id="a-amount" type="number" step="0.01" min="0" wire:model="addAmount" /></x-field>
            <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('showAdd', false)">{{ __('Annuler') }}</x-btn><x-btn type="submit" variant="gold" icon="plus">{{ __('Ajouter') }}</x-btn></div>
        </form>
    </x-panel>
</div>
