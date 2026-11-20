@php use App\Support\Format; use App\Livewire\DomainePublic\Occupations; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-btn variant="secondary" icon="printer" :href="route('domaine-public.imprimer-liste', ['q' => $search, 'situation' => $situation])" target="_blank">{{ __('Imprimer') }}</x-btn>
            @if ($this->allowed('domaine_public.ajouter'))<x-btn icon="plus" wire:click="create">{{ __('Nouvelle occupation') }}</x-btn>@endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="{{ __('Indicateurs') }}">
        @foreach ([
            ['tent', 'Occupations', $kpi['total'], null, '#2F7D6B'],
            ['triangle-alert', 'Trimestres à liquider', $kpi['unpaid'], Format::money($kpi['amount_due']).' DH dus', '#DC2626'],
            ['clock', 'En attente régisseur', $kpi['pending'], Format::money($kpi['amount_pending']).' DH', '#C9A13B'],
            ['calendar-x', 'Autorisations expirées', $kpi['expired'], null, '#6B7280'],
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
        <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('N°, occupant, emplacement, type…')" :aria-label="__('Rechercher')" />
        <x-select wire:model.live="situation" :aria-label="__('Situation')">
            <option value="">{{ __('Toutes les situations') }}</option><option value="impaye">{{ __('Trimestres à liquider') }}</option>
            <option value="en_attente">{{ __('En attente régisseur') }}</option><option value="ajour">{{ __('À jour') }}</option><option value="expire">{{ __('Autorisation expirée') }}</option>
        </x-select>
    </div>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr><th class="px-4 py-2.5 text-start">{{ __('Occupation') }}</th><th class="px-4 py-2.5 text-start">{{ __('Occupant') }}</th>
                        <th class="px-4 py-2.5 text-end">{{ __('Redevance') }}</th><th class="px-4 py-2.5 text-start">{{ __('Situation') }}</th><th class="px-4 py-2.5"></th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($rows as $r)
                        @php($o = $r['occupation'])
                        <tr wire:key="o-{{ $o->id }}">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-ink">{{ $o->type }} · {{ Format::money($o->area) }} m²
                                    @if ($r['est_expire'])<x-badge>{{ $o->status !== 'actif' ? (Occupations::STATUSES[$o->status] ?? $o->status) : 'Expirée' }}</x-badge>@endif</p>
                                <p class="text-xs text-muted">N° {{ $o->number }} · {{ $o->location }}{{ $o->ends_on ? ' · jusqu’au '.$o->ends_on->format('d/m/Y') : '' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-ink">{{ $o->taxpayer?->displayName() }}</p>
                                <p class="text-xs text-muted">@if ($o->taxpayer?->displayNameAr())<span class="font-ar" lang="ar">{{ $o->taxpayer->displayNameAr() }}</span> · @endif{{ $o->taxpayer?->cin }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-end">
                                <p class="font-semibold tabular-nums">{{ Format::money($r['trim_base']) }} <span class="text-xs text-muted">{{ __('DH/trim.') }}</span></p>
                                <p class="text-[11px] text-muted">{{ Format::money($r['an_base']) }} DH/an</p>
                            </td>
                            <td class="px-4 py-3">
                                @if ($r['nb_impayes'] === 0)
                                    <x-badge color="green">{{ __('À jour') }}</x-badge>
                                @else
                                    <p class="flex flex-wrap gap-1">
                                        @if ($r['nb_non_declares'])<x-badge color="red">{{ $r['nb_non_declares'] }} trim. · {{ Format::money($r['montant_non_declare']) }} DH</x-badge>@endif
                                        @if ($r['nb_emises'])<x-badge color="gold">{{ $r['nb_emises'] }} en attente</x-badge>@endif
                                    </p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <x-btn variant="ghost" size="sm" icon="file-text" :href="route('domaine-public.fiche', ['occupation' => $o->id])">{{ __('Fiche') }}</x-btn>
                                    @if ($this->allowed('bulletins.creer') && $r['nb_non_declares'] > 0)
                                        <x-btn variant="gold" size="sm" icon="hand-coins" :href="route('domaine-public.encaisser', ['occupation' => $o->id])">{{ __('Encaisser') }}</x-btn>
                                    @endif
                                    @if ($this->allowed('domaine_public.modifier'))<x-btn variant="ghost" size="sm" icon="pencil" wire:click="edit({{ $o->id }})" aria-label="Modifier l'occupation {{ $o->number }}" />@endif
                                    @if ($this->allowed('domaine_public.supprimer'))
                                        <x-btn variant="ghost" size="sm" icon="archive" wire:click="delete({{ $o->id }})" wire:confirm="Archiver l'occupation n° {{ $o->number }} ?" aria-label="Archiver l'occupation {{ $o->number }}" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4"><x-empty-state icon="tent" :title="__('Aucune occupation')">{{ __('Ajoutez une occupation ou modifiez les filtres.') }}</x-empty-state></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-panel show="showForm" :title="$editingId ? 'Modifier l’occupation' : 'Nouvelle occupation du domaine public'" max-width="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-3">
                <x-field label="N°" for="o-number" error="form.number"><x-input id="o-number" wire:model="form.number" /></x-field>
                <x-field :label="__('Type (tarif de l\'arrêté)')" for="o-type" error="form.type">
                    <x-select id="o-type" wire:model="form.type">
                        <option value="">{{ __('— Choisir —') }}</option>
                        @foreach ($tariffs as $t)<option value="{{ $t->label }}">{{ $t->label }} ({{ Format::money($t->value) }} {{ $t->unit }})</option>@endforeach
                        @if (($form['type'] ?? '') && ! $tariffs->pluck('label')->contains($form['type']))<option value="{{ tr($form['type']) }}">{{ tr($form['type']) }} (ancien)</option>@endif
                    </x-select>
                </x-field>
                <x-field :label="__('Superficie occupée (m²)')" for="o-area" error="form.area"><x-input id="o-area" type="number" step="0.01" min="0" wire:model="form.area" /></x-field>
            </div>

            @unless ($editingId)
                <fieldset class="space-y-2 rounded-xl border border-line p-3">
                    <legend class="px-1 text-xs font-bold uppercase tracking-[.06em] text-muted">{{ __('Occupant') }}</legend>
                    @if (! $newTaxpayer)
                        <div class="grid gap-2 sm:grid-cols-[1fr_1.4fr]">
                            <x-input type="search" wire:model.live.debounce.300ms="taxpayerSearch" :placeholder="__('Chercher nom, CIN…')" :aria-label="__('Chercher un contribuable')" />
                            <x-select wire:model="form.taxpayer_id" :aria-label="__('Occupant')">
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
                <x-field :label="__('Emplacement')" for="o-loc" error="form.location" class="sm:col-span-2"><x-input id="o-loc" wire:model="form.location" :placeholder="__('Bd Hassan II, devant le n° 12…')" /></x-field>
                <x-field :label="__('N° d\'autorisation')" for="o-auth" error="form.authorization_number"><x-input id="o-auth" wire:model="form.authorization_number" /></x-field>
                <x-field :label="__('Autorisée du')" for="o-start" error="form.starts_on"><x-input id="o-start" type="date" wire:model="form.starts_on" /></x-field>
                <x-field :label="__('au')" for="o-end" error="form.ends_on"><x-input id="o-end" type="date" wire:model="form.ends_on" /></x-field>
                <div class="grid grid-cols-2 gap-2">
                    <x-field :label="__('Début : trim.')" for="o-sq" error="form.tax_start_quarter">
                        <x-select id="o-sq" wire:model="form.tax_start_quarter">@foreach ([1, 2, 3, 4] as $q)<option value="{{ $q }}">T{{ $q }}</option>@endforeach</x-select>
                    </x-field>
                    <x-field :label="__('année')" for="o-sy" error="form.tax_start_year"><x-input id="o-sy" type="number" wire:model="form.tax_start_year" /></x-field>
                </div>
                @if ($editingId)
                    <x-field :label="__('Statut')" for="o-status" error="form.status">
                        <x-select id="o-status" wire:model="form.status">@foreach (Occupations::STATUSES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</x-select>
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
