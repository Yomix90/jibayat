@php use App\Support\Format; use App\Domain\Boissons\Services\BeverageCalculator; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" :title="'Déclaration annuelle '.$year.' — '.$outlet->name" :subtitle="$outlet->taxpayer?->displayName().' · n° '.$number">
        <x-slot:actions>
            <x-btn variant="gold" icon="printer" :href="route('boissons.annuelle-imprimer', ['outlet' => $outlet->id, 'annee' => $year])" target="_blank">{{ __('Imprimer') }}</x-btn>
            <x-btn variant="ghost" icon="chevron-left" wire:click="$set('outletId', null)">{{ __('Autre établissement') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <div class="flex items-center gap-3 text-sm">
        <label for="a-year" class="font-semibold">{{ __('Année') }}</label>
        <x-select id="a-year" wire:model.live="year" class="!w-28">
            @for ($y = now()->year; $y >= 2020; $y--)<option value="{{ $y }}">{{ $y }}</option>@endfor
        </x-select>
        <span class="ms-auto text-muted">{{ $paid }} / 4 trimestres payés · taux {{ rtrim(rtrim(number_format($rate, 2, ',', ''), '0'), ',') }} %</span>
    </div>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr><th class="px-4 py-2 text-start">{{ __('Trimestre') }}</th><th class="px-4 py-2 text-end">{{ __('CA déclaré') }}</th><th class="px-4 py-2 text-end">{{ __('Taxe') }}</th>
                        <th class="px-4 py-2 text-end">{{ __('Pénalités et amendes') }}</th><th class="px-4 py-2 text-end">{{ __('Total') }}</th><th class="px-4 py-2 text-start">{{ __('Statut') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($rows as $q => $r)
                        @php($d = $r['declaration'])
                        <tr>
                            <td class="px-4 py-2.5 font-semibold">{{ BeverageCalculator::QUARTERS[$q] }}</td>
                            @if ($d)
                                <td class="px-4 py-2.5 text-end tabular-nums">{{ Format::money($d->base_amount) }}</td>
                                <td class="px-4 py-2.5 text-end tabular-nums">{{ Format::money($d->principal) }}</td>
                                <td class="px-4 py-2.5 text-end tabular-nums">{{ Format::money($r['pen']) }}</td>
                                <td class="px-4 py-2.5 text-end font-bold tabular-nums">{{ Format::money($d->total) }}</td>
                                <td class="px-4 py-2.5">@if ($d->status === 'paye')<x-badge color="green">{{ __('Payé') }}</x-badge>@elseif ($d->status === 'sous_seuil')<x-badge>{{ __('Sous seuil') }}</x-badge>@else<x-badge color="gold">{{ __('Émis') }}</x-badge>@endif</td>
                            @else
                                <td colspan="4" class="px-4 py-2.5 text-end text-muted">—</td><td class="px-4 py-2.5"><x-badge color="red">{{ __('Non déclaré') }}</x-badge></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t border-line bg-bg font-bold">
                    <tr><td class="px-4 py-2.5">{{ __('Total annuel') }}</td><td class="px-4 py-2.5 text-end tabular-nums">{{ Format::money($totals['base']) }}</td>
                        <td class="px-4 py-2.5 text-end tabular-nums">{{ Format::money($totals['principal']) }}</td><td class="px-4 py-2.5 text-end tabular-nums">{{ Format::money($totals['pen']) }}</td>
                        <td class="px-4 py-2.5 text-end tabular-nums">{{ Format::money($totals['total']) }} DH</td><td></td></tr>
                </tfoot>
            </table>
        </div>
    </x-card>
</div>
