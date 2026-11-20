<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-btn variant="secondary" icon="printer" :href="route('declarations.formulaire', ['DEBITS_BOISSONS', 'cessation'])" target="_blank">{{ __('Formulaire vierge') }}</x-btn>
            <x-btn variant="secondary" icon="files" :href="route('declarations.registre', ['taxe' => 'DEBITS_BOISSONS'])">{{ __('Registre des déclarations d’assiette') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-2 sm:grid-cols-[1fr_14rem]">
        <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Établissement, exploitant, CIN…')" :aria-label="__('Rechercher')" />
        <x-select wire:model.live="status" :aria-label="__('Statut')">
            <option value="actif">{{ __('En activité') }}</option><option value="cesse">{{ __('En cessation') }}</option><option value="">{{ __('Tous') }}</option>
        </x-select>
    </div>

    <x-card :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($outlets as $o)
                <li class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm" wire:key="c-{{ $o->id }}">
                    <span class="font-mono text-xs font-bold">N° {{ $o->number }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $o->name }} @if ($o->status === 'cesse')<x-badge>{{ __('Cessation') }}</x-badge>@endif</p>
                        <p class="truncate text-xs text-muted">{{ $o->taxpayer?->displayName() }}{{ $o->address ? ' · '.$o->address : '' }}</p>
                        @if ($stop = $stops[$o->id] ?? null)
                            <p class="text-xs"><span class="font-semibold">{{ __('Arrêt depuis le') }} {{ $stop->event_on?->format('d/m/Y') }}</span> · {{ $stop->reason }}
                                @if ($stop->resume_on) · {{ __('reprise prévue le') }} <span @class(['font-semibold', 'text-red-600' => $stop->resume_on->isPast()])>{{ $stop->resume_on->format('d/m/Y') }}</span>@endif
                                · {{ __('période exonérée') }} @unless ($stop->signed)<x-badge color="gold">{{ __('À faire signer') }}</x-badge>@endunless</p>
                        @endif
                    </div>
                    @if ($stop = $stops[$o->id] ?? null)
                        <x-btn variant="ghost" size="sm" icon="printer" target="_blank" :href="route('declarations.imprimer', $stop)">{{ __('Déclaration d’arrêt') }}</x-btn>
                    @endif
                    @if ($this->allowed('boissons.modifier'))
                        @if ($o->status === 'cesse')
                            <x-btn variant="secondary" size="sm" icon="rotate-ccw" :href="route('declarations.nouvelle', ['taxe' => 'DEBITS_BOISSONS', 'type' => 'reprise', 'objet' => $o->id])">{{ __('Déclarer la reprise') }}</x-btn>
                        @else
                            <x-btn variant="danger" size="sm" icon="circle-x" :href="route('declarations.nouvelle', ['taxe' => 'DEBITS_BOISSONS', 'type' => 'cessation', 'objet' => $o->id])">{{ __('Déclarer l’arrêt') }}</x-btn>
                        @endif
                    @endif
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="coffee" :title="__('Aucun établissement')" /></li>
            @endforelse
        </ul>
    </x-card>
</div>
