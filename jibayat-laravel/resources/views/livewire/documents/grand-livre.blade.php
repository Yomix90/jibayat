@php use App\Support\Format; use App\Domain\Documents\Services\RegisterService; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-btn variant="secondary" icon="file-spreadsheet" :href="route('documents.export', ['type' => 'paiements', 'annee' => $year, 'module' => $module, 'statut' => $status, 'q' => $search])">{{ __('Export CSV') }}</x-btn>
            <x-btn variant="secondary" icon="printer" :href="route('documents.imprimer-grand-livre', ['annee' => $year, 'module' => $module, 'statut' => $status, 'q' => $search])" target="_blank">{{ __('Imprimer') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="{{ __('Totaux') }}">
        @foreach ([['Montant émis', $totals['emitted'], 'text-ink'], ['Recouvré (quittancé)', $totals['paid'], 'text-online'], ['Reste à recouvrer', $totals['remaining'], 'text-red-600']] as [$l, $v, $c])
            <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ tr($l) }}</p><p class="mt-1 text-xl font-bold tabular-nums {{ $c }}">{{ Format::money($v) }} DH</p></div>
        @endforeach
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Taux de recouvrement') }}</p><p class="mt-1 text-xl font-bold tabular-nums">{{ number_format($totals['rate'], 1, ',', ' ') }} %</p>
            <p class="text-xs text-muted">{{ $totals['n'] }} déclaration(s)</p></div>
    </section>

    <div class="grid gap-2 sm:grid-cols-4">
        <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Redevable, CIN, n° de déclaration, BV, quittance…')" :aria-label="__('Rechercher')" />
        <x-select wire:model.live="year" :aria-label="__('Année')"><option value="">{{ __('Toutes les années') }}</option>@foreach ($years as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach</x-select>
        <x-select wire:model.live="module" :aria-label="__('Taxe')"><option value="">{{ __('Toutes les taxes') }}</option>@foreach (RegisterService::MODULES as $k => $m)<option value="{{ $k }}">{{ $m[app()->isLocale('ar') ? 'ar' : 'fr'] }}</option>@endforeach</x-select>
        <x-select wire:model.live="status" :aria-label="__('Statut')"><option value="all">{{ __('Tous') }}</option><option value="paye">{{ __('Quittancés') }}</option><option value="en_attente">{{ __('En attente') }}</option></x-select>
    </div>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr><th class="px-4 py-2 text-start">{{ __('Déclaration') }}</th><th class="px-4 py-2 text-start">{{ __('Taxe') }}</th><th class="px-4 py-2 text-start">{{ __('Redevable') }}</th>
                        <th class="px-4 py-2 text-end">{{ __('Émis') }}</th><th class="px-4 py-2 text-start">{{ __('BV / quittance') }}</th><th class="px-4 py-2 text-end">{{ __('Recouvré') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($rows as $r)
                        @php($paid = $r->slip_status === 'paye' || $r->status === 'paye')
                        <tr wire:key="r-{{ $r->id }}">
                            <td class="px-4 py-2.5"><p class="font-mono text-xs font-semibold">{{ $r->number }}</p><p class="text-xs text-muted">{{ $r->year }}{{ $r->quarter ? ' · T'.$r->quarter : '' }} · {{ $r->declared_on ? \Illuminate\Support\Carbon::parse($r->declared_on)->format('d/m/Y') : '' }}</p></td>
                            <td class="px-4 py-2.5 text-xs">{{ RegisterService::MODULES[$r->module][app()->isLocale('ar') ? 'ar' : 'fr'] ?? $r->module }}</td>
                            <td class="px-4 py-2.5"><p class="font-semibold">{{ RegisterService::taxpayerName($r) }}</p><p class="text-xs text-muted">{{ $r->cin }}</p></td>
                            <td class="px-4 py-2.5 text-end font-semibold tabular-nums">{{ Format::money($r->total) }}</td>
                            <td class="px-4 py-2.5 text-xs">BV {{ $r->slip_number ?? '—' }}@if ($r->receipt_number)<br><span class="font-semibold">Q {{ $r->receipt_number }}</span> · {{ \Illuminate\Support\Carbon::parse($r->receipt_date)->format('d/m/Y') }}@endif</td>
                            <td class="px-4 py-2.5 text-end tabular-nums">@if ($paid)<span class="font-semibold text-online">{{ Format::money($r->amount ?? 0) }}</span>@elseif ($r->status === 'sous_seuil')<x-badge>{{ __('Sous seuil') }}</x-badge>@else<x-badge color="gold">{{ __('En attente') }}</x-badge>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-4"><x-empty-state icon="book-open" :title="__('Aucune recette pour ces critères')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-line px-4 py-2">{{ $rows->links() }}</div>
    </x-card>
</div>
