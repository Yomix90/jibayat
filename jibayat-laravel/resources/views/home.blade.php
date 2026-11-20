@php
    use App\Support\Format;
    $moduleLabels = collect(\App\Domain\Dashboard\DashboardStats::MODULES)->map(fn ($m) => $m['label']);
@endphp
<x-app-layout :title="__('Tableau de bord')">
    <div class="space-y-6">
        {{-- En-tête --}}
        <section class="zellige rounded-[24px] bg-navy p-5 text-white shadow-lg lg:p-6" style="--zellige-opacity: .12">
            <div class="flex flex-wrap items-center gap-4">
                <x-emblem icon="landmark" :size="56" fill="#C9A13B" stroke="rgba(255,255,255,.35)" icon-class="relative h-[42%] w-[42%] text-navy" />
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold uppercase tracking-[.08em] text-gold">{{ __('Tableau de bord') }} · {{ $year }}</p>
                    <h1 class="text-lg font-bold leading-tight lg:text-xl">{{ __('Plateforme fiscale & recouvrement communal') }}</h1>
                    <p class="mt-0.5 text-sm text-white/75">
                        @if (app()->isLocale('ar') && $commune?->name_ar){{ $commune->name_ar }}@if ($commune?->province_ar) · {{ $commune->province_ar }}@endif @else{{ $commune?->name ?? __('Commune') }}@if ($commune?->province) · {{ $commune->province }}@endif @endif
                        @if ($commune?->name_ar && ! app()->isLocale('ar'))<span class="font-ar ms-2" lang="ar">{{ $commune->name_ar }}</span>@endif
                    </p>
                </div>
                <div class="flex w-full flex-wrap gap-2 sm:w-auto">
                    @can('contribuables.ajouter')
                        <x-btn variant="gold" size="sm" icon="user-plus" :href="route('caisse.nouveau-contribuable')">{{ __('Contribuable') }}</x-btn>
                    @endcan
                    @canany(['bulletins.creer', 'paiements.valider'])
                        <a href="{{ route('caisse.bulletins') }}" class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/20">
                            <x-svg-icon name="receipt" class="h-4 w-4" /> {{ __('Bulletins') }}
                            @if ($totals['pending_count'] > 0)<span class="rounded-full bg-accent px-1.5 text-[11px]">{{ $totals['pending_count'] }}</span>@endif
                        </a>
                    @endcanany
                </div>
            </div>
        </section>

        {{-- Indicateurs --}}
        <section class="grid grid-cols-2 gap-3 md:grid-cols-3 2xl:grid-cols-5" aria-label="{{ __('Indicateurs') }}">
            @foreach ([
                ['banknote', __('Total émis'), Format::money($totals['emitted']), __('DH'), __(':n déclaration(s)', ['n' => $totals['declarations']]), '#5B7590', null],
                ['receipt', __('Recouvré & quittancé'), Format::money($totals['paid']), __('DH'), __('Taux : :rate %', ['rate' => Format::money($totals['rate'], 1)]), '#16A34A', 'text-online'],
                ['clock', __('En attente régisseur'), Format::money($totals['pending_amount']), __('DH'), __(':n bulletin(s)', ['n' => $totals['pending_count']]), '#C9A13B', null],
                ['triangle-alert', __('Reste à recouvrer'), Format::money($totals['remaining']), __('DH'), __(':n avis d’impayé émis', ['n' => $totals['notices']]), '#C8601E', 'text-red-600'],
                ['users', __('Redevables'), number_format($totals['taxpayers'], 0, ',', ' '), '', __(':n dossier(s) fiscaux', ['n' => $totals['files']]), '#1E2A3A', null],
            ] as [$icon, $label, $value, $unit, $sub, $color, $subClass])
                <div @class(['surface p-4', 'col-span-2 md:col-span-1' => $loop->last])>
                    <div class="flex items-center gap-2">
                        <span class="grid h-9 w-9 place-items-center rounded-xl text-white" style="background: {{ $color }}"><x-svg-icon :name="$icon" class="h-[18px] w-[18px]" /></span>
                        <p class="text-xs font-semibold text-muted">{{ tr($label) }}</p>
                    </div>
                    <p class="mt-3 text-xl font-bold tabular-nums text-ink lg:text-[22px]">{{ $value }} <span class="text-xs font-semibold text-muted">{{ $unit }}</span></p>
                    <p @class(['mt-0.5 text-xs font-semibold', $subClass ?? 'text-muted'])>{{ $sub }}</p>
                </div>
            @endforeach
        </section>

        {{-- Graphiques --}}
        <section class="grid gap-4 lg:grid-cols-3">
            <x-card class="lg:col-span-2">
                <x-slot:title>{{ __('Recouvrement mensuel — émis / encaissé') }} · {{ $year }}</x-slot:title>
                <div class="relative h-64">
                    <canvas data-chart="monthly" data-values='@json($monthly)' aria-label="{{ __('Graphique mensuel des montants émis et encaissés') }}"></canvas>
                </div>
            </x-card>
            <x-card :title="__('Répartition des recettes encaissées')">
                @php($pie = collect($modules)->filter(fn ($m) => $m['paid'] > 0))
                @if ($pie->isEmpty())
                    <x-empty-state icon="chart-column" :title="__('Aucun encaissement pour l\'instant')">{{ __('Le graphique apparaîtra après les premières quittances.') }}</x-empty-state>
                @else
                    <div class="relative h-64">
                        <canvas data-chart="modules" aria-label="{{ __('Répartition des recettes par module') }}"
                                data-values='@json(['labels' => $pie->pluck('label')->map(fn ($l) => tr($l))->values(), 'data' => $pie->pluck('paid')->values(), 'colors' => $pie->map(fn ($m) => $m['colors']['from'])->values()])'></canvas>
                    </div>
                @endif
            </x-card>
        </section>

        {{-- Situation par module --}}
        <section class="space-y-3">
            <x-section-title :count="count($modules)">{{ __('Situation par module') }}</x-section-title>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($modules as $m)
                    <a href="{{ route($m['route']) }}" wire:key="mod-{{ $m['code'] }}"
                       class="group surface p-4 transition hover:shadow-md"
                       style="--from: {{ $m['colors']['from'] }}; --to: {{ $m['colors']['to'] }}">
                        <div class="flex items-center gap-3">
                            <span class="icon-square !h-10 !w-10"><x-svg-icon :name="$m['icon']" class="h-5 w-5" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold text-ink">{{ tr($m['label']) }}</p>
                                <p class="text-xs text-muted">{{ __(':n actif(s)', ['n' => $m['count']]) }} · {{ __(':n déclaration(s)', ['n' => $m['declarations']]) }}</p>
                            </div>
                            <x-svg-icon name="chevron-right" class="h-4 w-4 text-muted transition group-hover:translate-x-0.5 rtl:rotate-180" />
                        </div>
                        <dl class="mt-3 space-y-1 text-sm">
                            <div class="flex justify-between"><dt class="text-muted">{{ __('Total émis') }}</dt><dd class="font-semibold tabular-nums text-ink">{{ Format::money($m['emitted']) }} DH</dd></div>
                            <div class="flex justify-between"><dt class="text-muted">{{ __('Recouvré') }}</dt><dd class="font-semibold tabular-nums text-online">{{ Format::money($m['paid']) }} DH</dd></div>
                            <div class="flex justify-between"><dt class="text-muted">{{ __('Reste dû') }}</dt><dd class="font-semibold tabular-nums text-red-600">{{ Format::money($m['remaining']) }} DH</dd></div>
                        </dl>
                        <div class="mt-3">
                            <div class="h-2 overflow-hidden rounded-full bg-bg" role="progressbar" aria-valuenow="{{ $m['rate'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Taux de recouvrement') }}">
                                <div class="h-full rounded-full" style="width: {{ min($m['rate'], 100) }}%; background: linear-gradient(90deg, var(--from), var(--to))"></div>
                            </div>
                            <p class="mt-1 flex justify-between text-[11px] text-muted">
                                <span>{{ __('Taux de recouvrement') }}</span><span class="font-bold text-ink">{{ Format::money($m['rate'], 1) }} %</span>
                            </p>
                        </div>
                    </a>
                @empty
                    <x-empty-state icon="grid-2x2" :title="__('Aucun module accessible')" class="sm:col-span-2 xl:col-span-3" />
                @endforelse
            </div>
        </section>

        {{-- Activité récente --}}
        <section class="grid gap-4 lg:grid-cols-3">
            <x-card :title="__('Dernières quittances')" :padding="false">
                <ul class="divide-y divide-line">
                    @forelse ($receipts as $r)
                        <li class="flex items-start gap-3 px-4 py-3 text-sm">
                            <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-green-50 text-online"><x-svg-icon name="file-check" class="h-4 w-4" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-ink">{{ Format::taxpayer($r) }}</p>
                                <p class="truncate text-xs text-muted">Quittance {{ $r->receipt_number ?? '—' }} · {{ $moduleLabels[$r->module] ?? $r->module }} ({{ Format::period($r) }})</p>
                            </div>
                            <p class="whitespace-nowrap font-bold tabular-nums text-ink">{{ Format::money($r->amount) }}</p>
                        </li>
                    @empty
                        <li class="p-4"><x-empty-state icon="receipt" :title="__('Aucune quittance')" /></li>
                    @endforelse
                </ul>
            </x-card>

            <x-card :padding="false">
                <x-slot:title>{{ __('Bulletins en attente') }} @if ($totals['pending_count'])<x-badge color="gold" class="ms-1">{{ $totals['pending_count'] }}</x-badge>@endif</x-slot:title>
                <ul class="divide-y divide-line">
                    @forelse ($pending as $b)
                        <li class="flex items-start gap-3 px-4 py-3 text-sm">
                            <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-gold/15 text-[#7a5d16]"><x-svg-icon name="clock" class="h-4 w-4" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-ink">{{ Format::taxpayer($b) }}</p>
                                <p class="truncate text-xs text-muted">BV {{ $b->slip_number ?? '—' }} · {{ $moduleLabels[$b->module] ?? $b->module }} ({{ Format::period($b) }})</p>
                            </div>
                            <p class="whitespace-nowrap font-bold tabular-nums text-ink">{{ Format::money($b->amount) }}</p>
                        </li>
                    @empty
                        <li class="p-4"><x-empty-state icon="clock" :title="__('Aucun bulletin en attente')" /></li>
                    @endforelse
                </ul>
            </x-card>

            <x-card :title="__('Dernières déclarations')" :padding="false">
                <ul class="divide-y divide-line">
                    @forelse ($declarations as $d)
                        <li class="flex items-start gap-3 px-4 py-3 text-sm">
                            <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-bg text-muted"><x-svg-icon name="file-text" class="h-4 w-4" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-ink">{{ Format::taxpayer($d) }}</p>
                                <p class="truncate text-xs text-muted">{{ $d->number }} · {{ $moduleLabels[$d->module] ?? $d->module }} ({{ Format::period($d) }})</p>
                            </div>
                            <div class="text-end">
                                <p class="whitespace-nowrap font-bold tabular-nums text-ink">{{ Format::money($d->total) }}</p>
                                @if ($d->status === 'paye')<x-badge color="green">{{ __('Payé') }}</x-badge>@elseif ($d->status === 'sous_seuil')<x-badge>{{ __('Sous seuil') }}</x-badge>@else<x-badge color="orange">{{ __('Émis') }}</x-badge>@endif
                            </div>
                        </li>
                    @empty
                        <li class="p-4"><x-empty-state icon="file-text" :title="__('Aucune déclaration')" /></li>
                    @endforelse
                </ul>
            </x-card>
        </section>
    </div>
</x-app-layout>
