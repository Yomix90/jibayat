@php use App\Support\Format; @endphp
<x-card :title="__('Paiements trimestriels')" :padding="false">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                <tr><th class="px-4 py-2 text-start">{{ __('Période') }}</th><th class="px-4 py-2 text-start">{{ __('Déclaration / bulletin') }}</th><th class="px-4 py-2 text-end">{{ __('Stat. + TPV') }}</th><th class="px-4 py-2 text-end">{{ __('Montant') }}</th><th class="px-4 py-2 text-start">{{ __('Statut') }}</th></tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($history as $h)
                    <tr wire:key="h-{{ $h->id }}" @class(['opacity-60' => $h->cancelled_at || $h->status === 'annule'])>
                        <td class="px-4 py-2.5 font-semibold">T{{ $h->quarter }} {{ $h->year }}</td>
                        <td class="px-4 py-2.5"><p class="font-mono text-xs font-semibold">{{ $h->number }}</p><p class="text-xs text-muted">BV {{ $h->slip_number ?? '—' }} · {{ $h->declared_on ? \Illuminate\Support\Carbon::parse($h->declared_on)->format('d/m/Y') : '' }}</p></td>
                        <td class="px-4 py-2.5 text-end text-xs tabular-nums text-muted">{{ Format::money($h->rate) }} + {{ Format::money($h->base_amount) }}</td>
                        <td class="px-4 py-2.5 text-end font-semibold tabular-nums">{{ Format::money($h->total) }}</td>
                        <td class="px-4 py-2.5">
                            @if ($h->cancelled_at || $h->status === 'annule')<x-badge>Annulé{{ $h->cancellation_reason ? ' : '.$h->cancellation_reason : '' }}</x-badge>
                            @elseif ($h->status === 'paye')<x-badge color="green">Payé · Q {{ $h->receipt_number }}</x-badge>
                            @elseif ($h->status === 'sous_seuil')<x-badge>{{ __('Sous seuil') }}</x-badge>
                            @elseif ($h->slip_status === 'rejete')<x-badge color="red">Rejeté : {{ $h->rejection_reason }}</x-badge>
                            @else<x-badge color="gold">{{ __('En attente régisseur') }}</x-badge>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-4 text-center text-sm text-muted">{{ __('Aucun paiement.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
