@php
    use App\Support\Format;
    use App\Domain\Regie\Services\Ledger;
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            @include('livewire.regie.partials.period', ['yearOnly' => true])
            <x-btn variant="secondary" icon="printer" :href="route('regie.imprimer.operations', ['annee' => $year])" target="_blank">{{ __('Imprimer') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-3" aria-label="{{ __('Indicateurs') }}">
        @foreach ([[__('Recettes de l’année'), $ops['total_receipts']], [__('Versées au comptable'), $ops['total_remittances']], [__('Versements accusés'), $ops['total_acknowledged']]] as [$l, $v])
            <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ $l }}</p><p class="mt-1 text-lg font-bold tabular-nums">{{ Format::money($v) }} DH</p></div>
        @endforeach
    </section>

    <x-card :padding="false">
        <x-slot:title>{{ __('Modèle 7') }} — {{ $year }}</x-slot:title>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr><th class="px-3 py-2 text-start">{{ __('Mois') }}</th><th class="px-3 py-2 text-end">{{ __('Solde au début') }}</th><th class="px-3 py-2 text-end">{{ __('Recettes') }}</th>
                        <th class="px-3 py-2 text-end">{{ __('Versements') }}</th><th class="px-3 py-2 text-end">{{ __('Accusés') }}</th><th class="px-3 py-2 text-end">{{ __('Solde à la fin') }}</th><th class="px-3 py-2 text-start">{{ __('Bordereaux') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($ops['months'] as $m)
                        <tr><td class="px-3 py-2 font-semibold">{{ tr(Ledger::MONTHS[$m['month']]) }}</td>
                            <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($m['opening']) }}</td><td class="px-3 py-2 text-end tabular-nums">{{ Format::money($m['receipts']) }}</td>
                            <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($m['remittances']) }}</td>
                            <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($m['acknowledged']) }}@if ($m['pending']) <x-badge color="gold">{{ $m['pending'] }} {{ __('en attente') }}</x-badge>@endif</td>
                            <td class="px-3 py-2 text-end font-semibold tabular-nums">{{ Format::money($m['closing']) }}</td>
                            <td class="px-3 py-2 text-xs text-muted">{{ collect($m['items'])->pluck('number')->implode(', ') ?: '—' }}</td></tr>
                    @empty
                        <tr><td colspan="7" class="p-4"><x-empty-state icon="landmark" :title="__('Aucune opération')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
