@php use App\Support\Format; use App\Domain\Documents\Services\RegisterService; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-btn variant="secondary" icon="file-spreadsheet" :href="route('documents.export', ['type' => 'declarations', 'annee' => $year, 'module' => $module, 'q' => $search])">{{ __('Export CSV') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach ([['Déclarations', number_format($totals['n'], 0, ',', ' '), ''], ['Principal', Format::money($totals['principal']), ' DH'], ['Pénalités et amendes', Format::money($totals['penalties']), ' DH'], ['Total émis', Format::money($totals['total']), ' DH']] as [$l, $v, $u])
            <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ tr($l) }}</p><p class="mt-1 text-xl font-bold tabular-nums">{{ $v }}{{ $u }}</p></div>
        @endforeach
    </section>

    <div class="grid gap-2 sm:grid-cols-3">
        <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Redevable, CIN, n° de déclaration…')" :aria-label="__('Rechercher')" />
        <x-select wire:model.live="year" :aria-label="__('Année')"><option value="">{{ __('Toutes les années') }}</option>@foreach ($years as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach</x-select>
        <x-select wire:model.live="module" :aria-label="__('Taxe')"><option value="">{{ __('Toutes les taxes') }}</option>@foreach (RegisterService::MODULES as $k => $m)<option value="{{ $k }}">{{ $m[app()->isLocale('ar') ? 'ar' : 'fr'] }}</option>@endforeach</x-select>
    </div>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr><th class="px-3 py-2 text-start">{{ __('Déclaration') }}</th><th class="px-3 py-2 text-start">{{ __('Taxe · période') }}</th><th class="px-3 py-2 text-start">{{ __('Redevable') }}</th>
                        <th class="px-3 py-2 text-end">{{ __('Base') }}</th><th class="px-3 py-2 text-end">{{ __('Principal') }}</th><th class="px-3 py-2 text-end">{{ __('Pénalités') }}</th><th class="px-3 py-2 text-end">{{ __('Total') }}</th><th class="px-3 py-2 text-start">{{ __('Statut') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($rows as $r)
                        <tr wire:key="d-{{ $r->id }}">
                            <td class="px-3 py-2.5"><p class="font-mono text-xs font-semibold">{{ $r->number }}</p><p class="text-xs text-muted">{{ $r->declared_on ? \Illuminate\Support\Carbon::parse($r->declared_on)->format('d/m/Y') : '' }}</p></td>
                            <td class="px-3 py-2.5 text-xs">{{ RegisterService::MODULES[$r->module][app()->isLocale('ar') ? 'ar' : 'fr'] ?? $r->module }}<br><span class="text-muted">{{ $r->year }}{{ $r->quarter ? ' · T'.$r->quarter : '' }}</span></td>
                            <td class="px-3 py-2.5"><p class="font-semibold">{{ RegisterService::taxpayerName($r) }}</p><p class="text-xs text-muted">{{ $r->cin }}</p></td>
                            <td class="px-3 py-2.5 text-end tabular-nums text-muted">{{ Format::money($r->base_amount) }}</td>
                            <td class="px-3 py-2.5 text-end tabular-nums">{{ Format::money($r->principal) }}</td>
                            <td class="px-3 py-2.5 text-end tabular-nums">{{ Format::money($r->late_penalty + $r->surcharge + $r->non_filing_fine) }}</td>
                            <td class="px-3 py-2.5 text-end font-semibold tabular-nums">{{ Format::money($r->total) }}</td>
                            <td class="px-3 py-2.5">@if ($r->status === 'paye')<x-badge color="green">{{ __('Payée') }}</x-badge>@elseif ($r->status === 'sous_seuil')<x-badge>{{ __('Sous seuil') }}</x-badge>@else<x-badge color="gold">{{ __('Émise') }}</x-badge>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-4"><x-empty-state icon="file-text" :title="__('Aucune déclaration pour ces critères')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-line px-4 py-2">{{ $rows->links() }}</div>
    </x-card>
</div>
