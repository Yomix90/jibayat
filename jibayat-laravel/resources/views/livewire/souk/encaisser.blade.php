@php use App\Support\Format; use App\Domain\Souk\Services\SoukCalculator; $t = $contract->taxpayer; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" :title="'Contrat n° '.$contract->number.' — '.$contract->souk_name"
                   :subtitle="$t?->displayName().' · redevance actuelle '.Format::money($situation['current_fee']).' DH/mois'">
        <x-slot:actions>
            <x-btn variant="secondary" size="sm" icon="file-text" :href="route('souk.fiche', ['contrat' => $contract->id])">{{ __('Fiche') }}</x-btn>
            <x-btn variant="ghost" size="sm" icon="chevron-left" wire:click="$set('contractId', null)">{{ __('Autre contrat') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <section class="grid gap-3 sm:grid-cols-3">
        <div class="surface p-4">
            <p class="text-xs font-semibold text-muted">{{ __('Mois à encaisser') }}</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-red-600">{{ Format::money($situation['total_non_declare']) }} DH</p>
            <p class="text-xs text-muted">{{ $situation['nb_non_declares'] }} mois · {{ SoukCalculator::periodLabel($available) }}</p>
        </div>
        <div class="surface p-4">
            <p class="text-xs font-semibold text-muted">{{ __('En attente du régisseur') }}</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-[#7a5d16]">{{ Format::money($situation['total_en_attente']) }} DH</p>
            <p class="text-xs text-muted">{{ $situation['nb_emises'] }} mois</p>
        </div>
        <div class="surface p-4">
            <p class="text-xs font-semibold text-muted">{{ __('Contrat') }}</p>
            <p class="mt-1 text-sm font-semibold">{{ $contract->starts_on?->format('d/m/Y') }} → {{ $contract->ends_on?->format('d/m/Y') ?? '…' }}</p>
            <p class="text-xs text-muted">+{{ rtrim(rtrim(number_format(SoukCalculator::rate($contract), 2, ',', ''), '0'), ',') }} % tous les {{ SoukCalculator::duration($contract) }} an(s)</p>
        </div>
    </section>

    @if (count($available))
        <form wire:submit="collect" class="grid gap-4 lg:grid-cols-3">
            <x-card class="lg:col-span-2">
                <x-slot:title>{{ __('Mois à payer (les plus anciens d\'abord)') }}</x-slot:title>
                <x-slot:actions>
                    <label class="flex items-center gap-2 text-xs text-muted">{{ __('Les') }}
                        <x-input type="number" min="1" :max="count($available)" wire:model.live.debounce.400ms="count" class="!w-16 text-center" :aria-label="__('Nombre de mois')" />
                        {{ __('premiers mois') }}
                    </label>
                </x-slot:actions>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($situation['months'] as $i => $m)
                        @php($on = in_array($m['mois'], array_column($chosen, 'mois'), true))
                        <div wire:key="m-{{ $m['mois'] }}" @class([
                            'flex flex-col rounded-xl border p-2.5 text-sm',
                            'border-dashed border-gold/50 bg-gold/10 opacity-80' => $m['est_emis'],
                            'border-gold bg-gold/15 ring-1 ring-gold' => ! $m['est_emis'] && $on,
                            'border-line bg-white' => ! $m['est_emis'] && ! $on,
                        ])>
                            <span class="font-semibold text-ink">{{ tr($m['label']) }}</span>
                            <span class="mt-1 text-xs font-bold tabular-nums text-[#2563eb]">{{ Format::money($m['redevance']) }} DH</span>
                            <span class="text-[10.5px] text-muted">{{ $m['est_emis'] ? 'Émis — en attente' : ($m['renouvellement'] ? 'Période '.($m['renouvellement'] + 1) : 'Période initiale') }}</span>
                        </div>
                    @endforeach
                </div>
                @error('months')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                @error('count')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
            </x-card>

            <x-card :title="__('Bulletin de versement')">
                <div class="space-y-3">
                    <div class="rounded-xl bg-navy p-3 text-white">
                        <p class="text-xs text-white/70">{{ count($chosen) }} mois sélectionné(s)</p>
                        @foreach ($groups as $g)
                            <p class="text-xs text-white/80">{{ $g['nb_mois'] }} × {{ Format::money($g['redevance_mensuelle']) }} = {{ Format::money($g['montant']) }} DH</p>
                        @endforeach
                        <p class="mt-1 text-2xl font-bold tabular-nums text-gold">{{ Format::money($total) }} DH</p>
                    </div>
                    <x-field label="N° de bulletin (vide = automatique)" for="e-slip" error="slip_number"><x-input id="e-slip" wire:model="slip_number" /></x-field>
                    <div class="grid grid-cols-2 gap-2">
                        <x-field :label="__('Mode')" for="e-mode" error="payment_mode">
                            <x-select id="e-mode" wire:model="payment_mode">
                                <option value="especes">{{ __('Espèces') }}</option><option value="cheque">{{ __('Chèque') }}</option>
                                <option value="virement">{{ __('Virement') }}</option><option value="bulletin_manuel">{{ __('Bulletin manuel') }}</option>
                            </x-select>
                        </x-field>
                        <x-field :label="__('N° de versement')" for="e-transfer" error="transfer_number"><x-input id="e-transfer" wire:model="transfer_number" /></x-field>
                    </div>
                    <x-field :label="__('Notes')" for="e-notes" error="notes"><x-input id="e-notes" wire:model="notes" /></x-field>
                    @if ($this->allowed('bulletins.creer'))
                        <x-btn type="submit" variant="gold" icon="hand-coins" class="w-full" :disabled="! count($chosen)">{{ __('Émettre le bulletin') }}</x-btn>
                    @else
                        <p class="text-xs text-muted">{{ __('Vous n\'avez pas le droit d\'émettre des bulletins.') }}</p>
                    @endif
                </div>
            </x-card>
        </form>
    @else
        <x-card><x-empty-state icon="check" :title="__('Rien à encaisser')">{{ $situation['total_en_attente'] > 0 ? 'Les mois dus sont émis ('.Format::money($situation['total_en_attente']).' DH en attente du régisseur).' : 'Contrat à jour.' }}</x-empty-state></x-card>
    @endif

    @include('livewire.souk.partials.history', ['history' => $history])
</div>
