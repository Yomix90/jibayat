@php use App\Support\Format; use App\Domain\Souk\Models\SoukContract; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-btn variant="secondary" icon="printer" :href="route('souk.imprimer-liste', ['q' => $search, 'situation' => $situation, 'souk' => $souk])" target="_blank">{{ __('Imprimer') }}</x-btn>
            @if ($this->allowed('souk.ajouter'))<x-btn icon="plus" wire:click="create">{{ __('Nouveau contrat') }}</x-btn>@endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="{{ __('Indicateurs') }}">
        @foreach ([
            ['scroll', 'Contrats', $kpi['total'], null, '#B8891F'],
            ['triangle-alert', 'Mois à encaisser', $kpi['unpaid'], Format::money($kpi['amount_due']).' DH dus', '#DC2626'],
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

    <div class="grid gap-2 sm:grid-cols-3">
        <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('N°, fermier, CIN, emplacement…')" :aria-label="__('Rechercher')" />
        <x-select wire:model.live="souk" :aria-label="__('Souk')">
            <option value="">{{ __('Tous les souks') }}</option>
            @foreach ($souks as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
        </x-select>
        <x-select wire:model.live="situation" :aria-label="__('Situation')">
            <option value="">{{ __('Toutes les situations') }}</option><option value="impaye">{{ __('Mois à encaisser') }}</option>
            <option value="en_attente">{{ __('En attente régisseur') }}</option><option value="ajour">{{ __('À jour') }}</option>
        </x-select>
    </div>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr><th class="px-4 py-2.5 text-start">{{ __('Contrat') }}</th><th class="px-4 py-2.5 text-start">{{ __('Fermier') }}</th>
                        <th class="px-4 py-2.5 text-end">{{ __('Redevance') }}</th><th class="px-4 py-2.5 text-start">{{ __('Situation') }}</th><th class="px-4 py-2.5"></th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($rows as $r)
                        @php($c = $r['contract'])
                        <tr wire:key="c-{{ $c->id }}">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-ink">{{ $c->souk_name }} @if ($c->pitch_number)<span class="text-muted">· {{ $c->pitch_number }}</span>@endif
                                    @if ($c->status !== 'actif')<x-badge>{{ SoukContract::STATUSES[$c->status] ?? $c->status }}</x-badge>@endif</p>
                                <p class="text-xs text-muted">N° {{ $c->number }}{{ $c->activity ? ' · '.$c->activity : '' }} · {{ $c->starts_on?->format('d/m/Y') }} → {{ $c->ends_on?->format('d/m/Y') ?? '…' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-ink">{{ $c->taxpayer?->displayName() }}</p>
                                <p class="text-xs text-muted">@if ($c->taxpayer?->displayNameAr())<span class="font-ar" lang="ar">{{ $c->taxpayer->displayNameAr() }}</span> · @endif{{ $c->taxpayer?->cin }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-end">
                                <p class="font-semibold tabular-nums">{{ Format::money($r['current_fee']) }} <span class="text-xs text-muted">{{ __('DH/mois') }}</span></p>
                                <p class="text-[11px] text-muted">+{{ rtrim(rtrim(number_format(\App\Domain\Souk\Services\SoukCalculator::rate($c), 2, ',', ''), '0'), ',') }} % tous les {{ \App\Domain\Souk\Services\SoukCalculator::duration($c) }} an(s)</p>
                            </td>
                            <td class="px-4 py-3">
                                @if ($r['nb_mois_impaye'] === 0)
                                    <x-badge color="green">{{ __('À jour') }}</x-badge>
                                @else
                                    <p class="flex flex-wrap gap-1">
                                        @if ($r['nb_non_declares'])<x-badge color="red">{{ $r['nb_non_declares'] }} mois · {{ Format::money($r['total_non_declare']) }} DH</x-badge>@endif
                                        @if ($r['nb_emises'])<x-badge color="gold">{{ $r['nb_emises'] }} en attente</x-badge>@endif
                                    </p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <x-btn variant="ghost" size="sm" icon="file-text" :href="route('souk.fiche', ['contrat' => $c->id])">{{ __('Fiche') }}</x-btn>
                                    @if ($this->allowed('bulletins.creer') && $r['nb_non_declares'] > 0)
                                        <x-btn variant="gold" size="sm" icon="hand-coins" :href="route('souk.encaisser', ['contrat' => $c->id])">{{ __('Encaisser') }}</x-btn>
                                    @endif
                                    @if ($this->allowed('souk.modifier'))<x-btn variant="ghost" size="sm" icon="pencil" wire:click="edit({{ $c->id }})" aria-label="Modifier le contrat {{ $c->number }}" />@endif
                                    @if ($this->allowed('souk.supprimer'))
                                        <x-btn variant="ghost" size="sm" icon="archive" wire:click="delete({{ $c->id }})" wire:confirm="Archiver le contrat n° {{ $c->number }} ?" aria-label="Archiver le contrat {{ $c->number }}" />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4"><x-empty-state icon="scroll" :title="__('Aucun contrat')">{{ __('Ajoutez un contrat d\'affermage ou modifiez les filtres.') }}</x-empty-state></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-panel show="showForm" :title="$editingId ? 'Modifier le contrat' : 'Nouveau contrat d’affermage'" max-width="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-3">
                <x-field :label="__('N° de contrat')" for="s-number" error="form.number"><x-input id="s-number" wire:model="form.number" /></x-field>
                <x-field :label="__('Souk')" for="s-souk" error="form.souk_name"><x-input id="s-souk" wire:model="form.souk_name" list="souks-list" :placeholder="__('Souk Had…')" /></x-field>
                <x-field :label="__('Emplacement')" for="s-pitch" error="form.pitch_number"><x-input id="s-pitch" wire:model="form.pitch_number" /></x-field>
                <datalist id="souks-list">@foreach ($souks as $s)<option value="{{ $s }}"></option>@endforeach</datalist>
            </div>

            @unless ($editingId)
                <fieldset class="space-y-2 rounded-xl border border-line p-3">
                    <legend class="px-1 text-xs font-bold uppercase tracking-[.06em] text-muted">{{ __('Fermier') }}</legend>
                    @if (! $newTaxpayer)
                        <div class="grid gap-2 sm:grid-cols-[1fr_1.4fr]">
                            <x-input type="search" wire:model.live.debounce.300ms="taxpayerSearch" :placeholder="__('Chercher nom, CIN…')" :aria-label="__('Chercher un contribuable')" />
                            <x-select wire:model="form.taxpayer_id" :aria-label="__('Fermier')">
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
                <x-field :label="__('Activité')" for="s-act" error="form.activity"><x-input id="s-act" wire:model="form.activity" :placeholder="__('Fruits, bétail…')" /></x-field>
                <x-field :label="__('Redevance mensuelle (DH)')" for="s-fee" error="form.monthly_fee"><x-input id="s-fee" type="number" step="0.01" min="0" wire:model="form.monthly_fee" /></x-field>
                <x-field :label="__('Date de début')" for="s-start" error="form.starts_on"><x-input id="s-start" type="date" wire:model="form.starts_on" /></x-field>
                <x-field :label="__('Durée d\'une période (ans)')" for="s-dur" error="form.duration_years"><x-input id="s-dur" type="number" min="1" wire:model="form.duration_years" /></x-field>
                <x-field :label="__('Revalorisation à chaque période (%)')" for="s-rate" error="form.increase_rate" hint="0 ou vide = 5 % (règle Flask)."><x-input id="s-rate" type="number" step="0.01" min="0" wire:model="form.increase_rate" /></x-field>
                <x-field :label="__('Date de fin')" for="s-end" error="form.ends_on" hint="Vide = fin de la 1re période."><x-input id="s-end" type="date" wire:model="form.ends_on" /></x-field>
                @if ($editingId)
                    <x-field :label="__('Statut')" for="s-status" error="form.status">
                        <x-select id="s-status" wire:model="form.status">@foreach (SoukContract::STATUSES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</x-select>
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
