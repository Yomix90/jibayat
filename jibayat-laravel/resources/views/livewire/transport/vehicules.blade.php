@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-btn variant="secondary" icon="printer" :href="route('transport.imprimer-liste', ['q' => $search, 'situation' => $situation])" target="_blank">{{ __('Imprimer') }}</x-btn>
            @if ($this->allowed('transport.ajouter'))<x-btn icon="plus" wire:click="create">{{ __('Nouveau véhicule') }}</x-btn>@endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="{{ __('Indicateurs') }}">
        @foreach ([
            ['bus', 'Véhicules', $kpi['total'], null, '#5B7590'],
            ['triangle-alert', 'Trimestres non déclarés', $kpi['unpaid'], Format::money($kpi['amount_due']).' DH dus', '#DC2626'],
            ['clock', 'En attente régisseur', $kpi['pending'], Format::money($kpi['amount_pending']).' DH', '#C9A13B'],
            ['check', 'À jour', $kpi['up_to_date'], null, '#16A34A'],
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

    <div class="grid gap-2 sm:grid-cols-2">
        <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Immatriculation, propriétaire, CIN…')" :aria-label="__('Rechercher')" />
        <x-select wire:model.live="situation" :aria-label="__('Situation')">
            <option value="">{{ __('Toutes les situations') }}</option><option value="impaye">{{ __('Trimestres non déclarés') }}</option>
            <option value="en_attente">{{ __('En attente régisseur') }}</option><option value="ajour">{{ __('À jour') }}</option>
        </x-select>
    </div>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr><th class="px-4 py-2.5 text-start">{{ __('Véhicule') }}</th><th class="px-4 py-2.5 text-start">{{ __('Propriétaire') }}</th>
                        <th class="px-4 py-2.5 text-end">{{ __('Par trimestre') }}</th><th class="px-4 py-2.5 text-start">{{ __('Situation') }}</th><th class="px-4 py-2.5"></th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($rows as $r)
                        @php($v = $r['vehicle'])
                        <tr wire:key="v-{{ $v->id }}">
                            <td class="px-4 py-3">
                                <p class="font-mono font-bold text-ink" dir="ltr">{{ $v->registration ?: '—' }}</p>
                                <p class="text-xs text-muted">N° {{ $v->number }} · {{ $v->type }}{{ $v->authorization_number ? ' · agrément '.$v->authorization_number : '' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-ink">{{ $v->taxpayer?->displayName() }}</p>
                                <p class="text-xs text-muted">@if ($v->taxpayer?->displayNameAr())<span class="font-ar" lang="ar">{{ $v->taxpayer->displayNameAr() }}</span> · @endif{{ $v->taxpayer?->cin }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-end">
                                <p class="font-semibold tabular-nums">{{ Format::money($r['per_quarter']) }} DH</p>
                                <p class="text-[11px] text-muted">stat. {{ Format::money($r['sta']) }} · TPV {{ Format::money($r['tpv']) }}</p>
                            </td>
                            <td class="px-4 py-3">
                                @if ($r['nb_non_paye'] === 0)
                                    <x-badge color="green">{{ __('À jour') }}</x-badge>
                                @else
                                    <p class="flex flex-wrap gap-1">
                                        @if ($r['nb_non_declares'])<x-badge color="red">{{ $r['nb_non_declares'] }} trim. · {{ Format::money($r['montant_non_declare']) }} DH</x-badge>@endif
                                        @if ($r['nb_emises'])<x-badge color="gold">{{ $r['nb_emises'] }} en attente</x-badge>@endif
                                    </p>
                                    <p class="mt-0.5 text-xs text-muted">Années : {{ implode(', ', $r['annees_non_payees']) }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <x-btn variant="ghost" size="sm" icon="file-text" :href="route('transport.fiche', ['vehicule' => $v->id])">{{ __('Fiche') }}</x-btn>
                                    @if ($this->allowed('bulletins.creer') && $r['nb_non_declares'] > 0)
                                        <x-btn variant="gold" size="sm" icon="hand-coins" :href="route('transport.encaisser', ['vehicule' => $v->id])">{{ __('Encaisser') }}</x-btn>
                                    @endif
                                    @if ($this->allowed('transport.modifier'))<x-btn variant="ghost" size="sm" icon="pencil" wire:click="edit({{ $v->id }})" aria-label="Modifier {{ $v->registration }}" />@endif
                                    @if ($this->allowed('transport.supprimer'))
                                        <x-btn variant="ghost" size="sm" icon="trash-2" wire:click="delete({{ $v->id }})" wire:confirm="Retirer le véhicule {{ $v->registration }} de la liste ?" aria-label="Supprimer {{ $v->registration }}" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4"><x-empty-state icon="bus" :title="__('Aucun véhicule')">{{ __('Ajoutez un véhicule ou modifiez les filtres.') }}</x-empty-state></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-panel show="showForm" :title="$editingId ? 'Modifier le véhicule' : 'Nouveau véhicule'" max-width="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-3">
                <x-field label="N°" for="v-number" error="form.number"><x-input id="v-number" wire:model="form.number" /></x-field>
                <x-field :label="__('Immatriculation')" for="v-reg" error="form.registration"><x-input id="v-reg" dir="ltr" wire:model="form.registration" placeholder="12345-A-6" /></x-field>
                <x-field :label="__('Type (tarif de l\'arrêté)')" for="v-type" error="form.type">
                    <x-select id="v-type" wire:model="form.type">
                        <option value="">{{ __('— Choisir —') }}</option>
                        @foreach ($types as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
                        @if (($form['type'] ?? '') && ! $types->contains($form['type']))<option value="{{ tr($form['type']) }}">{{ tr($form['type']) }} (ancien)</option>@endif
                    </x-select>
                </x-field>
            </div>

            @unless ($editingId)
                <fieldset class="space-y-2 rounded-xl border border-line p-3">
                    <legend class="px-1 text-xs font-bold uppercase tracking-[.06em] text-muted">{{ __('Propriétaire') }}</legend>
                    @if (! $newTaxpayer)
                        <div class="grid gap-2 sm:grid-cols-[1fr_1.4fr]">
                            <x-input type="search" wire:model.live.debounce.300ms="taxpayerSearch" :placeholder="__('Chercher nom, CIN…')" :aria-label="__('Chercher un contribuable')" />
                            <x-select wire:model="form.taxpayer_id" :aria-label="__('Propriétaire')">
                                <option value="">{{ __('— Choisir —') }}</option>
                                @foreach ($taxpayers as $t)<option value="{{ $t->id }}">{{ $t->displayName() }}{{ $t->cin ? ' · '.$t->cin : '' }}</option>@endforeach
                            </x-select>
                        </div>
                        @error('form.taxpayer_id')<p class="text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                        <button type="button" wire:click="$set('newTaxpayer', true)" class="text-xs font-semibold text-accent hover:underline">{{ __('+ Nouveau contribuable') }}</button>
                    @else
                        <div class="grid gap-2 sm:grid-cols-2">
                            <x-field :label="__('Nom')" for="t-ln" error="taxpayerForm.last_name"><x-input id="t-ln" wire:model="taxpayerForm.last_name" /></x-field>
                            <x-field :label="__('Prénom')" for="t-fn"><x-input id="t-fn" wire:model="taxpayerForm.first_name" /></x-field>
                            <x-field :label="__('Société')" for="t-cn"><x-input id="t-cn" wire:model="taxpayerForm.company_name" /></x-field>
                            <x-field label="الاسم" for="t-lna"><x-input id="t-lna" dir="rtl" class="font-ar" wire:model="taxpayerForm.last_name_ar" /></x-field>
                            <x-field label="CIN" for="t-cin"><x-input id="t-cin" wire:model="taxpayerForm.cin" /></x-field>
                            <x-field :label="__('Téléphone')" for="t-ph"><x-input id="t-ph" wire:model="taxpayerForm.phone" /></x-field>
                        </div>
                        <button type="button" wire:click="$set('newTaxpayer', false)" class="text-xs font-semibold text-muted hover:underline">{{ __('Choisir un contribuable existant') }}</button>
                    @endif
                </fieldset>
            @endunless

            <div class="grid gap-3 sm:grid-cols-3">
                <x-field :label="__('N° d\'agrément')" for="v-auth" error="form.authorization_number"><x-input id="v-auth" wire:model="form.authorization_number" /></x-field>
                <x-field :label="__('Date d\'agrément')" for="v-authd" error="form.authorized_on"><x-input id="v-authd" type="date" wire:model="form.authorized_on" /></x-field>
                <x-field :label="__('Places')" for="v-seats" error="form.seats"><x-input id="v-seats" type="number" min="0" wire:model="form.seats" /></x-field>
                <x-field :label="__('Début : trimestre')" for="v-sq" error="form.tax_start_quarter">
                    <x-select id="v-sq" wire:model="form.tax_start_quarter">@foreach (\App\Domain\Boissons\Services\BeverageCalculator::QUARTERS as $q => $l)<option value="{{ $q }}">{{ tr($l) }}</option>@endforeach</x-select>
                </x-field>
                <x-field :label="__('Début : année')" for="v-sy" error="form.tax_start_year"><x-input id="v-sy" type="number" wire:model="form.tax_start_year" /></x-field>
                @if ($editingId)
                    <x-field :label="__('Statut')" for="v-status" error="form.status">
                        <x-select id="v-status" wire:model="form.status"><option value="actif">{{ __('En service') }}</option><option value="suspendu">{{ __('Suspendu') }}</option><option value="retire">{{ __('Retiré') }}</option></x-select>
                    </x-field>
                @endif
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <x-btn variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-btn>
                <x-btn type="submit" icon="check">{{ __('Enregistrer') }}</x-btn>
            </div>
        </form>
    </x-panel>
</div>
