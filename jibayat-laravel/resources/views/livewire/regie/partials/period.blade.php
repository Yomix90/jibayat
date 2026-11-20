{{-- Sélecteur de période (mois / année) des états de la régie. --}}
<div class="inline-flex items-center gap-1">
    @if (empty($yearOnly))
        <x-btn variant="ghost" size="sm" icon="chevron-left" wire:click="previousMonth" :aria-label="__('Mois précédent')" />
        <x-select wire:model.live="month" :aria-label="__('Mois')" class="!w-36">@foreach (\App\Domain\Regie\Services\Ledger::MONTHS as $k => $m)<option value="{{ $k }}">{{ tr($m) }}</option>@endforeach</x-select>
    @endif
    <x-select wire:model.live="year" :aria-label="__('Année')" class="!w-28">@foreach ($this->years() as $y)<option value="{{ $y }}">{{ $y }}</option>@endforeach</x-select>
    @if (empty($yearOnly))
        <x-btn variant="ghost" size="sm" icon="chevron-right" wire:click="nextMonth" :aria-label="__('Mois suivant')" />
    @endif
</div>
