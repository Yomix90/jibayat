@php use App\Support\Format; use App\Domain\DomainePublic\Services\OccupationCalculator; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />
    <div class="flex flex-wrap items-center gap-3 text-sm">
        <label class="flex items-center gap-2">{{ __('Au moins') }}
            <x-input type="number" min="1" wire:model.live.debounce.400ms="minQuarters" class="!w-20 text-center" :aria-label="__('Nombre minimal de trimestres impayés')" />
            {{ __('trimestre(s) impayé(s)') }}</label>
        <span class="ms-auto font-semibold">{{ $rows->count() }} occupation(s) · <span class="text-red-600">{{ Format::money($total) }} DH</span></span>
    </div>
    <x-card :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($rows as $r)
                @php($o = $r['occupation'])
                <li class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm" wire:key="a-{{ $o->id }}">
                    <span class="font-mono text-xs font-bold">N° {{ $o->number }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $o->taxpayer?->displayName() }} <span class="text-xs font-normal text-muted">{{ $o->taxpayer?->phone }}</span></p>
                        <p class="truncate text-xs text-muted">{{ $o->type }} · {{ $o->location }} · {{ OccupationCalculator::periodLabel(array_values(array_filter($r['quarters'], fn ($q) => ! $q['est_emis']))) }}</p>
                    </div>
                    <p class="font-bold tabular-nums text-red-600">{{ Format::money($r['montant_non_declare']) }} DH</p>
                    <x-btn variant="secondary" size="sm" icon="printer" :href="route('domaine-public.avis-imprimer', $o)" target="_blank">{{ __('Avis') }}</x-btn>
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="check" :title="__('Aucune occupation en retard')" /></li>
            @endforelse
        </ul>
    </x-card>
</div>
