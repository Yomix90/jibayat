@php
    use App\Support\Format;
    use App\Domain\Regie\Services\Ledger;
    $modes = array_keys($s['modes']);
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-btn variant="ghost" size="sm" icon="chevron-left" wire:click="shift(-1)" :aria-label="__('Jour précédent')" />
            <x-input type="date" wire:model.live="date" :aria-label="__('Date')" class="!w-44" />
            <x-btn variant="ghost" size="sm" icon="chevron-right" wire:click="shift(1)" :aria-label="__('Jour suivant')" />
            <x-btn variant="secondary" icon="printer" :href="route('regie.imprimer.situation', ['date' => $date])" target="_blank">{{ __('Imprimer') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="{{ __('Indicateurs') }}">
        @foreach ([[__('Solde à l’ouverture'), $s['opening']], [__('Recettes du jour'), $s['receipts']], [__('Versements du jour'), $s['total_remittances']], [__('Solde à la fermeture'), $s['closing']]] as [$l, $v])
            <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ $l }}</p><p class="mt-1 text-lg font-bold tabular-nums">{{ Format::money($v) }} DH</p></div>
        @endforeach
    </section>

    <x-card :padding="false">
        <x-slot:title>{{ __('Recettes par rubrique') }}</x-slot:title>
        <x-slot:actions><span class="text-xs text-muted">{{ $s['pieces'] }} {{ __('pièce(s)') }}</span></x-slot:actions>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr><th class="px-3 py-2 text-start">{{ __('Rubrique') }}</th><th class="px-3 py-2 text-end">{{ __('Pièces') }}</th>
                        @foreach ($modes as $m)<th class="px-3 py-2 text-end">{{ tr(Ledger::modeLabel($m)) }}</th>@endforeach<th class="px-3 py-2 text-end">{{ __('Total') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($s['rubrics'] as $r)
                        <tr><td class="px-3 py-2"><span class="font-mono text-xs font-bold">{{ $r['code'] }}</span> {{ $r['label'] }}</td><td class="px-3 py-2 text-end tabular-nums">{{ $r['pieces'] }}</td>
                            @foreach ($modes as $m)<td class="px-3 py-2 text-end tabular-nums">{{ isset($r['modes'][$m]) ? Format::money($r['modes'][$m]) : '—' }}</td>@endforeach
                            <td class="px-3 py-2 text-end font-semibold tabular-nums">{{ Format::money($r['total']) }}</td></tr>
                    @empty
                        <tr><td colspan="{{ count($modes) + 3 }}" class="p-4"><x-empty-state icon="calendar-days" :title="__('Aucune recette ce jour')" /></td></tr>
                    @endforelse
                </tbody>
                @if ($s['rubrics'])
                    <tfoot class="border-t-2 border-gold bg-bg font-bold"><tr><td class="px-3 py-2">{{ __('Total') }}</td><td class="px-3 py-2 text-end tabular-nums">{{ $s['pieces'] }}</td>
                        @foreach ($modes as $m)<td class="px-3 py-2 text-end tabular-nums">{{ Format::money($s['modes'][$m]) }}</td>@endforeach<td class="px-3 py-2 text-end tabular-nums">{{ Format::money($s['receipts']) }}</td></tr></tfoot>
                @endif
            </table>
        </div>
    </x-card>

    @if ($s['remittances'])
        <x-card :title="__('Versements du jour')">
            <ul class="divide-y divide-line text-sm">@foreach ($s['remittances'] as $r)<li class="flex justify-between py-2"><span class="font-mono text-xs font-bold">{{ $r->number }}</span><span class="tabular-nums">{{ Format::money($r->amount) }} DH</span></li>@endforeach</ul>
        </x-card>
    @endif
    @if ((float) $s['ministry'] > 0)
        <p class="text-xs text-muted">{{ __('Virements du Ministère du jour (hors caisse)') }} : {{ Format::money($s['ministry']) }} DH</p>
    @endif
</div>
