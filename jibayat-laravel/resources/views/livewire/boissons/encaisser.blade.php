@php use App\Support\Format; use App\Domain\Boissons\Services\BeverageCalculator; $t = $outlet->taxpayer; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" :title="$outlet->name.' — n° '.$outlet->number"
                   :subtitle="$t?->displayName().' · '.$outlet->typeLabel().' · taux '.rtrim(rtrim(number_format($rate, 2, ',', ''), '0'), ',').' %'">
        <x-slot:actions>
            <x-btn variant="secondary" size="sm" icon="file-text" :href="route('boissons.fiche', ['etablissement' => $outlet->id])">{{ __('Fiche') }}</x-btn>
            <x-btn variant="ghost" size="sm" icon="chevron-left" wire:click="$set('outletId', null)">{{ __('Autre établissement') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <section class="grid gap-3 sm:grid-cols-3">
        <div class="surface p-4">
            <p class="text-xs font-semibold text-muted">{{ __('Trimestres à déclarer') }}</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-red-600">{{ count($lines) }}</p>
            <p class="text-xs text-muted">{{ $period }}</p>
        </div>
        <div class="surface p-4">
            <p class="text-xs font-semibold text-muted">{{ __('En attente du régisseur') }}</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-[#7a5d16]">{{ Format::money($pendingTotal) }} DH</p>
        </div>
        <div class="surface p-4">
            <p class="text-xs font-semibold text-muted">{{ __('Déjà payé') }}</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-online">{{ Format::money($paidTotal) }} DH</p>
        </div>
    </section>

    @if (count($lines))
        <form wire:submit="declare" class="grid gap-4 lg:grid-cols-3">
            <x-card class="lg:col-span-2" :padding="false">
                <x-slot:title>{{ __('Chiffre d\'affaires par trimestre') }}</x-slot:title>
                <x-slot:actions>
                    <x-btn variant="secondary" size="sm" icon="printer" target="_blank"
                           :href="route('boissons.formulaire', ['type' => 'ca', 'etablissement' => $outlet->id, 'trims' => implode(',', array_keys($lines))])">{{ __('Formulaire CA') }}</x-btn>
                </x-slot:actions>
                <div class="divide-y divide-line">
                    @foreach ($byYear as $year => $items)
                        <div class="px-4 py-3">
                            <p class="mb-2 text-xs font-bold uppercase tracking-[.06em] text-muted">{{ $year }}</p>
                            <div class="space-y-2">
                                @foreach ($items as $l)
                                    @php($on = in_array($l['key'], $selected, true))
                                    <div wire:key="q-{{ $l['key'] }}" @class(['grid items-center gap-2 rounded-xl border p-2.5 sm:grid-cols-[auto_1fr_9rem_1fr]', 'border-gold bg-gold/10' => $on, 'border-line' => ! $on])>
                                        <input type="checkbox" value="{{ $l['key'] }}" wire:model.live="selected" class="rounded border-line text-accent focus:ring-gold" aria-label="{{ tr($l['label']) }} {{ $year }}">
                                        <div>
                                            <p class="text-sm font-semibold">{{ tr($l['label']) }}</p>
                                            <p class="text-[11px] text-muted">Échéance {{ \Illuminate\Support\Carbon::parse($l['echeance'])->format('d/m/Y') }} · taux {{ rtrim(rtrim(number_format($l['rate'], 2, ',', ''), '0'), ',') }} %</p>
                                        </div>
                                        <div>
                                            <x-input type="number" step="0.01" min="0" wire:model.live.debounce.500ms="bases.{{ $l['key'] }}" :placeholder="__('CA (DH)')" aria-label="Chiffre d'affaires {{ tr($l['label']) }} {{ $year }}" />
                                            @error('bases.'.$l['key'])<p class="text-[11px] font-semibold text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="text-end text-xs">
                                            @if ($l['calc'])
                                                <p class="text-muted">Principal {{ Format::money($l['calc']['principal']) }}
                                                    @if ($l['calc']['penalite'] + $l['calc']['majoration'] > 0)· retard {{ Format::money($l['calc']['penalite'] + $l['calc']['majoration']) }}@endif
                                                    @if ($l['calc']['amende'] > 0)· amende {{ Format::money($l['calc']['amende']) }}@endif</p>
                                                <p class="text-sm font-bold tabular-nums">{{ Format::money($l['calc']['total']) }} DH
                                                    @if ($l['calc']['statut'] === 'sous_seuil')<x-badge class="ms-1">{{ __('sous seuil') }}</x-badge>@endif</p>
                                            @else
                                                <p class="text-muted">—</p>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="border-t border-line px-4 py-2 text-[11px] text-muted">Principal = CA × taux de l'arrêté fiscal en vigueur pour le trimestre. Pénalités à partir du lendemain de l'échéance (T4 : à partir de février de l'année suivante). Déclaré après l'échéance : pénalité 10 % et majoration (5 % puis 0,5 % par mois).
                    T4 déclaré à partir du 1er avril de l'année suivante : amende de non-déclaration (10 %, minimum 500 DH). Total inférieur à 200 DH : « sous seuil », sans bulletin.</p>
                @error('selected')<p class="px-4 pb-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
            </x-card>

            <x-card :title="__('Déclaration et bulletin')">
                <div class="space-y-3">
                    <div class="rounded-xl bg-navy p-3 text-white">
                        <p class="text-xs text-white/70">{{ count($selected) }} trimestre(s) sélectionné(s)</p>
                        <p class="mt-1 text-2xl font-bold tabular-nums text-gold">{{ Format::money($total) }} DH</p>
                    </div>
                    <x-field :label="__('Date de déclaration')" for="d-date" error="declared_on" :hint="__('Les pénalités sont calculées à cette date.')">
                        <x-input id="d-date" type="date" wire:model.live="declared_on" />
                    </x-field>
                    <x-field label="N° de bulletin (vide = automatique)" for="d-slip" error="slip_number"><x-input id="d-slip" wire:model="slip_number" /></x-field>
                    @if ($this->allowed('bulletins.creer'))
                        <x-btn type="submit" variant="gold" icon="hand-coins" class="w-full" :disabled="! count($selected)">{{ __('Déclarer et émettre') }}</x-btn>
                    @else
                        <p class="text-xs text-muted">{{ __('Vous n\'avez pas le droit d\'émettre des bulletins.') }}</p>
                    @endif
                </div>
            </x-card>
        </form>
    @else
        <x-card><x-empty-state icon="check" :title="__('Aucun trimestre à déclarer')">{{ $pendingTotal > 0 ? 'Les trimestres échus sont émis ('.Format::money($pendingTotal).' DH en attente du régisseur).' : 'Établissement à jour.' }}</x-empty-state></x-card>
    @endif

    @include('livewire.boissons.partials.declarations', ['history' => $history])
</div>
