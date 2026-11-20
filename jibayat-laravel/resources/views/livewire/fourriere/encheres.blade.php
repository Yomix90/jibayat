@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions><x-btn variant="secondary" icon="gavel" :href="route('fourriere.groupes')">{{ __('Groupes d\'enchères') }}</x-btn></x-slot:actions>
    </x-page-header>
    <p class="text-sm text-muted">{{ __('Véhicules restés plus de 365 jours en fourrière : ils peuvent être inscrits dans un groupe d\'enchères puis vendus.') }}</p>

    <x-card :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($rows as $r)
                @php($c = $r['case'])
                <li class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm" wire:key="e-{{ $c->id }}">
                    <span class="font-mono text-xs font-bold" dir="ltr">{{ $c->registration }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $c->vehicle_type }} · dossier {{ $c->number }}{{ $c->deposit_number ? ' · dépôt '.$c->deposit_number : '' }}</p>
                        <p class="text-xs text-muted">Entré le {{ $c->impounded_on?->format('d/m/Y') }} · {{ $r['days'] }} jours · {{ Format::money($r['amount']) }} DH de frais</p>
                    </div>
                    @if (isset($grouped[$c->id]))
                        <x-badge color="gold">{{ __('Dans un groupe') }}</x-badge>
                    @elseif ($this->allowed('fourriere.modifier') && $groups->count())
                        <div class="flex flex-wrap items-center gap-1">
                            <x-select wire:model="groupFor.{{ $c->id }}" class="!w-40" :aria-label="__('Groupe')">
                                <option value="">{{ __('Groupe…') }}</option>
                                @foreach ($groups as $g)<option value="{{ $g->id }}">{{ $g->number }} — {{ $g->label }}</option>@endforeach
                            </x-select>
                            <x-select wire:model="conditionFor.{{ $c->id }}" class="!w-32" :aria-label="__('État du véhicule')">
                                <option value="moyen">{{ __('État…') }}</option>
                                @foreach ($conditions as $e)<option value="{{ $e->code }}">{{ $e->label }}</option>@endforeach
                            </x-select>
                            <x-btn size="sm" icon="plus" wire:click="addToGroup({{ $c->id }})">{{ __('Ajouter') }}</x-btn>
                        </div>
                        @error('groupFor.'.$c->id)<p class="w-full text-end text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                    @elseif (! $groups->count())
                        <span class="text-xs text-muted">{{ __('Créez d\'abord un groupe d\'enchères.') }}</span>
                    @endif
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="clock" :title="__('Aucun véhicule de plus de 365 jours')" /></li>
            @endforelse
        </ul>
    </x-card>
</div>
