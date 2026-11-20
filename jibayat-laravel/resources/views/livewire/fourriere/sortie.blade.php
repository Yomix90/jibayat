<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />
    <p class="text-sm text-muted">{{ __('Véhicules dont les frais ont été payés et quittancés par le régisseur : la sortie restitue le véhicule à son propriétaire.') }}</p>
    <x-card :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($rows as $r)
                @php($c = $r['case'])
                <li class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm" wire:key="s-{{ $c->id }}">
                    <span class="font-mono text-xs font-bold" dir="ltr">{{ $c->registration }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $c->owner_name ?: 'Propriétaire non renseigné' }} <span class="text-xs font-normal text-muted">{{ $c->owner_cin }}</span></p>
                        <p class="truncate text-xs text-muted">Dossier {{ $c->number }} · {{ $c->vehicle_type }} · entré le {{ $c->impounded_on?->format('d/m/Y') }} · BV {{ $c->slip_number }}</p>
                    </div>
                    <x-btn variant="ghost" size="sm" icon="file-text" :href="route('fourriere.fiche', ['dossier' => $c->id])">{{ __('Fiche') }}</x-btn>
                    @if ($this->allowed('fourriere.modifier'))
                        <x-btn size="sm" icon="key-round" wire:click="release({{ $c->id }})" wire:confirm="Valider la sortie de {{ $c->registration }} ?">{{ __('Valider la sortie') }}</x-btn>
                    @endif
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="key-round" :title="__('Aucun véhicule à sortir')">{{ __('Un véhicule apparaît ici quand son paiement est validé par le régisseur.') }}</x-empty-state></li>
            @endforelse
        </ul>
    </x-card>
</div>
