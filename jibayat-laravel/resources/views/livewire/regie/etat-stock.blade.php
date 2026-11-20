@php
    use App\Support\Format;
    $cols = ['tgr' => 'Réserve TGR', 'regie' => 'Régie', 'with_agents' => 'Chez les agents', 'consumed' => 'Consommé'];
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions><x-btn variant="secondary" icon="printer" :href="route('regie.imprimer.etat-stock')" target="_blank">{{ __('Imprimer') }}</x-btn></x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted"><tr><th class="px-3 py-2 text-start">{{ __('Valeur') }}</th><th class="px-3 py-2 text-end">{{ __('P.U.') }}</th>
                    @foreach ($cols as $l)<th class="px-3 py-2 text-end">{{ __($l) }}</th>@endforeach<th class="px-3 py-2 text-end">{{ __('Valeur en régie') }}</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($state as $v)
                        <tr><td class="px-3 py-2 font-semibold">{{ $v['label'] }}</td><td class="px-3 py-2 text-end tabular-nums">{{ Format::money($v['unit_value']) }}</td>
                            @foreach ($cols as $k => $l)<td class="px-3 py-2 text-end tabular-nums">{{ number_format($v[$k], 0, ',', ' ') }}</td>@endforeach
                            <td class="px-3 py-2 text-end font-semibold tabular-nums">{{ Format::money($v['regie'] * $v['unit_value']) }}</td></tr>
                    @empty
                        <tr><td colspan="7" class="p-4"><x-empty-state icon="clipboard-list" :title="__('Aucune valeur active')" /></td></tr>
                    @endforelse
                </tbody>
                @if ($state)
                    <tfoot class="border-t-2 border-gold bg-bg font-bold"><tr><td class="px-3 py-2" colspan="2">{{ __('Total en valeur (DH)') }}</td>
                        @foreach ($cols as $k => $l)<td class="px-3 py-2 text-end tabular-nums">{{ Format::money(collect($state)->sum(fn ($v) => $v[$k] * $v['unit_value'])) }}</td>@endforeach
                        <td class="px-3 py-2 text-end tabular-nums">{{ Format::money(collect($state)->sum(fn ($v) => $v['regie'] * $v['unit_value'])) }}</td></tr></tfoot>
                @endif
            </table>
        </div>
    </x-card>
</div>
