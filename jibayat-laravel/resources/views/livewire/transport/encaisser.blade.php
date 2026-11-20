@php use App\Support\Format; $t = $vehicle->taxpayer; [$sta, $tpv] = $amounts; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" :title="$vehicle->registration.' — '.$vehicle->type"
                   :subtitle="$t?->displayName().' · stationnement '.Format::money($sta).' + TPV '.Format::money($tpv).' DH par trimestre'">
        <x-slot:actions>
            <x-btn variant="secondary" size="sm" icon="file-text" :href="route('transport.fiche', ['vehicule' => $vehicle->id])">{{ __('Fiche') }}</x-btn>
            <x-btn variant="ghost" size="sm" icon="chevron-left" wire:click="$set('vehicleId', null)">{{ __('Autre véhicule') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <section class="grid gap-3 sm:grid-cols-3">
        <div class="surface p-4">
            <p class="text-xs font-semibold text-muted">{{ __('Trimestres à payer') }}</p>
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
        <form wire:submit="pay" class="grid gap-4 lg:grid-cols-3">
            <x-card class="lg:col-span-2" :padding="false">
                <x-slot:title>Calcul au {{ \Illuminate\Support\Carbon::parse($paid_on ?: today())->format('d/m/Y') }}</x-slot:title>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                            <tr><th class="px-3 py-2"></th><th class="px-3 py-2 text-start">{{ __('Trimestre') }}</th><th class="px-3 py-2 text-end">{{ __('Stationnement') }}</th>
                                <th class="px-3 py-2 text-end">TPV</th><th class="px-3 py-2 text-end">{{ __('Total') }}</th></tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($lines as $l)
                                @php($c = $l['calc'])
                                <tr wire:key="q-{{ $l['key'] }}" @class(['bg-gold/10' => in_array($l['key'], $selected, true)])>
                                    <td class="px-3 py-2"><input type="checkbox" value="{{ $l['key'] }}" wire:model.live="selected" class="rounded border-line text-accent focus:ring-gold" aria-label="T{{ $l['trimestre'] }} {{ $l['annee'] }}"></td>
                                    <td class="px-3 py-2"><p class="font-semibold">T{{ $l['trimestre'] }} {{ $l['annee'] }}</p><p class="text-[11px] text-muted">échéance {{ \Illuminate\Support\Carbon::parse($l['echeance'])->format('d/m/Y') }}</p></td>
                                    <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($c['sta']) }}</td>
                                    <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($c['tpv']) }}</td>
                                    <td class="px-3 py-2 text-end font-bold tabular-nums">{{ Format::money($c['total']) }} @if ($c['statut'] === 'sous_seuil')<x-badge class="ms-1">{{ __('sous seuil') }}</x-badge>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="border-t border-line px-4 py-2 text-[11px] text-muted">{{ __('Échéance : dernier jour du mois qui suit le trimestre (T4 : 31 janvier de l\'année suivante). Stationnement et TPV sont trimestriels (tarifs de l\'arrêté fiscal) et ne sont pas soumis aux pénalités de retard.') }}</p>
                @error('selected')<p class="px-4 pb-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
            </x-card>

            <x-card :title="__('Paiement et bulletin')">
                <div class="space-y-3">
                    <div class="rounded-xl bg-navy p-3 text-white">
                        <p class="text-xs text-white/70">{{ count($selected) }} trimestre(s) sélectionné(s)</p>
                        <p class="mt-1 text-2xl font-bold tabular-nums text-gold">{{ Format::money($total) }} DH</p>
                    </div>
                    <x-field :label="__('Date de paiement')" for="p-date" error="paid_on" :hint="__('Date du bulletin.')">
                        <x-input id="p-date" type="date" wire:model.live="paid_on" />
                    </x-field>
                    <x-field label="N° de bulletin (vide = automatique)" for="p-slip" error="slip_number"><x-input id="p-slip" wire:model="slip_number" /></x-field>
                    @if ($this->allowed('bulletins.creer'))
                        <x-btn type="submit" variant="gold" icon="hand-coins" class="w-full" :disabled="! count($selected)">{{ __('Émettre le bulletin') }}</x-btn>
                    @else
                        <p class="text-xs text-muted">{{ __('Vous n\'avez pas le droit d\'émettre des bulletins.') }}</p>
                    @endif
                </div>
            </x-card>
        </form>
    @else
        <x-card><x-empty-state icon="check" :title="__('Aucun trimestre à payer')">{{ $pendingTotal > 0 ? 'Les trimestres échus sont émis ('.Format::money($pendingTotal).' DH en attente du régisseur).' : 'Véhicule à jour.' }}</x-empty-state></x-card>
    @endif

    @include('livewire.transport.partials.declarations', ['history' => $history])
</div>
