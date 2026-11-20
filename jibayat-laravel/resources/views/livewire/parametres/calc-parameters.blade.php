<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        @if ($this->canWrite())
            <x-slot:actions>
                <x-btn variant="secondary" icon="rotate-ccw" wire:click="resetLegal"
                       wire:confirm="Rétablir les valeurs légales (Loi 47-06) pour les paramètres existants ?">{{ __('Valeurs légales') }}</x-btn>
                <x-btn icon="check" wire:click="save">{{ __('Enregistrer') }}</x-btn>
            </x-slot:actions>
        @endif
    </x-page-header>

    <p class="text-sm text-muted">{{ __('Ces valeurs alimentent les calculs : pénalité de retard (%), majoration du 1er mois et des mois suivants (%), amende pour défaut de déclaration (%), dates limites (JJ/MM) et seuils d\'émission (DH).') }}</p>

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($groups as $module => $params)
            <x-card :title="$module" wire:key="m-{{ $module }}">
                <div class="space-y-3">
                    @foreach ($params as $p)
                        <div class="grid grid-cols-[1fr_8rem] items-center gap-3" wire:key="p-{{ $p->id }}">
                            <label for="p{{ $p->id }}" class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-ink">{{ $p->label ?: $p->code }}</span>
                                <span class="block truncate font-mono text-[11px] text-muted">{{ $p->code }}@if($p->description) · {{ $p->description }}@endif</span>
                            </label>
                            <div class="flex items-center gap-1.5">
                                <x-input id="p{{ $p->id }}" wire:model="values.{{ $p->id }}" class="text-end tabular-nums" :disabled="! $this->canWrite()" />
                                @if ($p->unit)<span class="w-10 text-xs text-muted">{{ $p->unit }}</span>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>
        @empty
            <x-empty-state icon="sliders-horizontal" :title="__('Aucun paramètre')" class="lg:col-span-2" />
        @endforelse
    </div>
</div>
