@php use App\Support\Format; use App\Domain\Tnb\Services\TnbCalculator; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            @if (count($selected))
                <x-btn variant="gold" icon="printer" :href="route('tnb.avis-lot-imprimer', ['ids' => implode(',', $selected)])" target="_blank">Imprimer la sélection ({{ count($selected) }})</x-btn>
            @else
                <x-btn variant="gold" icon="printer" disabled>{{ __('Imprimer la sélection (0)') }}</x-btn>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap items-center gap-3 text-sm">
        <label class="flex items-center gap-2">{{ __('Au moins') }}
            <x-input type="number" min="1" wire:model.live.debounce.400ms="minYears" class="!w-20 text-center" :aria-label="__('Nombre minimal d\'années dues')" />
            {{ __('année(s) due(s)') }}</label>
        <x-select wire:model.live="zone" :aria-label="__('Zone')" class="!w-auto">
            <option value="">{{ __('Toutes les zones') }}</option>
            @foreach ($zones as $z)<option value="{{ $z }}">Zone {{ $z }}</option>@endforeach
        </x-select>
        <span class="ms-auto font-semibold">{{ $rows->count() }} terrain(s) · <span class="text-red-600">{{ Format::money($total) }} DH</span></span>
    </div>

    <x-card :padding="false">
        @if ($rows->count())
            <div class="border-b border-line px-4 py-2">
                <button type="button" wire:click="selectAll(@js($rows->pluck('parcel.id')->all()))" class="text-xs font-semibold text-accent hover:underline">
                    {{ count($selected) === $rows->count() ? 'Tout désélectionner' : 'Tout sélectionner' }}</button>
            </div>
        @endif
        <ul class="divide-y divide-line">
            @forelse ($rows as $r)
                @php($p = $r['parcel'])
                <li class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm" wire:key="a-{{ $p->id }}">
                    <input type="checkbox" value="{{ $p->id }}" wire:model.live="selected" class="rounded border-line text-accent focus:ring-gold" aria-label="Sélectionner le terrain {{ $p->number }}">
                    <span class="font-mono text-xs font-bold">T {{ $p->number }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $p->taxpayer?->displayName() }} <span class="text-xs font-normal text-muted">{{ $p->taxpayer?->phone }}</span></p>
                        <p class="truncate text-xs text-muted">{{ $p->locationLabel() }} · {{ TnbCalculator::periodLabel($r['years']) }}</p>
                    </div>
                    <p class="font-bold tabular-nums text-red-600">{{ Format::money($r['total_due']) }} DH</p>
                    <x-btn variant="secondary" size="sm" icon="printer" :href="route('tnb.avis-imprimer', $p)" target="_blank">{{ __('Avis') }}</x-btn>
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="check" :title="__('Aucun terrain en retard')" /></li>
            @endforelse
        </ul>
    </x-card>
</div>
