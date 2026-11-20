@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        @if ($meta)
            <x-slot:actions>
                @if ($this->allowed('regie.exporter'))<x-btn variant="secondary" icon="file-spreadsheet" :href="route('documents.tgr-export', $query)">{{ __('Export Excel') }}</x-btn> @endif
                <x-btn variant="secondary" icon="printer" :href="route('documents.tgr-imprimer', $query)" target="_blank">{{ __('Imprimer') }}</x-btn>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if ($this->allowed('paiements.valider'))
        <form wire:submit="upload">
            <x-card :title="__('Charger un relevé « Activité mensuelle » (export GIR)')">
                <div class="grid items-end gap-3 sm:grid-cols-[1fr_auto]">
                    <x-field :label="__('Fichier Excel (.xls, .xlsx)')" for="tgr-file" error="file">
                        <input id="tgr-file" type="file" wire:model="file" accept=".xls,.xlsx,.xlsm"
                               class="block w-full text-sm file:me-3 file:rounded-full file:border-0 file:bg-navy file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
                    </x-field>
                    <x-btn type="submit" variant="gold" icon="upload" wire:loading.attr="disabled" wire:target="file,upload">{{ __('Lire le relevé') }}</x-btn>
                </div>
            </x-card>
        </form>
    @endif

    @if (! $meta)
        <x-card><x-empty-state icon="landmark" :title="__('Aucun relevé chargé')">{{ __('Chargez l’export Excel « Activité mensuelle » de GIR pour afficher les opérations encaissées par la TGR.') }}</x-empty-state></x-card>
    @else
        <section class="surface grid gap-x-6 gap-y-1 p-4 text-sm sm:grid-cols-3" aria-label="{{ __('Relevé') }}">
            <p><span class="text-muted">{{ __('Commune :') }}</span> <strong>{{ $meta['commune'] ?: '—' }}</strong></p>
            <p><span class="text-muted">{{ __('Régie :') }}</span> <strong>{{ $meta['regie'] ?: '—' }}</strong></p>
            <p><span class="text-muted">{{ __('Période :') }}</span> <strong>@if ($meta['periode_debut'])du {{ \Illuminate\Support\Carbon::parse($meta['periode_debut'])->format('d/m/Y') }} au {{ \Illuminate\Support\Carbon::parse($meta['periode_fin'])->format('d/m/Y') }}@else — @endif</strong></p>
        </section>

        <section class="grid grid-cols-2 gap-3 md:grid-cols-5" aria-label="{{ __('Totaux') }}">
            @foreach ([['Principal', $sums['principal']], ['Majorations', $sums['majoration']], ['Pénalités', $sums['penalites']], ['Astreintes', $sums['astreintes']]] as [$l, $v])
                <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ tr($l) }}</p><p class="mt-1 text-lg font-bold tabular-nums">{{ Format::money($v) }} DH</p></div>
            @endforeach
            <div class="surface col-span-2 p-4 md:col-span-1" style="border-color: var(--gold)"><p class="text-xs font-semibold text-muted">Total ({{ count($operations) }} op.)</p>
                <p class="mt-1 text-lg font-bold tabular-nums text-online">{{ Format::money($total) }} DH</p></div>
        </section>
        <p class="text-xs italic text-muted">{{ __('Arrêté à la somme de :') }} <strong class="not-italic text-ink">{{ $words }}</strong></p>

        <div class="grid gap-2 sm:grid-cols-[2fr_2fr_1fr_1fr_auto]">
            <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Partie versante, rubrique, référence…')" :aria-label="__('Rechercher')" />
            <x-select wire:model.live="rubric" :aria-label="__('Rubrique')"><option value="">{{ __('Toutes les rubriques') }}</option>@foreach ($rubrics as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach</x-select>
            <x-input type="date" wire:model.live="from" :aria-label="__('Du')" />
            <x-input type="date" wire:model.live="to" :aria-label="__('Au')" />
            <x-btn variant="secondary" icon="x" wire:click="resetFilters">{{ __('Effacer') }}</x-btn>
        </div>

        <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
            <x-card :padding="false">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                            <tr><th class="px-3 py-2 text-start">N°</th><th class="px-3 py-2 text-start">{{ __('Paiement') }}</th><th class="px-3 py-2 text-start">{{ __('Partie versante') }}</th>
                                <th class="px-3 py-2 text-start">{{ __('Rubrique') }}</th><th class="px-3 py-2 text-end">{{ __('Principal') }}</th><th class="px-3 py-2 text-end">{{ __('Maj. / pén.') }}</th><th class="px-3 py-2 text-end">{{ __('Total') }}</th></tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @forelse ($operations as $op)
                                <tr wire:key="op-{{ $op['num'] }}">
                                    <td class="px-3 py-2 text-xs text-muted">{{ $op['num'] }}</td>
                                    <td class="px-3 py-2"><p class="font-mono text-xs font-semibold">{{ $op['ref_paiement'] }}</p>
                                        <p class="text-xs text-muted">{{ $op['date'] ? \Illuminate\Support\Carbon::parse($op['date'])->format('d/m/Y') : '—' }} · {{ $op['mode_paiement'] }}</p></td>
                                    <td class="px-3 py-2"><p class="font-semibold">{{ $op['partie_versante'] ?: '—' }}</p>@if ($op['ref_creance'])<p class="text-xs text-muted">Créance {{ $op['ref_creance'] }}</p>@endif</td>
                                    <td class="px-3 py-2 text-xs"><p>{{ $op['libelle_rubrique'] }}</p><p class="font-mono text-muted">{{ $op['code_rubrique'] }}</p></td>
                                    <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($op['principal']) }}</td>
                                    <td class="px-3 py-2 text-end text-xs tabular-nums text-muted">{{ Format::money($op['majoration'] + $op['penalites'] + $op['astreintes']) }}</td>
                                    <td class="px-3 py-2 text-end font-semibold tabular-nums">{{ Format::money($op['total']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="p-4"><x-empty-state icon="search" :title="__('Aucune opération pour ces critères')" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
            <x-card :title="__('Par rubrique')" :padding="false">
                <ul class="divide-y divide-line text-sm">
                    @foreach ($byRubric as $r)
                        <li class="flex items-center gap-3 px-4 py-2.5"><span class="min-w-0 flex-1"><span class="block truncate font-semibold">{{ tr($r['label']) }}</span>
                            <span class="text-xs text-muted">{{ $r['code'] }} · {{ $r['n'] }} op.</span></span><span class="font-semibold tabular-nums">{{ Format::money($r['total']) }}</span></li>
                    @endforeach
                </ul>
            </x-card>
        </div>
    @endif
</div>
