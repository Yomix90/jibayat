@php
    use App\Support\Format;
    use App\Domain\Regie\Services\Ledger;
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            @include('livewire.regie.partials.period', ['yearOnly' => true])
            @if ($this->allowed('recettes.exporter'))<x-btn variant="secondary" icon="file-spreadsheet" :href="route('regie.export.tableau', ['annee' => $year])">Excel</x-btn>@endif
            <x-btn variant="secondary" icon="printer" :href="route('regie.imprimer.tableau', ['annee' => $year])" target="_blank">{{ __('Imprimer') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.04em] text-muted">
                    <tr><th class="sticky start-0 bg-bg px-3 py-2 text-start">{{ __('Rubrique') }}</th>@foreach (Ledger::MONTHS as $m)<th class="px-2 py-2 text-end">{{ mb_substr(tr($m), 0, 4) }}</th>@endforeach<th class="px-3 py-2 text-end">{{ __('Total') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($t['rows'] as $r)
                        <tr><td class="sticky start-0 bg-white px-3 py-2"><span class="font-mono font-bold">{{ $r['code'] }}</span> <span class="block max-w-56 truncate text-muted">{{ $r['label'] }}</span></td>
                            @foreach ($r['months'] as $v)<td class="px-2 py-2 text-end tabular-nums {{ $v ? '' : 'text-line' }}">{{ $v ? Format::short($v) : '·' }}</td>@endforeach
                            <td class="px-3 py-2 text-end font-semibold tabular-nums">{{ Format::money($r['total']) }}</td></tr>
                    @empty
                        <tr><td colspan="14" class="p-4"><x-empty-state icon="table" :title="__('Aucune recette cette année')" /></td></tr>
                    @endforelse
                </tbody>
                @if ($t['rows'])
                    <tfoot class="border-t-2 border-gold bg-bg font-bold"><tr><td class="sticky start-0 bg-bg px-3 py-2">{{ __('Total') }}</td>
                        @foreach ($t['totals'] as $v)<td class="px-2 py-2 text-end tabular-nums">{{ Format::short($v) }}</td>@endforeach<td class="px-3 py-2 text-end tabular-nums">{{ Format::money($t['total']) }}</td></tr></tfoot>
                @endif
            </table>
        </div>
    </x-card>
</div>
