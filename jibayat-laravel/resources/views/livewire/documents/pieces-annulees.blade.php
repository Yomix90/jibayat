@php use App\Support\Format; use App\Domain\Documents\Services\RegisterService; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-btn variant="secondary" icon="printer" :href="route('documents.imprimer-pieces-annulees', ['module' => $module, 'q' => $search])" target="_blank">{{ __('Imprimer') }}</x-btn>
        </x-slot:actions>
    </x-page-header>
    <p class="text-sm text-muted">{{ __('Une pièce officielle ne se supprime pas : elle s\'annule avec un motif, un auteur et une date, et reste consultable ici.') }}</p>

    <div class="grid gap-2 sm:grid-cols-[1fr_16rem]">
        <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('N° de pièce, motif…')" :aria-label="__('Rechercher')" />
        <x-select wire:model.live="module" :aria-label="__('Taxe')"><option value="">{{ __('Toutes les taxes') }}</option><option value="REGIE">{{ __('Régie des recettes') }}</option>@foreach (RegisterService::MODULES as $k => $m)<option value="{{ $k }}">{{ $m[app()->isLocale('ar') ? 'ar' : 'fr'] }}</option>@endforeach</x-select>
    </div>

    <x-card :padding="false">
        <div class="flex items-center justify-between border-b border-line px-4 py-2 text-xs text-muted">
            <span>{{ count($rows) }} pièce(s)</span><span>{{ __('Montant total annulé :') }} <strong class="tabular-nums text-ink">{{ Format::money($total) }} DH</strong></span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr><th class="px-4 py-2 text-start">{{ __('Pièce') }}</th><th class="px-4 py-2 text-start">{{ __('Taxe · redevable') }}</th><th class="px-4 py-2 text-end">{{ __('Montant') }}</th>
                        <th class="px-4 py-2 text-start">{{ __('Annulée le · par') }}</th><th class="px-4 py-2 text-start">{{ __('Motif') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($rows as $r)
                        <tr>
                            <td class="px-4 py-2.5"><x-badge>{{ $r->kind }}</x-badge><p class="mt-0.5 font-mono text-xs font-semibold">{{ $r->number }}</p></td>
                            <td class="px-4 py-2.5 text-xs">{{ RegisterService::MODULES[$r->module][app()->isLocale('ar') ? 'ar' : 'fr'] ?? ($r->module === 'REGIE' ? __('Régie des recettes') : $r->module) }}<br><span class="font-semibold text-ink">{{ RegisterService::taxpayerName($r) }}</span></td>
                            <td class="px-4 py-2.5 text-end tabular-nums">{{ Format::money($r->amount) }}</td>
                            <td class="px-4 py-2.5 text-xs">{{ $r->cancelled_at ? \Illuminate\Support\Carbon::parse($r->cancelled_at)->format('d/m/Y H:i') : 'Statut « annulé » (Flask)' }}<br><span class="text-muted">{{ $r->by_name ?? '—' }}</span></td>
                            <td class="px-4 py-2.5 text-xs">{{ $r->reason ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4"><x-empty-state icon="check" :title="__('Aucune pièce annulée')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
