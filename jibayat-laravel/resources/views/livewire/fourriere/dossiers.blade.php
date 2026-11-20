@php use App\Support\Format; use App\Domain\Fourriere\Services\ImpoundCalculator; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-btn variant="secondary" icon="printer" :href="route('fourriere.imprimer-liste', ['q' => $search, 'statut' => $status, 'type' => $type])" target="_blank">{{ __('Imprimer') }}</x-btn>
            @if ($this->allowed('fourriere.ajouter'))<x-btn icon="plus" wire:click="create">{{ __('Mise en fourrière') }}</x-btn>@endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="{{ __('Indicateurs') }}">
        @foreach ([
            ['car', 'Véhicules au parc', $stats['actifs'], $stats['en_fourriere'].' en fourrière · '.$stats['attente_sortie'].' à sortir', '#3F3F46'],
            ['banknote', 'Montant dû (parc)', Format::money($stats['total_du']), 'DH', '#DC2626'],
            ['clock', 'Plus de 265 jours', $stats['plus265'], 'dont '.$stats['plus365'].' de plus de 365 jours', '#C9A13B'],
            ['key-round', 'Sortis', $stats['sortie'], null, '#16A34A'],
        ] as [$icon, $label, $value, $sub, $color])
            <div class="surface p-4">
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-lg text-white" style="background: {{ $color }}"><x-svg-icon :name="$icon" class="h-4 w-4" /></span>
                    <p class="text-xs font-semibold text-muted">{{ tr($label) }}</p>
                </div>
                <p class="mt-2 text-2xl font-bold tabular-nums text-ink">{{ $value }}</p>
                @if ($sub)<p class="text-xs font-semibold text-muted">{{ $sub }}</p>@endif
            </div>
        @endforeach
    </section>

    <div class="grid gap-2 sm:grid-cols-3">
        <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Immatriculation, n° de dépôt, propriétaire…')" :aria-label="__('Rechercher')" />
        <x-select wire:model.live="status" :aria-label="__('Statut')">
            <option value="">{{ __('Tous les statuts') }}</option>
            @foreach (ImpoundCalculator::STATUSES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
        </x-select>
        <x-select wire:model.live="type" :aria-label="__('Type de véhicule')">
            <option value="">{{ __('Tous les types') }}</option>
            @foreach ($types as $t)<option value="{{ $t->label }}">{{ $t->label }}</option>@endforeach
        </x-select>
    </div>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr><th class="px-4 py-2.5 text-start">{{ __('Véhicule') }}</th><th class="px-4 py-2.5 text-start">{{ __('Entrée') }}</th><th class="px-4 py-2.5 text-end">{{ __('Jours') }}</th>
                        <th class="px-4 py-2.5 text-end">{{ __('Montant dû') }}</th><th class="px-4 py-2.5 text-start">{{ __('Statut') }}</th><th class="px-4 py-2.5"></th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($rows as $r)
                        @php($c = $r['case'])
                        <tr wire:key="c-{{ $c->id }}">
                            <td class="px-4 py-3">
                                <p class="font-mono font-bold text-ink" dir="ltr">{{ $c->registration ?: '—' }}</p>
                                <p class="text-xs text-muted">Dossier {{ $c->number }}{{ $c->deposit_number ? ' · dépôt '.$c->deposit_number : '' }} · {{ $c->vehicle_type }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <p class="font-semibold text-ink">{{ $c->impounded_on?->format('d/m/Y') }}</p>
                                <p class="text-muted">{{ $c->depositor }}{{ $c->reason ? ' · '.$c->reason : '' }}</p>
                            </td>
                            <td class="px-4 py-3 text-end">
                                <span class="font-bold tabular-nums">{{ $r['days'] }}</span>
                                @if ($c->status !== 'sortie' && $r['plus365'])<x-badge color="red" class="ms-1">+365</x-badge>@elseif ($c->status !== 'sortie' && $r['plus265'])<x-badge color="gold" class="ms-1">+265</x-badge>@endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-end font-semibold tabular-nums">{{ $c->status === 'sortie' ? '—' : Format::money($r['amount']).' DH' }}</td>
                            <td class="px-4 py-3">
                                <x-badge :color="['en_fourriere' => 'orange', 'en_attente_sortie' => 'gold', 'sortie' => 'green'][$c->status] ?? 'gray'">{{ ImpoundCalculator::STATUSES[$c->status] ?? $c->status }}</x-badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <x-btn variant="ghost" size="sm" icon="file-text" :href="route('fourriere.fiche', ['dossier' => $c->id])">{{ __('Fiche') }}</x-btn>
                                    @if ($this->allowed('bulletins.creer') && $c->status === 'en_fourriere')
                                        <x-btn variant="gold" size="sm" icon="hand-coins" :href="route('fourriere.encaisser', ['dossier' => $c->id])">{{ __('Encaisser') }}</x-btn>
                                    @endif
                                    @if ($this->allowed('fourriere.modifier') && $c->status !== 'sortie')
                                        <x-btn variant="ghost" size="sm" icon="pencil" wire:click="edit({{ $c->id }})" aria-label="Modifier le dossier {{ $c->number }}" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-4"><x-empty-state icon="car" :title="__('Aucun dossier')">{{ __('Enregistrez une mise en fourrière ou modifiez les filtres.') }}</x-empty-state></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-panel show="showForm" :title="$editingId ? 'Modifier le dossier' : 'Mise en fourrière'" max-width="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-3">
                <x-field :label="__('N° de dossier')" for="f-number" error="form.number"><x-input id="f-number" wire:model="form.number" /></x-field>
                <x-field :label="__('N° de dépôt')" for="f-dep" error="form.deposit_number"><x-input id="f-dep" wire:model="form.deposit_number" /></x-field>
                <x-field :label="__('Date de mise en fourrière')" for="f-date" error="form.impounded_on"><x-input id="f-date" type="date" wire:model="form.impounded_on" /></x-field>
                <x-field :label="__('Immatriculation')" for="f-reg" error="form.registration"><x-input id="f-reg" dir="ltr" wire:model="form.registration" /></x-field>
                <x-field :label="__('Type (tarif journalier de l\'arrêté)')" for="f-type" error="form.vehicle_type" class="sm:col-span-2">
                    <x-select id="f-type" wire:model="form.vehicle_type">
                        <option value="">{{ __('— Choisir —') }}</option>
                        @foreach ($types as $t)<option value="{{ $t->label }}">{{ $t->label }} ({{ Format::money($t->daily_rate) }} DH/jour)</option>@endforeach
                    </x-select>
                </x-field>
                <x-field :label="__('Déposant')" for="f-depositor" error="form.depositor">
                    <x-input id="f-depositor" wire:model="form.depositor" list="depositors" :placeholder="__('Police, gendarmerie…')" />
                    <datalist id="depositors">@foreach ($depositors as $d)<option value="{{ $d->label }}"></option>@endforeach</datalist>
                </x-field>
                <x-field :label="__('Motif')" for="f-reason" error="form.reason">
                    <x-input id="f-reason" wire:model="form.reason" list="reasons" />
                    <datalist id="reasons">@foreach ($reasons as $m)<option value="{{ $m->label }}"></option>@endforeach</datalist>
                </x-field>
                <x-field :label="__('Frais de remorquage (DH)')" for="f-tow" error="form.towing_fee"><x-input id="f-tow" type="number" step="0.01" min="0" wire:model="form.towing_fee" /></x-field>
            </div>
            <x-field :label="__('Notes')" for="f-notes" error="form.notes"><x-input id="f-notes" wire:model="form.notes" /></x-field>
            <div class="flex justify-end gap-2 pt-2">
                <x-btn variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-btn>
                <x-btn type="submit" icon="check">{{ __('Enregistrer') }}</x-btn>
            </div>
        </form>
    </x-panel>
</div>
