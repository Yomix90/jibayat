@php
    use App\Support\Format;
    use App\Domain\Regie\Services\Ledger;
    $ar = app()->isLocale('ar');
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            @include('livewire.regie.partials.period')
            <x-btn variant="secondary" icon="printer" :href="route('regie.imprimer.etat-mensuel', ['annee' => $year, 'mois' => $month])" target="_blank">{{ __('Imprimer') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap items-center gap-2 text-sm">
        @if ($state['closed'])
            <x-badge color="green"><x-svg-icon name="lock" class="h-3 w-3" /> {{ __('Clôturé') }}</x-badge><span class="text-muted">{{ __('État figé à la clôture du mois.') }}</span>
        @else
            <x-badge color="gold">{{ __('Provisoire') }}</x-badge><span class="text-muted">{{ __('Le mois n’est pas clôturé : l’état peut encore évoluer.') }}</span>
            @if ($this->allowed('paiements.valider'))<a href="{{ route('regie.cloture', ['annee' => $year]) }}" class="font-semibold text-accent underline">{{ __('Clôturer') }}</a>@endif
        @endif
    </div>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="{{ __('Indicateurs') }}">
        @foreach ([[__('Report (mois antérieurs)'), $state['total_previous']], [__('Recettes du mois'), $state['total_month']], [__('Cumul de l’année'), $state['total_cumulative']], [__('Virements du Ministère (exclus)'), $state['ministry_excluded']]] as [$l, $v])
            <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ $l }}</p><p class="mt-1 text-lg font-bold tabular-nums">{{ Format::money($v) }} DH</p></div>
        @endforeach
    </section>

    <x-card :padding="false">
        <x-slot:title>{{ __('Modèle 10') }} — {{ tr(Ledger::MONTHS[$month]) }} {{ $year }}</x-slot:title>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr><th class="px-3 py-2 text-start">{{ __('Code') }}</th><th class="px-3 py-2 text-start">{{ __('Rubrique') }}</th><th class="px-3 py-2 text-end">{{ __('Pièces') }}</th>
                        <th class="px-3 py-2 text-end">{{ __('Report') }}</th><th class="px-3 py-2 text-end">{{ __('Mois') }}</th><th class="px-3 py-2 text-end">{{ __('Cumul') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($state['rows'] as $r)
                        <tr><td class="px-3 py-2 font-mono text-xs font-bold">{{ $r['code'] }}</td><td class="px-3 py-2">{{ $ar && ($r['label_ar'] ?? null) ? $r['label_ar'] : $r['label'] }}</td>
                            <td class="px-3 py-2 text-end tabular-nums">{{ $r['pieces'] }}</td><td class="px-3 py-2 text-end tabular-nums">{{ Format::money($r['previous']) }}</td>
                            <td class="px-3 py-2 text-end font-semibold tabular-nums">{{ Format::money($r['month']) }}</td><td class="px-3 py-2 text-end tabular-nums">{{ Format::money($r['cumulative']) }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="p-4"><x-empty-state icon="calendar-range" :title="__('Aucune recette sur l’année jusqu’à ce mois')" /></td></tr>
                    @endforelse
                </tbody>
                <tfoot class="border-t-2 border-gold bg-bg font-bold"><tr><td class="px-3 py-2" colspan="3">{{ __('Total') }}</td>
                    <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($state['total_previous']) }}</td><td class="px-3 py-2 text-end tabular-nums">{{ Format::money($state['total_month']) }}</td>
                    <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($state['total_cumulative']) }}</td></tr></tfoot>
            </table>
        </div>
    </x-card>
</div>
