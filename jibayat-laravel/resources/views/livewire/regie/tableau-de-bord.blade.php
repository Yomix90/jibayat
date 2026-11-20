@php
    use App\Support\Format;
    use App\Domain\Regie\Services\Ledger;
    $max = max(1, collect($days)->max('amount'));
    $levels = ['danger' => ['red', 'circle-alert'], 'warning' => ['gold', 'triangle-alert'], 'info' => ['blue', 'info']];
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            @if ($this->allowed('recettes.ajouter'))<x-btn icon="pen-line" :href="route('regie.saisie')">{{ __('Saisir des recettes') }}</x-btn>@endif
            <x-btn variant="secondary" icon="book-open" :href="route('regie.livre-caisse')">{{ __('Livre de caisse') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="{{ __('Indicateurs') }}">
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Encaisse (solde de caisse)') }}</p>
            <p class="mt-1 text-lg font-bold tabular-nums {{ $ceiling > 0 && $balance > $ceiling ? 'text-red-600' : '' }}">{{ Format::money($balance) }} DH</p>
            @if ($ceiling > 0)<p class="text-[11px] text-muted">{{ __('plafond') }} {{ Format::money($ceiling) }}</p>@endif</div>
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Recettes du jour') }}</p><p class="mt-1 text-lg font-bold tabular-nums text-online">{{ Format::money($todayTotal) }} DH</p></div>
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Recettes du mois') }}</p><p class="mt-1 text-lg font-bold tabular-nums">{{ Format::money($monthTotal) }} DH</p></div>
        <a href="{{ route('regie.brouillons') }}" class="surface p-4 hover:border-gold"><p class="text-xs font-semibold text-muted">{{ __('Brouillons à valider') }}</p><p class="mt-1 text-lg font-bold tabular-nums {{ $drafts ? 'text-[#8a5a12]' : '' }}">{{ $drafts }}</p></a>
    </section>

    <div class="grid gap-6 lg:grid-cols-5">
        <x-card class="lg:col-span-3" :title="__('Recettes des 14 derniers jours')">
            <div class="flex h-40 items-end gap-1.5" role="img" aria-label="{{ __('Recettes des 14 derniers jours') }}">
                @foreach ($days as $d)
                    <a href="{{ route('regie.situation', ['date' => $d['date']->toDateString()]) }}" class="group flex h-full flex-1 flex-col justify-end" title="{{ $d['date']->format('d/m') }} : {{ Format::money($d['amount']) }} DH">
                        <div class="rounded-t-md bg-gold/70 transition group-hover:bg-gold" style="height: {{ max(2, round($d['amount'] / $max * 100)) }}%"></div>
                        <span class="mt-1 text-center text-[10px] text-muted">{{ $d['date']->format('d') }}</span>
                    </a>
                @endforeach
            </div>
            @if ($byFamily)
                <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                    @foreach ($byFamily as $f => $v)<div class="flex justify-between rounded-lg bg-bg px-3 py-2"><span>{{ tr(Ledger::familyLabel($f)) }}</span><strong class="tabular-nums">{{ Format::money($v) }}</strong></div>@endforeach
                </div>
            @endif
        </x-card>

        <x-card class="lg:col-span-2" :padding="false">
            <x-slot:title>{{ __('À corriger') }}</x-slot:title>
            <x-slot:actions><a href="{{ route('regie.centre-controle') }}" class="text-xs font-semibold text-accent">{{ __('Centre de contrôle') }}</a></x-slot:actions>
            <ul class="divide-y divide-line text-sm">
                @forelse ($issues as $i)
                    <li class="flex items-start gap-3 px-4 py-3">
                        <x-svg-icon :name="$levels[$i['level']][1]" class="mt-0.5 h-4 w-4 shrink-0 {{ ['danger' => 'text-red-600', 'warning' => 'text-[#8a5a12]', 'info' => 'text-sky-700'][$i['level']] }}" />
                        <div class="min-w-0 flex-1"><p class="font-semibold">{{ $i['title'] }}</p><p class="text-xs text-muted">{{ $i['detail'] }}</p></div>
                        @if ($i['route'] && Route::has($i['route']))<a href="{{ route($i['route'], $i['params']) }}" class="text-xs font-semibold text-accent">{{ __('Voir') }}</a>@endif
                    </li>
                @empty
                    <li class="p-4"><x-empty-state icon="circle-check" :title="__('Rien à signaler')" /></li>
                @endforelse
            </ul>
        </x-card>
    </div>
</div>
