@php
    use App\Support\Format;
    use App\Domain\Regie\Services\Ledger;
    use App\Domain\Regie\Services\MinutesService;
    $evo = $r['previous_total'] > 0 ? round(($r['total'] - $r['previous_total']) / $r['previous_total'] * 100, 1) : null;
    $max = max(1, max($r['months']));
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            @include('livewire.regie.partials.period', ['yearOnly' => true])
            <x-btn variant="secondary" icon="printer" :href="route('regie.imprimer.rapport-annuel', ['annee' => $year])" target="_blank">{{ __('Imprimer') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="{{ __('Indicateurs') }}">
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Recettes') }} {{ $year }}</p><p class="mt-1 text-lg font-bold tabular-nums">{{ Format::money($r['total']) }} DH</p>
            @if ($evo !== null)<p class="text-xs {{ $evo >= 0 ? 'text-online' : 'text-red-600' }}">{{ $evo >= 0 ? '+' : '' }}{{ number_format($evo, 1, ',', ' ') }} % {{ __('sur') }} {{ $year - 1 }}</p>@endif</div>
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Versé au comptable') }}</p><p class="mt-1 text-lg font-bold tabular-nums">{{ Format::money($r['remitted']) }} DH</p></div>
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Mois clôturés') }}</p><p class="mt-1 text-lg font-bold tabular-nums">{{ $r['closed'] }} / 12</p></div>
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Solde de caisse') }}</p><p class="mt-1 text-lg font-bold tabular-nums">{{ Format::money($r['closing']) }} DH</p></div>
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2" :title="__('Recettes mensuelles')">
            <div class="flex h-40 items-end gap-1.5" role="img" aria-label="{{ __('Recettes mensuelles') }}">
                @foreach ($r['months'] as $m => $v)
                    <div class="flex h-full flex-1 flex-col justify-end" title="{{ tr(Ledger::MONTHS[$m]) }} : {{ Format::money($v) }} DH"><div class="rounded-t-md bg-gold/70" style="height: {{ max(2, round($v / $max * 100)) }}%"></div><span class="mt-1 text-center text-[10px] text-muted">{{ mb_substr(tr(Ledger::MONTHS[$m]), 0, 3) }}</span></div>
                @endforeach
            </div>
        </x-card>
        <x-card :title="__('Activité de la régie')">
            <ul class="space-y-2 text-sm">
                @foreach ($r['families'] as $f => $v)<li class="flex justify-between"><span>{{ tr(Ledger::familyLabel($f)) }}</span><strong class="tabular-nums">{{ Format::money($v) }}</strong></li>@endforeach
                <li class="border-t border-line pt-2"></li>
                @foreach (MinutesService::TYPES as $k => $t)<li class="flex justify-between"><span>{{ app()->isLocale('ar') ? $t['ar'] : $t['fr'] }}</span><strong class="tabular-nums">{{ $r['reports'][$k] ?? 0 }}</strong></li>@endforeach
                <li class="flex justify-between"><span>{{ __('Pièces annulées') }}</span><strong class="tabular-nums">{{ $r['cancelled'] }}</strong></li>
            </ul>
        </x-card>
    </div>

    <x-card :padding="false" :title="__('Par rubrique')">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted"><tr><th class="px-3 py-2 text-start">{{ __('Rubrique') }}</th><th class="px-3 py-2 text-end">{{ $year - 1 }}</th><th class="px-3 py-2 text-end">{{ $year }}</th><th class="px-3 py-2 text-end">{{ __('Évolution') }}</th><th class="px-3 py-2 text-end">{{ __('Prévision budget') }}</th><th class="px-3 py-2 text-end">{{ __('Réalisation') }}</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($r['rows'] as $row)
                        <tr><td class="px-3 py-2"><span class="font-mono text-xs font-bold">{{ $row['code'] }}</span> {{ $row['label'] }}</td><td class="px-3 py-2 text-end tabular-nums text-muted">{{ Format::money($row['previous']) }}</td>
                            <td class="px-3 py-2 text-end font-semibold tabular-nums">{{ Format::money($row['total']) }}</td>
                            <td class="px-3 py-2 text-end tabular-nums {{ ($row['evolution'] ?? 0) >= 0 ? 'text-online' : 'text-red-600' }}">{{ $row['evolution'] === null ? '—' : ($row['evolution'] >= 0 ? '+' : '').number_format($row['evolution'], 1, ',', ' ').' %' }}</td>
                            <td class="px-3 py-2 text-end tabular-nums">{{ $row['budget'] === null ? '—' : Format::money($row['budget']) }}</td>
                            <td class="px-3 py-2 text-end tabular-nums">{{ $row['budget'] ? number_format($row['total'] / $row['budget'] * 100, 1, ',', ' ').' %' : '—' }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="p-4"><x-empty-state icon="book-marked" :title="__('Aucune recette cette année')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
