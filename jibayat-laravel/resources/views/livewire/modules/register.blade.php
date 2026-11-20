@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />

    <div class="grid gap-2 sm:grid-cols-3">
        <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('N° déclaration, BV, quittance, nom, CIN…')" :aria-label="__('Rechercher')" />
        <x-select wire:model.live="year" :aria-label="__('Année')">
            <option value="">{{ __('Toutes les années') }}</option>
            @foreach ($years as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach
        </x-select>
        <x-select wire:model.live="status" :aria-label="__('Statut')">
            <option value="all">{{ __('Tous') }}</option><option value="paye">{{ __('Payés') }}</option><option value="en_attente">{{ __('En attente') }}</option><option value="impaye">{{ __('Non payés') }}</option>
        </x-select>
    </div>

    <section class="grid grid-cols-3 gap-3">
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Lignes') }}</p><p class="text-xl font-bold">{{ $totals->n }}</p></div>
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Émis (DH)') }}</p><p class="text-xl font-bold tabular-nums">{{ Format::money($totals->emitted) }}</p></div>
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Encaissé (DH)') }}</p><p class="text-xl font-bold tabular-nums text-online">{{ Format::money($totals->paid) }}</p></div>
    </section>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr><th class="px-4 py-2 text-start">{{ __('Déclaration') }}</th><th class="px-4 py-2 text-start">{{ __('Redevable') }}</th><th class="px-4 py-2 text-start">{{ __('Période') }}</th><th class="px-4 py-2 text-end">{{ __('Montant') }}</th><th class="px-4 py-2 text-start">{{ __('BV / quittance') }}</th><th class="px-4 py-2 text-start">{{ __('Statut') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($rows as $r)
                        <tr wire:key="r-{{ $r->id }}-{{ $r->slip_number }}">
                            <td class="px-4 py-2.5 font-mono text-xs">{{ $r->number }}<br><span class="text-muted">{{ $r->declared_on ? \Carbon\Carbon::parse($r->declared_on)->format('d/m/Y') : '' }}</span></td>
                            <td class="px-4 py-2.5"><p class="font-semibold">{{ Format::taxpayer($r) }}</p><p class="text-xs text-muted">{{ $r->cin }}</p></td>
                            <td class="px-4 py-2.5 text-xs">{{ \Illuminate\Support\Str::before((string) $r->notes, '|') ?: Format::period($r) }}</td>
                            <td class="px-4 py-2.5 text-end font-semibold tabular-nums">{{ Format::money($r->total) }}</td>
                            <td class="px-4 py-2.5 text-xs">{{ $r->slip_number ? 'BV '.$r->slip_number : '—' }}@if ($r->receipt_number)<br><span class="font-semibold text-online">Q {{ $r->receipt_number }} · {{ \Carbon\Carbon::parse($r->receipt_date)->format('d/m/Y') }}</span>@endif</td>
                            <td class="px-4 py-2.5">
                                @if ($r->slip_status === 'paye' || $r->status === 'paye')<x-badge color="green">{{ __('Payé') }}</x-badge>
                                @elseif ($r->slip_status === 'rejete')<x-badge color="red">{{ __('Rejeté') }}</x-badge>
                                @elseif ($r->slip_status === 'en_attente')<x-badge color="gold">{{ __('En attente') }}</x-badge>
                                @elseif ($r->status === 'sous_seuil')<x-badge>{{ __('Sous seuil') }}</x-badge>
                                @else<x-badge color="orange">{{ __('Sans bulletin') }}</x-badge>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-4"><x-empty-state icon="book-marked" :title="__('Registre vide')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
    {{ $rows->links() }}
</div>
