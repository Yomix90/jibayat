@php
    use App\Support\Format;
    $levels = ['ok' => ['green', 'Suffisant'], 'bas' => ['gold', 'Stock bas'], 'rupture' => ['red', 'Rupture']];
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>@if ($this->allowed('regie.exporter'))<x-btn variant="secondary" icon="file-spreadsheet" :href="route('regie.export.intelligence')">Excel</x-btn>@endif</x-slot:actions>
    </x-page-header>

    <p class="text-sm text-muted">{{ __('Consommation = carnets remis et tickets sortis (moins les retours), en moyenne mensuelle depuis la première sortie des 12 derniers mois. Commande conseillée : de quoi couvrir 3 mois.') }}</p>

    <div class="grid gap-3 lg:grid-cols-2">
        @forelse ($i['rows'] as $r)
            @php($max = max(1, max($r['series'])))
            <x-card wire:key="i-{{ $r['id'] }}">
                <div class="flex items-start justify-between gap-2">
                    <div><p class="font-semibold">{{ $r['label'] }}</p><p class="text-xs text-muted">{{ Format::money($r['unit_value']) }} DH {{ __('l’unité') }}</p></div>
                    <x-badge :color="$levels[$r['level']][0]">{{ __($levels[$r['level']][1]) }}</x-badge>
                </div>
                <div class="mt-3 flex h-16 items-end gap-1" role="img" aria-label="{{ __('Sorties mensuelles') }}">
                    @foreach ($r['series'] as $m => $q)<div class="flex-1 rounded-t bg-gold/60" style="height: {{ max(2, round($q / $max * 100)) }}%" title="{{ $m }} : {{ $q }}"></div>@endforeach
                </div>
                <dl class="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
                    <div class="rounded-lg bg-bg p-2"><dt class="text-muted">{{ __('Moyenne / mois') }}</dt><dd class="font-bold tabular-nums">{{ number_format($r['average'], 1, ',', ' ') }}</dd></div>
                    <div class="rounded-lg bg-bg p-2"><dt class="text-muted">{{ __('Couverture régie') }}</dt><dd class="font-bold tabular-nums">{{ $r['cover'] === null ? '—' : number_format($r['cover'], 1, ',', ' ').' '.__('mois') }}</dd></div>
                    <div class="rounded-lg bg-bg p-2"><dt class="text-muted">{{ __('Couverture TGR') }}</dt><dd class="font-bold tabular-nums">{{ $r['tgr_cover'] === null ? '—' : number_format($r['tgr_cover'], 1, ',', ' ').' '.__('mois') }}</dd></div>
                </dl>
                @if ($r['order'] > 0)
                    <p class="mt-3 rounded-xl bg-gold/10 px-3 py-2 text-sm">{{ __('Retrait conseillé à la TGR') }} : <strong class="tabular-nums">{{ number_format($r['order'], 0, ',', ' ') }}</strong> {{ __('unité(s)') }} ({{ Format::money($r['order_value']) }} DH)</p>
                @endif
            </x-card>
        @empty
            <x-empty-state class="lg:col-span-2" icon="brain" :title="__('Aucune valeur active')" />
        @endforelse
    </div>
</div>
