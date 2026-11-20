@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-btn variant="secondary" icon="printer" :href="route('locations.repertoire', ['q' => $search, 'secteur' => $sector, 'situation' => $situation])" target="_blank">{{ __('Imprimer') }}</x-btn>
            @if ($this->allowed('locations.ajouter'))<x-btn icon="plus" wire:click="create">{{ __('Nouveau bail') }}</x-btn>@endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5" aria-label="{{ __('Indicateurs') }}">
        @foreach ([
            ['store', 'Baux actifs', $kpi['total'], null, '#C8601E'],
            ['triangle-alert', 'Baux avec impayés', $kpi['unpaid'], Format::money($kpi['amount_due']).' DH dus', '#DC2626'],
            ['clock', 'En attente régisseur', $kpi['pending'], Format::money($kpi['amount_pending']).' DH', '#C9A13B'],
            ['check', 'Baux à jour', $kpi['up_to_date'], null, '#16A34A'],
            ['warehouse', 'Boutiques disponibles', $kpi['free_shops'], null, '#5B7590'],
        ] as [$icon, $label, $value, $sub, $color])
            <div @class(['surface p-4', 'col-span-2 md:col-span-1' => $loop->last])>
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
        <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('N° bail, locataire, CIN, boutique…')" :aria-label="__('Rechercher')" />
        <x-select wire:model.live="sector" :aria-label="__('Secteur')">
            <option value="">{{ __('Tous les secteurs') }}</option>
            @foreach ($sectors as $s)<option value="{{ $s->id }}">{{ $s->code }} — {{ $s->label }}</option>@endforeach
        </x-select>
        <x-select wire:model.live="situation" :aria-label="__('Situation')">
            <option value="">{{ __('Toutes les situations') }}</option>
            <option value="impaye">{{ __('Avec mois non déclarés') }}</option>
            <option value="en_attente">{{ __('En attente régisseur') }}</option>
            <option value="ajour">{{ __('À jour') }}</option>
        </x-select>
    </div>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr>
                        <th class="px-4 py-2.5 text-start">{{ __('Bail') }}</th>
                        <th class="px-4 py-2.5 text-start">{{ __('Locataire') }}</th>
                        <th class="px-4 py-2.5 text-end">{{ __('Loyer') }}</th>
                        <th class="px-4 py-2.5 text-start">{{ __('Situation') }}</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($rows as $r)
                        @php($l = $r['lease'])
                        <tr wire:key="l-{{ $l->id }}">
                            <td class="px-4 py-3">
                                <p class="font-mono text-xs font-bold text-ink">N° {{ $l->number }}</p>
                                <p class="text-xs text-muted">{{ $l->shop?->number ?? $l->premises_ref ?? '—' }}@if ($l->leaseSector) · {{ $l->leaseSector->code }}@endif</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-ink">{{ $l->taxpayer?->displayName() }}</p>
                                <p class="text-xs text-muted">
                                    @if ($l->taxpayer?->displayNameAr())<span class="font-ar" lang="ar">{{ $l->taxpayer->displayNameAr() }}</span> · @endif
                                    {{ $l->taxpayer?->cin ?? '' }} {{ $l->taxpayer?->phone ? '· '.$l->taxpayer->phone : '' }}
                                </p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-end font-semibold tabular-nums">{{ Format::money($r['rent']) }}<span class="text-xs text-muted"> {{ __('DH/mois') }}</span></td>
                            <td class="px-4 py-3">
                                @if ($r['nb_non_paye'] === 0)
                                    <x-badge color="green">{{ __('À jour') }}</x-badge>
                                @else
                                    <p class="text-xs font-semibold text-ink">{{ $r['periode'] }}</p>
                                    <p class="mt-0.5 flex flex-wrap gap-1">
                                        @if ($r['nb_non_declares'])<x-badge color="red">{{ Format::money($r['total_non_declare']) }} DH à émettre</x-badge>@endif
                                        @if ($r['nb_emises'])<x-badge color="gold">{{ Format::money($r['total_en_attente']) }} DH en attente</x-badge>@endif
                                    </p>
                                @endif
                                @if ($l->status !== 'actif')<x-badge class="mt-1">{{ ucfirst($l->status) }}</x-badge>@endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <x-btn variant="ghost" size="sm" icon="file-text" :href="route('locations.fiche', ['bail' => $l->id])">{{ __('Fiche') }}</x-btn>
                                    @if ($this->allowed('bulletins.creer') && $r['nb_non_declares'] > 0)
                                        <x-btn variant="gold" size="sm" icon="hand-coins" :href="route('locations.encaisser', ['bail' => $l->id])">{{ __('Encaisser') }}</x-btn>
                                    @endif
                                    @if ($this->allowed('locations.modifier'))
                                        <x-btn variant="ghost" size="sm" icon="pencil" wire:click="edit({{ $l->id }})" aria-label="Modifier le bail {{ $l->number }}" />
                                    @endif
                                    @if ($this->allowed('locations.supprimer'))
                                        <x-btn variant="ghost" size="sm" icon="trash-2" wire:click="delete({{ $l->id }})"
                                               wire:confirm="Supprimer le bail n° {{ $l->number }} ? (s'il a des pièces, il sera archivé comme résilié)" aria-label="Supprimer le bail {{ $l->number }}" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4"><x-empty-state icon="store" :title="__('Aucun bail')">{{ __('Créez un bail ou modifiez les filtres.') }}</x-empty-state></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-panel show="showForm" :title="$editingId ? 'Modifier le bail' : 'Nouveau bail'" max-width="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-3">
                <x-field :label="__('N° de bail')" for="l-number" error="form.number"><x-input id="l-number" wire:model="form.number" /></x-field>
                <x-field :label="__('Date de début')" for="l-start" error="form.starts_on"><x-input id="l-start" type="date" wire:model="form.starts_on" /></x-field>
                <x-field :label="__('Date de fin')" for="l-end" error="form.ends_on"><x-input id="l-end" type="date" wire:model="form.ends_on" /></x-field>
            </div>

            <fieldset class="space-y-2 rounded-xl border border-line p-3">
                <legend class="px-1 text-xs font-bold uppercase tracking-[.06em] text-muted">{{ __('Locataire') }}</legend>
                @if (! $newTaxpayer)
                    <div class="grid gap-2 sm:grid-cols-[1fr_1.4fr]">
                        <x-input type="search" wire:model.live.debounce.300ms="taxpayerSearch" :placeholder="__('Chercher nom, CIN…')" :aria-label="__('Chercher un contribuable')" />
                        <x-select wire:model="form.taxpayer_id" :aria-label="__('Locataire')">
                            <option value="">{{ __('— Choisir —') }}</option>
                            @foreach ($taxpayers as $t)<option value="{{ $t->id }}">{{ $t->displayName() }}{{ $t->cin ? ' · '.$t->cin : '' }}</option>@endforeach
                        </x-select>
                    </div>
                    @error('form.taxpayer_id')<p class="text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                    @unless ($editingId)
                        <button type="button" wire:click="$set('newTaxpayer', true)" class="text-xs font-semibold text-accent hover:underline">{{ __('+ Nouveau contribuable') }}</button>
                    @endunless
                @else
                    <div class="grid gap-2 sm:grid-cols-2">
                        <x-field :label="__('Nom')" for="t-ln" error="taxpayerForm.last_name"><x-input id="t-ln" wire:model="taxpayerForm.last_name" /></x-field>
                        <x-field :label="__('Prénom')" for="t-fn"><x-input id="t-fn" wire:model="taxpayerForm.first_name" /></x-field>
                        <x-field label="CIN" for="t-cin"><x-input id="t-cin" wire:model="taxpayerForm.cin" /></x-field>
                        <x-field :label="__('Téléphone')" for="t-ph"><x-input id="t-ph" wire:model="taxpayerForm.phone" /></x-field>
                    </div>
                    <button type="button" wire:click="$set('newTaxpayer', false)" class="text-xs font-semibold text-muted hover:underline">{{ __('Choisir un contribuable existant') }}</button>
                @endif
            </fieldset>

            <fieldset class="space-y-3 rounded-xl border border-line p-3">
                <legend class="px-1 text-xs font-bold uppercase tracking-[.06em] text-muted">{{ __('Local et tarif') }}</legend>
                <x-field :label="__('Boutique disponible')" for="l-shop" :hint="__('Choisir une boutique remplit le secteur, le tarif et la superficie.')">
                    <x-select id="l-shop" wire:model.live="form.shop_id">
                        <option value="">{{ __('— Aucune (local hors boutique) —') }}</option>
                        @foreach ($freeShops as $b)<option value="{{ $b->id }}">{{ $b->leaseTariff?->leaseSector?->code }} · {{ $b->number }} — {{ $b->leaseTariff?->label }} ({{ Format::money($b->leaseTariff?->value) }} {{ $b->leaseTariff?->unit }})</option>@endforeach
                    </x-select>
                </x-field>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field :label="__('Secteur')" for="l-sector" error="form.lease_sector_id">
                        <x-select id="l-sector" wire:model.live="form.lease_sector_id">
                            <option value="">—</option>
                            @foreach ($sectors as $s)<option value="{{ $s->id }}">{{ $s->code }} — {{ $s->label }}</option>@endforeach
                        </x-select>
                    </x-field>
                    <x-field :label="__('Groupe de tarif')" for="l-tariff" error="form.lease_tariff_id">
                        <x-select id="l-tariff" wire:model="form.lease_tariff_id">
                            <option value="">—</option>
                            @foreach ($tariffs as $t)<option value="{{ $t->id }}">{{ $t->label }} ({{ Format::money($t->value) }} {{ $t->unit }})</option>@endforeach
                        </x-select>
                    </x-field>
                    <x-field :label="__('Référence du local')" for="l-ref" error="form.premises_ref"><x-input id="l-ref" wire:model="form.premises_ref" /></x-field>
                    <x-field :label="__('Adresse')" for="l-addr" error="form.address"><x-input id="l-addr" wire:model="form.address" /></x-field>
                    <x-field :label="__('Superficie (m²)')" for="l-area" error="form.area"><x-input id="l-area" type="number" step="0.01" min="0" wire:model="form.area" /></x-field>
                    <x-field :label="__('Loyer mensuel du bail (DH)')" for="l-rent" error="form.monthly_rent" :hint="__('Utilisé seulement s\'il n\'y a ni tarif ni secteur.')">
                        <x-input id="l-rent" type="number" step="0.01" min="0" wire:model="form.monthly_rent" />
                    </x-field>
                </div>
            </fieldset>

            @if ($editingId)
                <x-field :label="__('Statut')" for="l-status" error="form.status">
                    <x-select id="l-status" wire:model="form.status"><option value="actif">{{ __('Actif') }}</option><option value="suspendu">{{ __('Suspendu') }}</option><option value="resilie">{{ __('Résilié') }}</option></x-select>
                </x-field>
            @endif

            <div class="flex justify-end gap-2 pt-2">
                <x-btn variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-btn>
                <x-btn type="submit" icon="check">{{ __('Enregistrer') }}</x-btn>
            </div>
        </form>
    </x-panel>
</div>
