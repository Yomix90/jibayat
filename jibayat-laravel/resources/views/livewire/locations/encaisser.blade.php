@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" :title="'Bail n° '.$lease->number.' — '.($lease->shop?->number ?? $lease->premises_ref ?? 'Local communal')"
                   :subtitle="$lease->taxpayer?->displayName().' · loyer actuel '.Format::money($rent).' DH/mois'">
        <x-slot:actions>
            <x-btn variant="secondary" size="sm" icon="file-text" :href="route('locations.fiche', ['bail' => $lease->id])">{{ __('Fiche') }}</x-btn>
            <x-btn variant="ghost" size="sm" icon="chevron-left" wire:click="$set('leaseId', null)">{{ __('Autre bail') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <section class="grid gap-3 sm:grid-cols-3">
        <div class="surface p-4">
            <p class="text-xs font-semibold text-muted">{{ __('Période non payée') }}</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-red-600">{{ Format::money($dueTotal) }} DH</p>
            <p class="text-xs text-muted">{{ $period }} · <span class="font-ar" lang="ar">{{ $periodAr }}</span></p>
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

    @if (count($available))
        <form wire:submit="collect" class="grid gap-4 lg:grid-cols-3">
            <x-card class="lg:col-span-2">
                <x-slot:title>{{ __('Mois à encaisser') }}</x-slot:title>
                <x-slot:actions>
                    <label class="flex items-center gap-2 text-xs text-muted">{{ __('Les') }}
                        <x-input type="number" min="0" :max="count($available)" wire:model.live.debounce.400ms="count" class="!w-16 text-center" :aria-label="__('Nombre de mois')" />
                        {{ __('premiers mois') }}
                    </label>
                </x-slot:actions>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($months as $m)
                        <label wire:key="m-{{ $m['mois'] }}" @class([
                            'flex cursor-pointer flex-col rounded-xl border p-2.5 text-sm transition',
                            'cursor-not-allowed border-dashed border-gold/50 bg-gold/10 opacity-80' => $m['est_emis'],
                            'border-gold bg-gold/15 ring-1 ring-gold' => ! $m['est_emis'] && in_array($m['mois'], $selected, true),
                            'border-line bg-white hover:border-gold' => ! $m['est_emis'] && ! in_array($m['mois'], $selected, true),
                        ])>
                            <span class="flex items-center justify-between gap-1">
                                <span class="font-semibold text-ink">{{ tr($m['label']) }}</span>
                                @unless ($m['est_emis'])
                                    <input type="checkbox" value="{{ $m['mois'] }}" wire:model.live="selected" class="rounded border-line text-accent focus:ring-gold">
                                @endunless
                            </span>
                            <span class="mt-1 text-xs font-bold tabular-nums text-[#2563eb]">{{ Format::money($m['tarif']) }} DH</span>
                            <span class="text-[10.5px] text-muted">{{ $m['est_emis'] ? 'Émis — en attente' : (str_starts_with($m['tarif_source'], 'arrete:') ? 'Arrêté '.substr($m['tarif_source'], 7) : ($m['tarif_source'] === 'loc_tarif' ? 'Tarif du local' : 'Loyer du bail')) }}</span>
                        </label>
                    @endforeach
                </div>
                @error('months')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
            </x-card>

            <x-card :title="__('Bulletin de versement')">
                <div class="space-y-3">
                    <div class="rounded-xl bg-navy p-3 text-white">
                        <p class="text-xs text-white/70">{{ count($selected) }} mois sélectionné(s)</p>
                        @foreach ($groups as $g)
                            <p class="text-xs text-white/80">{{ $g['nb_mois'] }} × {{ Format::money($g['tarif']) }} = {{ Format::money($g['montant']) }} DH</p>
                        @endforeach
                        <p class="mt-1 text-2xl font-bold tabular-nums text-gold">{{ Format::money($total) }} DH</p>
                    </div>
                    <x-field label="N° de bulletin (laisser vide = automatique)" for="e-slip" error="slip_number"><x-input id="e-slip" wire:model="slip_number" /></x-field>
                    <div class="grid grid-cols-2 gap-2">
                        <x-field :label="__('Mode')" for="e-mode" error="payment_mode">
                            <x-select id="e-mode" wire:model="payment_mode">
                                <option value="especes">{{ __('Espèces') }}</option><option value="cheque">{{ __('Chèque') }}</option>
                                <option value="virement">{{ __('Virement') }}</option><option value="bulletin_manuel">{{ __('Bulletin manuel') }}</option>
                            </x-select>
                        </x-field>
                        <x-field :label="__('Date')" for="e-date" error="paid_on"><x-input id="e-date" type="date" wire:model="paid_on" /></x-field>
                    </div>
                    <x-field :label="__('Notes')" for="e-notes" error="notes"><x-input id="e-notes" wire:model="notes" /></x-field>
                    @if ($this->allowed('bulletins.creer'))
                        <x-btn type="submit" variant="gold" icon="hand-coins" class="w-full" :disabled="! count($selected)">{{ __('Émettre le bulletin') }}</x-btn>
                    @else
                        <p class="text-xs text-muted">{{ __('Vous n\'avez pas le droit d\'émettre des bulletins.') }}</p>
                    @endif
                </div>
            </x-card>
        </form>
    @else
        <x-card>
            <x-empty-state icon="check" :title="__('Rien à encaisser')">
                {{ $pendingTotal > 0 ? 'Tous les loyers échus sont émis ('.Format::money($pendingTotal).' DH en attente du régisseur).' : 'Bail à jour.' }}
            </x-empty-state>
        </x-card>
    @endif

    <x-card :title="__('Historique des paiements de loyers')" :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr><th class="px-4 py-2 text-start">{{ __('Déclaration / bulletin') }}</th><th class="px-4 py-2 text-start">{{ __('Mois') }}</th><th class="px-4 py-2 text-end">{{ __('Montant') }}</th><th class="px-4 py-2 text-start">{{ __('Statut') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($history as $h)
                        <tr wire:key="h-{{ $h->id }}">
                            <td class="px-4 py-2.5"><p class="font-mono text-xs font-semibold">{{ $h->number }}</p><p class="text-xs text-muted">BV {{ $h->slip_number ?? '—' }}</p></td>
                            <td class="px-4 py-2.5 text-xs">{{ \Illuminate\Support\Str::before((string) $h->notes, '|') }}</td>
                            <td class="px-4 py-2.5 text-end font-semibold tabular-nums">{{ Format::money($h->total) }}</td>
                            <td class="px-4 py-2.5">
                                @if ($h->status === 'paye')<x-badge color="green">Payé · Q {{ $h->receipt_number }}</x-badge>
                                @elseif ($h->status === 'annule')<x-badge>{{ __('Annulé') }}</x-badge>
                                @elseif ($h->slip_status === 'rejete')<x-badge color="red">Rejeté : {{ $h->rejection_reason }}</x-badge>
                                @else<x-badge color="gold">{{ __('En attente régisseur') }}</x-badge>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-4 text-center text-sm text-muted">{{ __('Aucun paiement enregistré.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
