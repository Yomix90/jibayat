@php
    use Illuminate\Support\Carbon;
    $blocks = [
        ['TNB', 'annuelle_terrains', 'map-pin', __('Déclaration annuelle des terrains (art. 47)'), $tnb, $tnb_deadline, $year, fn ($o) => __('Terrain n°').' '.$o->number.($o->land_title ? ' · TF '.$o->land_title : '').' · '.$o->area.' m²'],
        ['DEBITS_BOISSONS', 'recettes_annuelles', 'coffee', __('Déclaration des recettes de l’année :y (art. 67-II)', ['y' => $year - 1]), $boissons, $boissons_deadline, $year - 1, fn ($o) => $o->name.' (n° '.$o->number.')'],
    ];
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions><x-input type="number" wire:model.live.debounce.500ms="year" class="!w-28" :aria-label="__('Année')" /></x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($blocks as [$module, $type, $icon, $title, $rows, $deadline, $fy, $name])
            @php($days = $today->diffInDays(Carbon::parse($deadline), false))
            <x-card :padding="false" wire:key="b-{{ $module }}">
                <x-slot:title>{{ $title }}</x-slot:title>
                <x-slot:actions><x-badge :color="$days < 0 ? 'red' : ($days <= 30 ? 'gold' : null)">{{ __('Délai') }} {{ Carbon::parse($deadline)->format('d/m/Y') }}{{ $days >= 0 ? ' · J-'.(int) $days : ' · '.__('dépassé') }}</x-badge></x-slot:actions>
                <p class="border-b border-line px-4 py-2 text-xs text-muted">{{ $rows->count() }} {{ __('déclaration(s) manquante(s)') }}</p>
                <ul class="max-h-[32rem] divide-y divide-line overflow-y-auto text-sm">
                    @forelse ($rows as $o)
                        <li class="flex flex-wrap items-center gap-3 px-4 py-2.5" wire:key="{{ $module }}-{{ $o->id }}">
                            <x-svg-icon :name="$icon" class="h-4 w-4 text-muted" />
                            <span class="min-w-0 flex-1"><span class="block truncate font-semibold">{{ $name($o) }}</span>
                                <span class="text-xs text-muted">{{ $o->taxpayer?->displayName() }} @if ($o->taxpayer?->phone)· <a href="tel:{{ $o->taxpayer->phone }}" class="text-accent">{{ $o->taxpayer->phone }}</a>@endif</span></span>
                            <x-btn variant="secondary" size="sm" icon="file-plus" :href="route('declarations.nouvelle', ['taxe' => $module, 'type' => $type, 'objet' => $o->id])">{{ __('Déclarer') }}</x-btn>
                        </li>
                    @empty
                        <li class="p-4"><x-empty-state icon="check" :title="__('Toutes les déclarations sont déposées')" /></li>
                    @endforelse
                </ul>
            </x-card>
        @endforeach
    </div>
    <p class="text-xs text-muted">{{ __('Défaut ou retard de déclaration : majoration de 15 % du droit dû, minimum 500 DH (art. 134 de la loi 47-06).') }}</p>
</div>
