@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            @if ($rubrics->count() > 1)<x-select wire:model.live="rubric" :aria-label="__('Rubrique')" class="!w-56">@foreach ($rubrics as $r)<option value="{{ $r->id }}">{{ $r->code }} · {{ $r->label }}</option>@endforeach</x-select>@endif
            @include('livewire.regie.partials.period')
            @if ($current)<x-btn variant="secondary" icon="printer" :href="route('regie.imprimer.taxe-speciale', ['rubrique' => $current->id, 'annee' => $year, 'mois' => $month])" target="_blank">{{ __('Imprimer le bordereau') }}</x-btn>@endif
        </x-slot:actions>
    </x-page-header>

    @if (! $current)
        <x-empty-state icon="beef" :title="__('Aucune rubrique de taxe spéciale')">{{ __('Dans Paramètres ➜ Rubriques, créez la rubrique de la taxe d’abattage et cochez « Taxe spéciale ».') }}</x-empty-state>
    @else
        <section class="grid grid-cols-2 gap-3 md:grid-cols-3" aria-label="{{ __('Indicateurs') }}">
            <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Recettes') }}</p><p class="mt-1 text-lg font-bold tabular-nums">{{ $entries->count() }}</p></div>
            <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Quantité') }}</p><p class="mt-1 text-lg font-bold tabular-nums">{{ rtrim(rtrim(number_format((float) $entries->sum('quantity'), 2, ',', ' '), '0'), ',') }}</p></div>
            <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Montant') }}</p><p class="mt-1 text-lg font-bold tabular-nums">{{ Format::money($entries->sum('amount')) }} DH</p></div>
        </section>
        <x-card :padding="false">
            <x-slot:title>{{ $current->code }} · {{ $current->label }}</x-slot:title>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted"><tr><th class="px-3 py-2 text-start">{{ __('Date') }}</th><th class="px-3 py-2 text-start">{{ __('N°') }}</th><th class="px-3 py-2 text-start">{{ __('Partie versante') }}</th><th class="px-3 py-2 text-end">{{ __('Quantité') }}</th><th class="px-3 py-2 text-end">{{ __('P.U.') }}</th><th class="px-3 py-2 text-end">{{ __('Montant') }}</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($entries as $e)
                            <tr><td class="px-3 py-2">{{ $e->entry_date->format('d/m/Y') }}</td><td class="px-3 py-2 font-mono text-xs">{{ $e->reference ?: $e->number }}</td><td class="px-3 py-2">{{ $e->payer }}</td>
                                <td class="px-3 py-2 text-end tabular-nums">{{ $e->quantity !== null ? rtrim(rtrim(number_format((float) $e->quantity, 2, ',', ' '), '0'), ',') : '—' }}</td>
                                <td class="px-3 py-2 text-end tabular-nums">{{ $e->unit_price !== null ? Format::money($e->unit_price) : '—' }}</td><td class="px-3 py-2 text-end font-semibold tabular-nums">{{ Format::money($e->amount) }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="p-4"><x-empty-state icon="beef" :title="__('Aucune recette ce mois')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
</div>
