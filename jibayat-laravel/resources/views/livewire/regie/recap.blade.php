@php
    use App\Support\Format;
    use App\Domain\Regie\Services\Ledger;
    $modes = $r['modes'];
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-input type="date" wire:model.live="from" :aria-label="__('Du')" class="!w-40" />
            <x-input type="date" wire:model.live="to" :aria-label="__('Au')" class="!w-40" />
            <x-btn variant="secondary" icon="printer" :href="route('regie.imprimer.recap', ['du' => $from, 'au' => $to])" target="_blank">{{ __('Imprimer') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="{{ __('Indicateurs') }}">
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Total de la période') }}</p><p class="mt-1 text-lg font-bold tabular-nums">{{ Format::money($r['total']) }} DH</p></div>
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Pièces') }}</p><p class="mt-1 text-lg font-bold tabular-nums">{{ $r['pieces'] }}</p></div>
        @foreach (array_slice($r['totals'], 0, 2, true) as $m => $v)
            <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ tr(Ledger::modeLabel($m)) }}</p><p class="mt-1 text-lg font-bold tabular-nums">{{ Format::money($v) }} DH</p></div>
        @endforeach
    </section>

    @forelse ($r['families'] as $f)
        <x-card :padding="false" wire:key="f-{{ $f['family'] }}">
            <x-slot:title>{{ tr($f['label']) }}</x-slot:title>
            <x-slot:actions><strong class="text-sm tabular-nums">{{ Format::money($f['total']) }} DH</strong></x-slot:actions>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted"><tr><th class="px-3 py-2 text-start">{{ __('Rubrique') }}</th><th class="px-3 py-2 text-end">{{ __('Pièces') }}</th>
                        @foreach ($modes as $m)<th class="px-3 py-2 text-end">{{ tr(Ledger::modeLabel($m)) }}</th>@endforeach<th class="px-3 py-2 text-end">{{ __('Total') }}</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($f['rubrics'] as $row)
                            <tr><td class="px-3 py-2"><span class="font-mono text-xs font-bold">{{ $row['code'] }}</span> {{ $row['label'] }}</td><td class="px-3 py-2 text-end tabular-nums">{{ $row['pieces'] }}</td>
                                @foreach ($modes as $m)<td class="px-3 py-2 text-end tabular-nums">{{ isset($row['modes'][$m]) ? Format::money($row['modes'][$m]) : '—' }}</td>@endforeach
                                <td class="px-3 py-2 text-end font-semibold tabular-nums">{{ Format::money($row['total']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @empty
        <x-empty-state icon="sigma" :title="__('Aucune recette sur la période')" />
    @endforelse
</div>
