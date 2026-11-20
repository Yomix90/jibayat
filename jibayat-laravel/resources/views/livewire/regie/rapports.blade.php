@php
    use App\Support\Format;
    use App\Domain\Regie\Services\Ledger;
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-btn variant="secondary" icon="printer" :href="route('regie.imprimer.rapport', ['du' => $from, 'au' => $to, 'rubrique' => $rubric])" target="_blank">{{ __('Imprimer') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap items-center gap-2">
        <x-input type="date" wire:model.live="from" :aria-label="__('Du')" class="!w-40" />
        <x-input type="date" wire:model.live="to" :aria-label="__('Au')" class="!w-40" />
        <x-select wire:model.live="rubric" :aria-label="__('Rubrique')" class="!w-60"><option value="">{{ __('Toutes les rubriques') }}</option>@foreach ($rubrics as $r)<option value="{{ $r->id }}">{{ $r->code }} · {{ $r->label }}</option>@endforeach</x-select>
        <div class="inline-flex rounded-full border border-line bg-white p-0.5 text-xs font-semibold" role="group" aria-label="{{ __('Période') }}">
            @foreach (['today' => 'Aujourd’hui', 'week' => 'Semaine', 'month' => 'Mois', 'quarter' => 'Trimestre', 'year' => 'Année'] as $k => $l)
                <button type="button" wire:click="preset('{{ $k }}')" class="rounded-full px-3 py-1.5 text-muted hover:text-ink">{{ __($l) }}</button>
            @endforeach
        </div>
    </div>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="{{ __('Indicateurs') }}">
        @foreach ([[__('Solde au début'), $opening], [__('Recettes de la période'), $receipts->sum('amount')], [__('Versements'), $remittances->sum('amount')], [__('Solde à la fin'), $closing]] as [$l, $v])
            <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ $l }}</p><p class="mt-1 text-lg font-bold tabular-nums">{{ Format::money($v) }} DH</p></div>
        @endforeach
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card :title="__('Par famille')">
            <ul class="space-y-2 text-sm">
                @forelse ($recap['families'] as $f)<li class="flex justify-between"><span>{{ tr($f['label']) }}</span><strong class="tabular-nums">{{ Format::money($f['total']) }}</strong></li>@empty<li class="text-muted">—</li>@endforelse
            </ul>
            <p class="mt-4 text-xs font-bold uppercase tracking-[.06em] text-muted">{{ __('Par mode') }}</p>
            <ul class="mt-2 space-y-2 text-sm">
                @foreach ($recap['totals'] as $m => $v)<li class="flex justify-between"><span>{{ tr(Ledger::modeLabel($m)) }}</span><strong class="tabular-nums">{{ Format::money($v) }}</strong></li>@endforeach
            </ul>
        </x-card>
        <x-card class="lg:col-span-2" :padding="false">
            <x-slot:title>{{ __('Détail des pièces') }}</x-slot:title>
            <x-slot:actions><span class="text-xs text-muted">{{ $receipts->count() }} {{ __('pièce(s)') }}</span></x-slot:actions>
            <div class="max-h-[32rem] overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted"><tr><th class="px-3 py-2 text-start">{{ __('Date') }}</th><th class="px-3 py-2 text-start">{{ __('Pièce') }}</th><th class="px-3 py-2 text-start">{{ __('Rubrique') }}</th><th class="px-3 py-2 text-start">{{ __('Mode') }}</th><th class="px-3 py-2 text-end">{{ __('Montant') }}</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($receipts->take(500) as $r)
                            <tr><td class="whitespace-nowrap px-3 py-1.5">{{ \Carbon\Carbon::parse($r->date)->format('d/m/Y') }}</td><td class="px-3 py-1.5"><span class="font-mono text-xs">{{ $r->ref }}</span> <span class="text-xs text-muted">{{ $r->payer }}</span></td>
                                <td class="px-3 py-1.5 text-xs">{{ $r->code }}</td><td class="px-3 py-1.5 text-xs">{{ tr(Ledger::modeLabel($r->mode)) }}</td><td class="px-3 py-1.5 text-end tabular-nums">{{ Format::money($r->amount) }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="p-4"><x-empty-state icon="file-bar-chart" :title="__('Aucune recette sur la période')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
</div>
