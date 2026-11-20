@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            @include('livewire.regie.partials.period')
            <x-btn variant="secondary" icon="printer" :href="route('regie.imprimer.livre', ['annee' => $year, 'mois' => $month])" target="_blank">{{ __('Imprimer') }}</x-btn>
            @if ($this->allowed('config.gerer'))<x-btn variant="ghost" icon="settings-2" wire:click="openSettings" :aria-label="__('Paramètres de la régie')" />@endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="{{ __('Indicateurs') }}">
        @foreach ([[__('Report du mois précédent'), $book['carried'], 'text-ink'], [__('Recettes du mois'), $book['total_receipts'], 'text-online'],
                   [__('Versements au comptable'), $book['total_remittances'], 'text-accent'], [__('Solde en caisse'), $book['closing'], 'text-ink']] as [$l, $v, $c])
            <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ $l }}</p><p class="mt-1 text-lg font-bold tabular-nums {{ $c }}">{{ Format::money($v) }} DH</p></div>
        @endforeach
    </section>

    @if ($closure)
        <div class="surface flex items-center gap-3 p-4 text-sm"><x-svg-icon name="lock" class="h-5 w-5 text-gold" />{{ __('Mois clôturé le') }} {{ $closure->closed_at->format('d/m/Y H:i') }} — {{ __('solde arrêté à') }} <strong class="tabular-nums">{{ Format::money($closure->closing_balance) }} DH</strong></div>
    @endif
    @if ($opening['date'] === '2000-01-01' && $this->allowed('config.gerer'))
        <div class="surface border-gold/40 bg-gold/5 p-4 text-sm">{{ __('Le solde de départ de la régie n’est pas renseigné.') }} <button type="button" wire:click="openSettings" class="font-semibold text-accent underline">{{ __('Le renseigner') }}</button></div>
    @endif

    <x-card :padding="false">
        <x-slot:title>{{ __('Livre de caisse') }} — {{ tr(\App\Domain\Regie\Services\Ledger::MONTHS[$month]) }} {{ $year }}</x-slot:title>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr><th class="px-3 py-2 text-start">{{ __('Date') }}</th><th class="px-3 py-2 text-end">{{ __('Pièces') }}</th><th class="px-3 py-2 text-end">{{ __('Espèces') }}</th>
                        <th class="px-3 py-2 text-end">{{ __('Chèques, virements') }}</th><th class="px-3 py-2 text-end">{{ __('Total recettes') }}</th>
                        <th class="px-3 py-2 text-end">{{ __('Versements') }}</th><th class="px-3 py-2 text-end">{{ __('Solde') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr class="bg-gold/5"><td class="px-3 py-2 font-semibold" colspan="6">{{ __('Report') }}</td><td class="px-3 py-2 text-end font-semibold tabular-nums">{{ Format::money($book['carried']) }}</td></tr>
                    @forelse ($book['days'] as $d)
                        <tr wire:key="d-{{ $d['date'] }}">
                            <td class="whitespace-nowrap px-3 py-2"><a href="{{ route('regie.situation', ['date' => $d['date']]) }}" class="font-semibold hover:text-accent">{{ \Carbon\Carbon::parse($d['date'])->format('d/m/Y') }}</a></td>
                            <td class="px-3 py-2 text-end tabular-nums">{{ $d['pieces'] }}</td>
                            <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($d['cash']) }}</td>
                            <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($d['other']) }}</td>
                            <td class="px-3 py-2 text-end font-semibold tabular-nums">{{ Format::money($d['receipts']) }}</td>
                            <td class="px-3 py-2 text-end tabular-nums text-accent">@if ($d['remittances']){{ Format::money($d['remittances']) }}<span class="block text-[11px] text-muted">{{ implode(', ', $d['remittance_refs']) }}</span>@else — @endif</td>
                            <td class="px-3 py-2 text-end font-semibold tabular-nums">{{ Format::money($d['balance']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-4"><x-empty-state icon="book-open" :title="__('Aucune opération ce mois')" /></td></tr>
                    @endforelse
                </tbody>
                <tfoot class="border-t-2 border-gold bg-bg font-bold"><tr><td class="px-3 py-2" colspan="2">{{ __('Totaux du mois') }}</td>
                    <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($book['total_cash']) }}</td><td class="px-3 py-2 text-end tabular-nums">{{ Format::money($book['total_other']) }}</td>
                    <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($book['total_receipts']) }}</td><td class="px-3 py-2 text-end tabular-nums">{{ Format::money($book['total_remittances']) }}</td>
                    <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($book['closing']) }}</td></tr></tfoot>
            </table>
        </div>
    </x-card>
    <p class="text-xs text-muted">{{ __('Recettes : bulletins quittancés des modules et recettes saisies et validées à la régie. Les virements du Ministère ne passent pas par la caisse et n’y figurent pas.') }}</p>

    <x-panel show="showSettings" title="Paramètres de la régie">
        <form wire:submit="saveSettings" class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <x-field :label="__('Solde de départ (DH)')" for="s-sol" error="settings.solde_initial"><x-input id="s-sol" type="number" step="0.01" min="0" wire:model="settings.solde_initial" /></x-field>
                <x-field :label="__('À la date du')" for="s-date" error="settings.solde_initial_date"><x-input id="s-date" type="date" wire:model="settings.solde_initial_date" /></x-field>
            </div>
            <x-field :label="__('Plafond d’encaisse (DH, 0 = aucun)')" for="s-pl" error="settings.plafond"><x-input id="s-pl" type="number" step="0.01" min="0" wire:model="settings.plafond" /></x-field>
            <x-field :label="__('Comptable de rattachement')" for="s-c" error="settings.comptable"><x-input id="s-c" wire:model="settings.comptable" /></x-field>
            <x-field :label="__('Régisseur des recettes')" for="s-r" error="settings.regisseur"><x-input id="s-r" wire:model="settings.regisseur" /></x-field>
            <x-field :label="__('RIB du compte de la commune (virements)')" for="s-rib" error="settings.rib"><x-input id="s-rib" wire:model="settings.rib" inputmode="numeric" /></x-field>
            <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('showSettings', false)">{{ __('Annuler') }}</x-btn><x-btn type="submit" variant="gold" icon="check">{{ __('Enregistrer') }}</x-btn></div>
        </form>
    </x-panel>
</div>
