@php use App\Support\Format; $labels = ['en_stock' => ['En stock', 'green'], 'affecte' => ['Affecté', 'gold'], 'consomme' => ['Consommé', 'gray']]; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />
    <div class="grid gap-2 sm:grid-cols-3">
        <x-select wire:model.live="status" :aria-label="__('Statut')"><option value="">{{ __('Tous les statuts') }}</option><option value="en_stock">{{ __('En stock') }}</option><option value="affecte">{{ __('Affectés') }}</option><option value="consomme">{{ __('Consommés') }}</option></x-select>
        <x-select wire:model.live="value" :aria-label="__('Valeur')"><option value="">{{ __('Toutes les valeurs') }}</option>@foreach ($values as $v)<option value="{{ $v->id }}">{{ $v->label }}</option>@endforeach</x-select>
        <x-select wire:model.live="service" :aria-label="__('Service')"><option value="">{{ __('Tous les services') }}</option>@foreach ($services as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</x-select>
    </div>
    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr><th class="px-4 py-2 text-start">{{ __('Carnet') }}</th><th class="px-4 py-2 text-start">{{ __('Valeur') }}</th><th class="px-4 py-2 text-start">{{ __('Affectation') }}</th><th class="px-4 py-2 text-end">{{ __('Versé · écart') }}</th><th class="px-4 py-2"></th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($books as $b)
                        <tr wire:key="b-{{ $b->id }}">
                            <td class="px-4 py-2.5"><p class="font-mono text-xs font-bold">{{ $b->number }}</p><p class="text-xs text-muted">n° {{ $b->first_number }} → {{ $b->last_number }}</p></td>
                            <td class="px-4 py-2.5 text-xs">{{ $b->stampValue?->label }}<br><span class="text-muted">{{ Format::money((float) $b->stampValue?->unit_value * (int) $b->stampValue?->units_per_book) }} DH le carnet</span></td>
                            <td class="px-4 py-2.5 text-xs"><x-badge :color="$labels[$b->status][1] ?? 'gray'">{{ $labels[$b->status][0] ?? $b->status }}</x-badge>
                                @if ($b->employee)<br>{{ $b->employee->last_name }} {{ $b->employee->first_name }} · {{ $b->service?->name }} · {{ $b->assigned_on?->format('d/m/Y') }}@endif</td>
                            <td class="px-4 py-2.5 text-end text-xs tabular-nums">@if ($b->status === 'consomme'){{ Format::money($b->amount_paid) }} DH<br><span @class(['text-red-600' => (float) $b->difference < 0, 'text-online' => (float) $b->difference > 0, 'text-muted' => (float) $b->difference == 0])>{{ (float) $b->difference > 0 ? '+' : '' }}{{ Format::money($b->difference) }}</span>@else — @endif</td>
                            <td class="px-4 py-2.5 text-end">@if ($b->status === 'en_stock' && $this->allowed('regie.modifier'))<x-btn variant="secondary" size="sm" icon="user-check" wire:click="openAssign({{ $b->id }})">{{ __('Affecter') }}</x-btn>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4"><x-empty-state icon="book-copy" :title="__('Aucun carnet')">{{ __('Ouvrez un paquet pour générer ses carnets.') }}</x-empty-state></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-line px-4 py-2">{{ $books->links() }}</div>
    </x-card>

    @if ($assignId)
        <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="{{ __('Affecter le carnet') }}">
            <div class="absolute inset-0 bg-navy/40 backdrop-blur-[2px]" wire:click="$set('assignId', null)"></div>
            <div class="relative w-full max-w-md rounded-t-[24px] bg-white p-5 sm:rounded-[24px]" style="box-shadow: var(--shadow-lg)">
                <h2 class="mb-3 text-[17px] font-semibold">{{ __('Affecter le carnet') }}</h2>
                <form wire:submit="assign" class="space-y-3">
                    <x-field :label="__('Service')" for="a-service" error="assignService">
                        <x-select id="a-service" wire:model.live="assignService"><option value="">{{ __('— Choisir —') }}</option>@foreach ($services as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</x-select>
                    </x-field>
                    <x-field :label="__('Employé')" for="a-employee" error="assignEmployee">
                        <x-select id="a-employee" wire:model="assignEmployee"><option value="">{{ __('— Choisir —') }}</option>@foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->last_name }} {{ $e->first_name }}</option>@endforeach</x-select>
                    </x-field>
                    <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('assignId', null)">{{ __('Fermer') }}</x-btn><x-btn type="submit" icon="check">{{ __('Affecter') }}</x-btn></div>
                </form>
            </div>
        </div>
    @endif
</div>
