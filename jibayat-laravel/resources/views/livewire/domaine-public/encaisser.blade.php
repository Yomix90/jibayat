@php use App\Support\Format; $t = $occupation->taxpayer; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" :title="'Occupation n° '.$occupation->number.' — '.$occupation->type"
                   :subtitle="$t?->displayName().' · '.Format::money($occupation->area).' m² × '.Format::money($situation['tariff']->value).' '.$situation['tariff']->unit">
        <x-slot:actions>
            <x-btn variant="secondary" size="sm" icon="file-text" :href="route('domaine-public.fiche', ['occupation' => $occupation->id])">{{ __('Fiche') }}</x-btn>
            <x-btn variant="ghost" size="sm" icon="chevron-left" wire:click="$set('occupationId', null)">{{ __('Autre occupation') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <section class="grid gap-3 sm:grid-cols-3">
        <div class="surface p-4">
            <p class="text-xs font-semibold text-muted">{{ __('Trimestres à liquider') }}</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-red-600">{{ Format::money($situation['montant_non_declare']) }} DH</p>
            <p class="text-xs text-muted">{{ $situation['nb_non_declares'] }} trimestre(s) × {{ Format::money($situation['trim_base']) }} DH</p>
        </div>
        <div class="surface p-4">
            <p class="text-xs font-semibold text-muted">{{ __('En attente du régisseur') }}</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-[#7a5d16]">{{ Format::money($situation['montant_en_attente']) }} DH</p>
        </div>
        <div class="surface p-4">
            <p class="text-xs font-semibold text-muted">{{ __('Redevance') }}</p>
            <p class="mt-1 text-sm font-semibold">{{ Format::money($situation['trim_base']) }} DH / trimestre · {{ Format::money($situation['an_base']) }} DH / an</p>
            <p class="text-xs text-muted">{{ $occupation->ends_on ? 'Autorisée jusqu’au '.$occupation->ends_on->format('d/m/Y') : 'Sans date de fin' }}</p>
        </div>
    </section>

    @if ($years)
        <form wire:submit="declare" class="grid gap-4 lg:grid-cols-3">
            <x-card class="lg:col-span-2" :padding="false">
                <x-slot:title>{{ __('Périodes à liquider') }}</x-slot:title>
                <div class="divide-y divide-line">
                    @foreach ($years as $year => $block)
                        @php($yearOn = isset($block['year']) && in_array($block['year']['key'], $selected, true))
                        <div class="px-4 py-3" wire:key="y-{{ $year }}">
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs font-bold uppercase tracking-[.06em] text-muted">{{ $year }}</p>
                                @isset($block['year'])
                                    <label @class(['flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold', 'border-gold bg-gold/15' => $yearOn, 'border-line' => ! $yearOn])>
                                        <input type="checkbox" value="{{ $block['year']['key'] }}" wire:model.live="selected" class="rounded border-line text-accent focus:ring-gold">
                                        Année entière · {{ Format::money($block['year']['calc']['total']) }} DH
                                    </label>
                                @endisset
                            </div>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                @foreach ($block['quarters'] ?? [] as $q)
                                    @php($on = in_array($q['key'], $selected, true))
                                    <label wire:key="q-{{ $q['key'] }}" @class([
                                        'flex flex-col rounded-xl border p-2.5 text-sm transition',
                                        'pointer-events-none opacity-40' => $yearOn,
                                        'cursor-pointer border-gold bg-gold/15 ring-1 ring-gold' => $on && ! $yearOn,
                                        'cursor-pointer border-line bg-white hover:border-gold' => ! $on && ! $yearOn,
                                    ])>
                                        <span class="flex items-center justify-between">
                                            <span class="font-semibold">T{{ $q['trimestre'] }}</span>
                                            <input type="checkbox" value="{{ $q['key'] }}" wire:model.live="selected" @disabled($yearOn) class="rounded border-line text-accent focus:ring-gold">
                                        </span>
                                        <span class="mt-1 text-xs font-bold tabular-nums text-[#2563eb]">{{ Format::money($q['calc']['total']) }} DH</span>
                                        <span class="text-[10.5px] text-muted">échéance {{ \Illuminate\Support\Carbon::parse($q['echeance'])->format('d/m/Y') }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="border-t border-line px-4 py-2 text-[11px] text-muted">Montant = tarif de l'arrêté fiscal × superficie occupée. Pas de pénalité ni de majoration de retard. Une période de moins de 100 DH est enregistrée « sous seuil », sans bulletin.</p>
                @error('selected')<p class="px-4 pb-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                @error('periods')<p class="px-4 pb-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
            </x-card>

            <x-card :title="__('Liquidation et bulletin')">
                <div class="space-y-3">
                    <div class="rounded-xl bg-navy p-3 text-white">
                        <p class="text-xs text-white/70">{{ count($selected) }} période(s) sélectionnée(s)</p>
                        <p class="mt-1 text-2xl font-bold tabular-nums text-gold">{{ Format::money($total) }} DH</p>
                    </div>
                    <x-field :label="__('Date')" for="d-date" error="declared_on"><x-input id="d-date" type="date" wire:model="declared_on" /></x-field>
                    <x-field label="N° de bulletin (vide = automatique)" for="d-slip" error="slip_number"><x-input id="d-slip" wire:model="slip_number" /></x-field>
                    @if ($this->allowed('bulletins.creer'))
                        <x-btn type="submit" variant="gold" icon="hand-coins" class="w-full" :disabled="! count($selected)">{{ __('Liquider et émettre') }}</x-btn>
                    @else
                        <p class="text-xs text-muted">{{ __('Vous n\'avez pas le droit d\'émettre des bulletins.') }}</p>
                    @endif
                </div>
            </x-card>
        </form>
    @else
        <x-card><x-empty-state icon="check" :title="__('Rien à liquider')">{{ $situation['montant_en_attente'] > 0 ? 'Les trimestres échus sont émis ('.Format::money($situation['montant_en_attente']).' DH en attente du régisseur).' : 'Occupation à jour.' }}</x-empty-state></x-card>
    @endif

    @include('livewire.domaine-public.partials.history', ['history' => $history])
</div>
