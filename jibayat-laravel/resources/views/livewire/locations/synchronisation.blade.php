@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        @if ($this->allowed('config.gerer'))
            <x-slot:actions><x-btn icon="refresh-cw" wire:click="syncAll">{{ __('Synchroniser les tarifs non liés') }}</x-btn></x-slot:actions>
        @endif
    </x-page-header>

    <p class="text-sm text-muted">{{ __('Arrêté fiscal actif :') }} <strong>{{ $activeOrder?->number ?? 'aucun' }}</strong>.
        Le loyer d'un bail utilise la valeur de l'arrêté (colonne « Arrêté ») ; un écart signifie que la valeur du groupe a changé sans être recopiée.</p>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr><th class="px-4 py-2 text-start">{{ __('Secteur') }}</th><th class="px-4 py-2 text-start">{{ __('Groupe de tarif') }}</th><th class="px-4 py-2 text-end">{{ __('Valeur') }}</th><th class="px-4 py-2 text-end">{{ __('Arrêté') }}</th><th class="px-4 py-2 text-start">{{ __('État') }}</th><th class="px-4 py-2"></th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($tariffs as $t)
                        @php($st = $status[$t->id])
                        <tr wire:key="sy-{{ $t->id }}">
                            <td class="px-4 py-2.5 font-mono text-xs">{{ $t->leaseSector?->code }}</td>
                            <td class="px-4 py-2.5">{{ $t->label }} <span class="text-xs text-muted">({{ $t->tariff_type === 'm2' ? 'au m²' : 'fixe' }})</span></td>
                            <td class="px-4 py-2.5 text-end tabular-nums">{{ Format::money($t->value) }}</td>
                            <td class="px-4 py-2.5 text-end tabular-nums">{{ $st['fiscal_value'] !== null ? Format::money($st['fiscal_value']) : '—' }}<span class="block text-[11px] text-muted">{{ $t->tariff?->taxOrder?->number }}</span></td>
                            <td class="px-4 py-2.5">
                                @if ($st['in_sync'])<x-badge color="green">{{ __('Synchronisé') }}</x-badge>
                                @elseif ($st['fiscal_value'] !== null)<x-badge color="orange">{{ __('Écart') }}</x-badge>
                                @else<x-badge color="red">{{ __('Non lié') }}</x-badge>@endif
                            </td>
                            <td class="px-4 py-2.5 text-end">
                                @if ($this->allowed('config.gerer') && ! $st['in_sync'])
                                    <x-btn variant="secondary" size="sm" icon="refresh-cw" wire:click="syncOne({{ $t->id }})">{{ __('Synchroniser') }}</x-btn>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-4"><x-empty-state icon="refresh-cw" :title="__('Aucun groupe de tarif')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
