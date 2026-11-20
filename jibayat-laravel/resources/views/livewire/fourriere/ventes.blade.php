@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions><x-btn variant="secondary" icon="gavel" :href="route('fourriere.groupes')">{{ __('Groupes d\'enchères') }}</x-btn></x-slot:actions>
    </x-page-header>
    <div class="surface flex flex-wrap items-center gap-4 p-4">
        <div><p class="text-xs font-semibold text-muted">{{ __('Véhicules vendus') }}</p><p class="text-2xl font-bold tabular-nums">{{ $sales->count() }}</p></div>
        <div><p class="text-xs font-semibold text-muted">{{ __('Total des adjudications') }}</p><p class="text-2xl font-bold tabular-nums text-online">{{ Format::money($total) }} DH</p></div>
    </div>
    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr><th class="px-4 py-2 text-start">{{ __('Date') }}</th><th class="px-4 py-2 text-start">{{ __('Véhicule') }}</th><th class="px-4 py-2 text-start">{{ __('Acheteur') }}</th>
                        <th class="px-4 py-2 text-end">{{ __('Prix') }}</th><th class="px-4 py-2 text-start">{{ __('Quittance / PV') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($sales as $s)
                        <tr wire:key="s-{{ $s->id }}">
                            <td class="px-4 py-2.5">{{ $s->sold_on?->format('d/m/Y') }}</td>
                            <td class="px-4 py-2.5"><p class="font-mono text-xs font-bold" dir="ltr">{{ $s->impoundCase?->registration }}</p><p class="text-xs text-muted">{{ $s->auctionGroup?->number }} · {{ $s->impoundCase?->vehicle_type }}</p></td>
                            <td class="px-4 py-2.5"><p class="font-semibold">{{ $s->buyer_name }}</p><p class="text-xs text-muted">{{ $s->buyer_cin }} {{ $s->buyer_phone }}</p></td>
                            <td class="px-4 py-2.5 text-end font-semibold tabular-nums">{{ Format::money($s->hammer_price) }}</td>
                            <td class="px-4 py-2.5 text-xs">{{ $s->receipt_number ?: '—' }}
                                @if ($s->report_path)<a href="{{ route('fourriere.pv', $s) }}" class="ms-2 font-semibold text-accent hover:underline">PV</a>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4"><x-empty-state icon="scale" :title="__('Aucune vente')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
