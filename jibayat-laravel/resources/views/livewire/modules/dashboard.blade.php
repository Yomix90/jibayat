@php use App\Support\Format; @endphp
<div class="space-y-6" style="--from: {{ $cfg['colors']['from'] }}; --to: {{ $cfg['colors']['to'] }}">
    <x-page-header :route="$this->pageRoute()" :subtitle="tr($cfg['label']).' · '.$year">
        <x-slot:actions><x-btn variant="secondary" icon="list" :href="route($cfg['route'])">{{ __('Liste') }}</x-btn></x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-3 2xl:grid-cols-6">
        @foreach ([
            [__('Dossiers actifs'), $kpi['files'], __('tous statuts'), null],
            [__('Déclarations').' '.$year, $kpi['declarations_year'], __(':a émise(s) · :b sous seuil', ['a' => $kpi['emitted_year'], 'b' => $kpi['below_threshold_year']]), null],
            [__('Recouvré (DH)'), Format::short($kpi['paid']), __(':n quittance(s)', ['n' => $kpi['paid_count']]), 'text-online'],
            [__('Impayé (DH)'), Format::short($kpi['unpaid']), __(':n déclaration(s) en attente', ['n' => $kpi['unpaid_count']]), 'text-red-600'],
            [__('Taux de recouvrement'), Format::money($kpi['rate'], 1).' %', __('payé / émis'), null],
            [__('Avis de non-paiement'), $kpi['notices'], __('avis émis actifs'), null],
        ] as [$label, $value, $sub, $class])
            <div class="surface p-4">
                <p class="text-xs font-semibold text-muted">{{ tr($label) }}</p>
                <p @class(['mt-1 text-xl font-bold tabular-nums', $class ?? 'text-ink'])>{{ $value }}</p>
                <p class="text-[11px] text-muted">{{ $sub }}</p>
            </div>
        @endforeach
    </section>

    <section class="grid gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2" :title="__('Recouvrement mensuel').' '.$year">
            <div class="relative h-60"><canvas data-chart="monthly" data-values='@json($monthly)'></canvas></div>
        </x-card>
        <x-card :title="__('Répartition des statuts')">
            @if (collect($statuses['data'])->sum() > 0)
                <div class="relative h-60"><canvas data-chart="modules" data-values='@json($statuses)'></canvas></div>
            @else
                <x-empty-state icon="chart-column" :title="__('Aucune déclaration')" />
            @endif
        </x-card>
    </section>

    <section class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Historique par année')" :padding="false">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted"><tr><th class="px-4 py-2 text-start">{{ __('Année') }}</th><th class="px-4 py-2 text-end">{{ __('Décl.') }}</th><th class="px-4 py-2 text-end">{{ __('Émis') }}</th><th class="px-4 py-2 text-end">{{ __('Payé') }}</th><th class="px-4 py-2 text-end">{{ __('Taux') }}</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($byYear as $y)
                        <tr><td class="px-4 py-2 font-semibold">{{ $y->year }}</td><td class="px-4 py-2 text-end">{{ $y->n }}</td>
                            <td class="px-4 py-2 text-end tabular-nums">{{ Format::money($y->emitted) }}</td><td class="px-4 py-2 text-end tabular-nums text-online">{{ Format::money($y->paid) }}</td>
                            <td class="px-4 py-2 text-end">{{ Format::money(\App\Domain\Dashboard\DashboardStats::rate((float) $y->paid, (float) $y->emitted), 1) }} %</td></tr>
                    @empty
                        <tr><td colspan="5" class="p-4 text-center text-muted">{{ __('Aucune donnée.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>
        <x-card :title="__('Déclarations récentes')" :padding="false">
            <ul class="divide-y divide-line">
                @forelse ($recent as $d)
                    <li class="flex items-center gap-3 px-4 py-2.5 text-sm">
                        <div class="min-w-0 flex-1"><p class="truncate font-semibold">{{ Format::taxpayer($d) }}</p><p class="truncate text-xs text-muted">{{ $d->number }} · {{ \Illuminate\Support\Str::before((string) $d->notes, '|') ?: Format::period($d) }}</p></div>
                        <p class="font-bold tabular-nums">{{ Format::money($d->total) }}</p>
                        @if ($d->status === 'paye')<x-badge color="green">{{ __('Payé') }}</x-badge>@elseif ($d->status === 'annule')<x-badge>{{ __('Annulé') }}</x-badge>@else<x-badge color="orange">{{ $d->status === 'sous_seuil' ? 'Sous seuil' : 'Émis' }}</x-badge>@endif
                    </li>
                @empty
                    <li class="p-4 text-center text-sm text-muted">{{ __('Aucune déclaration.') }}</li>
                @endforelse
            </ul>
        </x-card>
    </section>
</div>
