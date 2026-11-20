@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-btn variant="secondary" icon="printer" :href="route('boissons.imprimer-liste', ['q' => $search, 'statut' => $status, 'situation' => $situation])" target="_blank">{{ __('Imprimer') }}</x-btn>
            @if ($this->allowed('boissons.ajouter'))<x-btn icon="plus" wire:click="create">{{ __('Nouvel établissement') }}</x-btn>@endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="{{ __('Indicateurs') }}">
        @foreach ([
            ['coffee', 'Établissements', $kpi['total'], null, '#8B2E3C'],
            ['triangle-alert', 'Trimestres non déclarés', $kpi['unpaid'], 'estimé '.Format::money($kpi['amount_late']).' DH', '#DC2626'],
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
    <p class="-mt-3 text-xs text-muted">{{ __('Les montants dus sont estimés sur le dernier chiffre d\'affaires déclaré (le montant réel dépend du CA déclaré pour chaque trimestre).') }}</p>

    <div class="grid gap-2 sm:grid-cols-3">
        <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Établissement, exploitant, CIN…')" :aria-label="__('Rechercher')" />
        <x-select wire:model.live="status" :aria-label="__('Statut')">
            <option value="">{{ __('Tous les statuts') }}</option><option value="actif">{{ __('En activité') }}</option><option value="cesse">{{ __('Cessation') }}</option>
        </x-select>
        <x-select wire:model.live="situation" :aria-label="__('Situation')">
            <option value="">{{ __('Toutes les situations') }}</option><option value="impaye">{{ __('Trimestres non déclarés') }}</option>
            <option value="en_attente">{{ __('En attente régisseur') }}</option><option value="ajour">{{ __('À jour') }}</option>
        </x-select>
    </div>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr>
                        <th class="px-4 py-2.5 text-start">{{ __('Établissement') }}</th><th class="px-4 py-2.5 text-start">{{ __('Exploitant') }}</th>
                        <th class="px-4 py-2.5 text-end">{{ __('Taux') }}</th><th class="px-4 py-2.5 text-start">{{ __('Situation') }}</th><th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($rows as $r)
                        @php($o = $r['outlet'])
                        <tr wire:key="o-{{ $o->id }}">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-ink">{{ $o->name }} @if ($o->status === 'cesse')<x-badge>{{ __('Cessation') }}</x-badge>@endif</p>
                                <p class="text-xs text-muted">N° {{ $o->number }} · {{ $o->typeLabel() }}{{ $o->address ? ' · '.$o->address : '' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-ink">{{ $o->taxpayer?->displayName() }}</p>
                                <p class="text-xs text-muted">@if ($o->taxpayer?->displayNameAr())<span class="font-ar" lang="ar">{{ $o->taxpayer->displayNameAr() }}</span> · @endif{{ $o->taxpayer?->cin ?? $o->taxpayer?->rc }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-end font-semibold tabular-nums">{{ rtrim(rtrim(number_format($r['rate'], 2, ',', ''), '0'), ',') }} %</td>
                            <td class="px-4 py-3">
                                @if ($r['nb_non_paye'] === 0)
                                    <x-badge color="green">{{ __('À jour') }}</x-badge>
                                @else
                                    <p class="flex flex-wrap gap-1">
                                        @if ($r['nb_non_declares'])<x-badge color="red">{{ $r['nb_non_declares'] }} trim. à déclarer{{ $r['montant_estime_retard'] > 0 ? ' · ~'.Format::money($r['montant_estime_retard']).' DH' : '' }}</x-badge>@endif
                                        @if ($r['nb_emises'])<x-badge color="gold">{{ $r['nb_emises'] }} en attente · {{ Format::money($r['montant_en_attente']) }} DH</x-badge>@endif
                                    </p>
                                    <p class="mt-0.5 text-xs text-muted">Années : {{ implode(', ', $r['annees_non_payees']) }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <x-btn variant="ghost" size="sm" icon="file-text" :href="route('boissons.fiche', ['etablissement' => $o->id])">{{ __('Fiche') }}</x-btn>
                                    @if ($this->allowed('bulletins.creer') && $r['nb_non_declares'] > 0)
                                        <x-btn variant="gold" size="sm" icon="hand-coins" :href="route('boissons.encaisser', ['etablissement' => $o->id])">{{ __('Encaisser') }}</x-btn>
                                    @endif
                                    @if ($this->allowed('boissons.modifier'))
                                        <x-btn variant="ghost" size="sm" icon="pencil" wire:click="edit({{ $o->id }})" aria-label="Modifier {{ $o->name }}" />
                                    @endif
                                    @if ($this->allowed('boissons.supprimer'))
                                        <x-btn variant="ghost" size="sm" icon="trash-2" wire:click="delete({{ $o->id }})" wire:confirm="Retirer l'établissement {{ $o->name }} de la liste ?" aria-label="Supprimer {{ $o->name }}" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4"><x-empty-state icon="coffee" :title="__('Aucun établissement')">{{ __('Ajoutez un établissement ou modifiez les filtres.') }}</x-empty-state></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-panel show="showForm" :title="$editingId ? 'Modifier l’établissement' : 'Nouvel établissement'" max-width="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-3">
                <x-field label="N°" for="b-number" error="form.number"><x-input id="b-number" wire:model="form.number" /></x-field>
                <x-field :label="__('Nom de l\'établissement')" for="b-name" error="form.name" class="sm:col-span-2"><x-input id="b-name" wire:model="form.name" :placeholder="__('Café Central…')" /></x-field>
            </div>

            @unless ($editingId)
                <fieldset class="space-y-2 rounded-xl border border-line p-3">
                    <legend class="px-1 text-xs font-bold uppercase tracking-[.06em] text-muted">{{ __('Exploitant') }}</legend>
                    @if (! $newTaxpayer)
                        <div class="grid gap-2 sm:grid-cols-[1fr_1.4fr]">
                            <x-input type="search" wire:model.live.debounce.300ms="taxpayerSearch" :placeholder="__('Chercher nom, CIN, RC…')" :aria-label="__('Chercher un contribuable')" />
                            <x-select wire:model="form.taxpayer_id" :aria-label="__('Exploitant')">
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
                            <x-field :label="__('Raison sociale (société)')" for="t-cn"><x-input id="t-cn" wire:model="taxpayerForm.company_name" /></x-field>
                            <x-field label="الاسم" for="t-lna"><x-input id="t-lna" dir="rtl" class="font-ar" wire:model="taxpayerForm.last_name_ar" /></x-field>
                            <x-field label="CIN" for="t-cin"><x-input id="t-cin" wire:model="taxpayerForm.cin" /></x-field>
                            <x-field label="RC" for="t-rc"><x-input id="t-rc" wire:model="taxpayerForm.rc" /></x-field>
                            <x-field :label="__('Téléphone')" for="t-ph"><x-input id="t-ph" wire:model="taxpayerForm.phone" /></x-field>
                        </div>
                        <button type="button" wire:click="$set('newTaxpayer', false)" class="text-xs font-semibold text-muted hover:underline">{{ __('Choisir un contribuable existant') }}</button>
                    @endif
                </fieldset>
            @endunless

            <div class="grid gap-3 sm:grid-cols-2">
                <x-field :label="__('Type d\'établissement (tarif)')" for="b-tariff" error="form.tariff_id">
                    <x-select id="b-tariff" wire:model="form.tariff_id">
                        <option value="">{{ __('— Choisir —') }}</option>
                        @foreach ($tariffs as $t)<option value="{{ $t->id }}">{{ $t->label }} ({{ rtrim(rtrim(number_format((float) $t->value, 2, ',', ''), '0'), ',') }} %)</option>@endforeach
                    </x-select>
                </x-field>
                <x-field :label="__('Superficie (m²)')" for="b-area" error="form.area"><x-input id="b-area" type="number" step="0.1" min="0" wire:model="form.area" /></x-field>
                <x-field :label="__('Adresse')" for="b-addr" error="form.address" class="sm:col-span-2"><x-input id="b-addr" wire:model="form.address" /></x-field>
                <x-field :label="__('N° d\'autorisation')" for="b-auth" error="form.authorization_number"><x-input id="b-auth" wire:model="form.authorization_number" /></x-field>
                <x-field :label="__('Date d\'autorisation')" for="b-authd" error="form.authorized_on"><x-input id="b-authd" type="date" wire:model="form.authorized_on" /></x-field>
                <x-field :label="__('Début d\'imposition : trimestre')" for="b-sq" error="form.tax_start_quarter">
                    <x-select id="b-sq" wire:model="form.tax_start_quarter">@foreach (\App\Domain\Boissons\Services\BeverageCalculator::QUARTERS as $q => $l)<option value="{{ $q }}">{{ tr($l) }}</option>@endforeach</x-select>
                </x-field>
                <x-field :label="__('Début d\'imposition : année')" for="b-sy" error="form.tax_start_year"><x-input id="b-sy" type="number" wire:model="form.tax_start_year" /></x-field>
                @if ($editingId)
                    <x-field :label="__('Statut')" for="b-status" error="form.status">
                        <x-select id="b-status" wire:model="form.status"><option value="actif">{{ __('En activité') }}</option><option value="cesse">{{ __('Cessation') }}</option></x-select>
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
