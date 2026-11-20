@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            @include('livewire.regie.partials.period')
            <x-btn variant="secondary" icon="printer" :href="route('regie.imprimer.rapprochement', ['annee' => $year, 'mois' => $month])" target="_blank">{{ __('Imprimer') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    @unless ($r['statement'])
        <div class="surface border-gold/40 bg-gold/5 p-4 text-sm">{{ __('Aucun relevé TGR importé pour ce mois.') }} <a class="font-semibold text-accent underline" href="{{ route('documents.releves-gir') }}">{{ __('Importer le relevé GIR') }}</a></div>
    @endunless

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="{{ __('Indicateurs') }}">
        @foreach ([[__('Recettes de la régie'), $r['total_regie'], ''], [__('Relevé du comptable (TGR)'), $r['total_tgr'], ''], [__('Écart'), $r['total_gap'], abs($r['total_gap']) > 0.009 ? 'text-red-600' : 'text-online'], [__('Versé au comptable'), $r['remitted'], '']] as [$l, $v, $c])
            <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ $l }}</p><p class="mt-1 text-lg font-bold tabular-nums {{ $c }}">{{ Format::money($v) }} DH</p></div>
        @endforeach
    </section>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted"><tr><th class="px-3 py-2 text-start">{{ __('Code') }}</th><th class="px-3 py-2 text-start">{{ __('Rubrique') }}</th>
                    <th class="px-3 py-2 text-end">{{ __('Régie') }}</th><th class="px-3 py-2 text-end">{{ __('Comptable (TGR)') }}</th><th class="px-3 py-2 text-end">{{ __('Écart') }}</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($r['rows'] as $row)
                        <tr><td class="px-3 py-2 font-mono text-xs font-bold">{{ $row['code'] }}</td><td class="px-3 py-2">{{ $row['label'] }}</td>
                            <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($row['regie']) }}</td><td class="px-3 py-2 text-end tabular-nums">{{ Format::money($row['tgr']) }}</td>
                            <td class="px-3 py-2 text-end font-semibold tabular-nums {{ abs($row['gap']) > 0.009 ? 'text-red-600' : 'text-online' }}">{{ Format::money($row['gap']) }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="p-4"><x-empty-state icon="scale" :title="__('Rien à rapprocher ce mois')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
