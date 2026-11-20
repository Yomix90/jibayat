@php use App\Support\Format; use App\Domain\Boissons\Services\BeverageCalculator; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />
    <div class="flex flex-wrap items-center gap-3 text-sm">
        <label class="flex items-center gap-2">{{ __('Au moins') }}
            <x-input type="number" min="1" wire:model.live.debounce.400ms="minQuarters" class="!w-20 text-center" :aria-label="__('Nombre minimal de trimestres impayés')" />
            {{ __('trimestre(s) impayé(s)') }}</label>
        <span class="ms-auto font-semibold">{{ $rows->count() }} véhicule(s) · <span class="text-red-600">{{ Format::money($total) }} DH</span></span>
    </div>
    <x-card :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($rows as $r)
                @php($v = $r['vehicle'])
                <li class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm" wire:key="a-{{ $v->id }}">
                    <span class="font-mono text-xs font-bold" dir="ltr">{{ $v->registration }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $v->taxpayer?->displayName() }} <span class="text-xs font-normal text-muted">{{ $v->taxpayer?->phone }}</span></p>
                        <p class="truncate text-xs text-muted">{{ $v->type }} · {{ BeverageCalculator::periodLabel($r['quarters']) }}</p>
                    </div>
                    <p class="font-bold tabular-nums text-red-600">{{ Format::money($r['montant_non_declare']) }} DH</p>
                    <x-btn variant="secondary" size="sm" icon="printer" :href="route('transport.avis-imprimer', $v)" target="_blank">{{ __('Avis') }}</x-btn>
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="check" :title="__('Aucun véhicule en retard')" /></li>
            @endforelse
        </ul>
    </x-card>
</div>
