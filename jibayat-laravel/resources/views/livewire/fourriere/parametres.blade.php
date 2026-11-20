@php use App\Support\Format; use App\Livewire\Fourriere\Parametres; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />

    <x-card :title="__('Tarifs (arrêté fiscal)')">
        <p class="mb-2 text-xs text-muted">{{ __('Les tarifs journaliers et les frais de remorquage se modifient dans Paramètres › Arrêtés fiscaux (rubrique Fourrière).') }}</p>
        <ul class="flex flex-wrap gap-2 text-sm">
            @foreach ($types as $t)<li><x-badge>{{ $t->label }} · {{ Format::money($t->daily_rate) }} DH/jour</x-badge></li>@endforeach
            <li><x-badge color="orange">Remorquage · {{ Format::money($towing) }} DH</x-badge></li>
        </ul>
    </x-card>

    <div class="grid gap-4 lg:grid-cols-3">
        @foreach (Parametres::CATEGORIES as $cat => $title)
            <x-card :title="$title" :padding="false">
                <ul class="divide-y divide-line text-sm">
                    @forelse ($settings[$cat] ?? [] as $s)
                        <li class="flex items-center gap-2 px-4 py-2" wire:key="s-{{ $s->id }}">
                            <span @class(['flex-1', 'text-muted line-through' => ! $s->is_active])>{{ $s->label }}</span>
                            @if ($this->allowed('config.gerer'))
                                <x-btn variant="ghost" size="sm" wire:click="toggle({{ $s->id }})">{{ $s->is_active ? 'Désactiver' : 'Activer' }}</x-btn>
                            @endif
                        </li>
                    @empty
                        <li class="px-4 py-3 text-xs text-muted">{{ __('Aucun élément.') }}</li>
                    @endforelse
                </ul>
                @if ($this->allowed('config.gerer'))
                    <form wire:submit="add('{{ $cat }}')" class="flex gap-2 border-t border-line p-3">
                        <x-input wire:model="newLabel.{{ $cat }}" :placeholder="__('Ajouter…')" aria-label="Nouveau : {{ tr($title) }}" />
                        <x-btn type="submit" size="sm" icon="plus" :aria-label="__('Ajouter')" />
                    </form>
                    @error('newLabel.'.$cat)<p class="px-3 pb-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                @endif
            </x-card>
        @endforeach
    </div>
</div>
