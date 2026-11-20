@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />
    <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Choisir un véhicule : immatriculation, n° de dossier ou de dépôt…')" :aria-label="__('Chercher un véhicule')" />
    <x-card :title="__('Véhicules en fourrière à encaisser')" :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($rows as $r)
                @php($c = $r['case'])
                <li wire:key="c-{{ $c->id }}">
                    <button type="button" wire:click="choose({{ $c->id }})" class="flex w-full items-center gap-3 px-4 py-3 text-start hover:bg-bg">
                        <span class="font-mono text-xs font-bold text-ink" dir="ltr">{{ $c->registration }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-ink">Dossier {{ $c->number }} · {{ $c->vehicle_type }}</span>
                            <span class="block truncate text-xs text-muted">Entré le {{ $c->impounded_on?->format('d/m/Y') }} · {{ $r['days'] }} jour(s)</span>
                        </span>
                        <x-badge color="red">{{ Format::money($r['amount']) }} DH</x-badge>
                        <x-svg-icon name="chevron-right" class="h-4 w-4 text-muted rtl:rotate-180" />
                    </button>
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="check" :title="__('Aucun véhicule à encaisser')" /></li>
            @endforelse
        </ul>
    </x-card>
</div>
