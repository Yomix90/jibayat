@php
    use App\Support\Format;
    $canEdit = $this->allowed('regie.modifier');
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>@if ($this->allowed('config.gerer'))<x-btn variant="secondary" icon="building-2" :href="route('parametres.regie')">{{ __('Valeurs (Intervenants)') }}</x-btn>@endif</x-slot:actions>
    </x-page-header>

    <form wire:submit="save">
        <x-card :padding="false" :title="__('Valeurs')">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted"><tr><th class="px-3 py-2 text-start">{{ __('Valeur') }}</th><th class="px-3 py-2 text-start">{{ __('Nature') }}</th>
                        <th class="px-3 py-2 text-start">{{ __('Rubrique de recette') }}</th><th class="px-3 py-2 text-end">{{ __('Seuil d’alerte (unités)') }}</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($values as $id => $v)
                            <tr wire:key="cv-{{ $id }}"><td class="px-3 py-2"><p class="font-semibold">{{ $stampValues[$id]->label }}</p><p class="text-xs text-muted">{{ Format::money($stampValues[$id]->unit_value) }} DH · {{ $stampValues[$id]->units_per_book }} {{ __('par carnet') }}</p></td>
                                <td class="px-3 py-2"><x-select wire:model="values.{{ $id }}.type" :disabled="! $canEdit" :aria-label="__('Nature')"><option value="timbre">{{ __('Timbre') }}</option><option value="vignette">{{ __('Vignette') }}</option><option value="ticket">{{ __('Ticket') }}</option></x-select></td>
                                <td class="px-3 py-2"><x-select wire:model="values.{{ $id }}.rubric_id" :disabled="! $canEdit" :aria-label="__('Rubrique')"><option value="">—</option>@foreach ($rubrics as $r)<option value="{{ $r->id }}">{{ $r->code }} · {{ $r->label }}</option>@endforeach</x-select></td>
                                <td class="px-3 py-2 text-end"><x-input type="number" min="0" wire:model="values.{{ $id }}.alert_threshold" :disabled="! $canEdit" class="!w-28 text-end" :aria-label="__('Seuil')" />
                                    @error("values.$id.alert_threshold")<p class="text-xs text-red-600">{{ $message }}</p>@enderror</td></tr>
                        @empty
                            <tr><td colspan="4" class="p-4"><x-empty-state icon="settings-2" :title="__('Aucune valeur')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($canEdit && $values)<div class="flex justify-end border-t border-line p-3"><x-btn type="submit" variant="gold" icon="check">{{ __('Enregistrer') }}</x-btn></div>@endif
        </x-card>
    </form>

    <x-card :title="__('Dotation de la réserve TGR')">
        <p class="mb-3 text-xs text-muted">{{ __('Quantité mise à la disposition de la régie par la TGR (réserve). Chaque paquet reçu ou retrait diminue la réserve.') }}</p>
        @if ($canEdit)
            <form wire:submit="addDotation" class="grid gap-2 sm:grid-cols-5">
                <x-select wire:model="dotation.stamp_value_id" :aria-label="__('Valeur')" class="sm:col-span-2">@foreach ($stampValues as $v)<option value="{{ $v->id }}">{{ $v->label }}</option>@endforeach</x-select>
                <x-input type="number" min="1" wire:model="dotation.quantity" :placeholder="__('Quantité (unités)')" :aria-label="__('Quantité')" />
                <x-input type="date" wire:model="dotation.moved_on" :aria-label="__('Date')" />
                <x-btn type="submit" icon="plus">{{ __('Ajouter') }}</x-btn>
                <x-input wire:model="dotation.reference" :placeholder="__('Référence (décision, bordereau TGR…)')" :aria-label="__('Référence')" class="sm:col-span-5" />
                @error('dotation.quantity')<p class="text-xs text-red-600 sm:col-span-5">{{ $message }}</p>@enderror
            </form>
        @endif
        <ul class="mt-4 divide-y divide-line text-sm">
            @foreach ($dotations as $d)<li class="flex justify-between gap-2 py-2 {{ $d->cancelled_at ? 'opacity-60 line-through' : '' }}"><span>{{ $d->moved_on->format('d/m/Y') }} · {{ $d->value?->label }}@if ($d->reference) · {{ $d->reference }}@endif</span><span class="font-semibold tabular-nums">{{ number_format($d->quantity, 0, ',', ' ') }}</span></li>@endforeach
        </ul>
    </x-card>
</div>
