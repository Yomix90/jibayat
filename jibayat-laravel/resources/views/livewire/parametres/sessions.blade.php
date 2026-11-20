<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />

    <x-card :title="__('Déconnexion automatique')">
        <form wire:submit="save" class="space-y-4">
            <p class="text-sm text-muted">Une session sans activité (clic, saisie, défilement) pendant ce délai est fermée.
                Le délai est rappelé dans l'en-tête : « fermeture après {{ config('jibayat.auto_logout_minutes') }} min sans activité ».</p>
            <div class="flex flex-wrap items-end gap-3">
                <x-field :label="__('Délai (minutes)')" for="minutes" error="minutes" class="w-40">
                    <x-input id="minutes" type="number" min="1" max="240" wire:model="minutes" :disabled="! $this->canWrite()" />
                </x-field>
                <div class="flex gap-1.5 pb-0.5">
                    @foreach ([5, 10, 15, 30] as $m)
                        <x-btn variant="secondary" size="sm" wire:click="$set('minutes', {{ $m }})" :disabled="! $this->canWrite()">{{ $m }} min</x-btn>
                    @endforeach
                </div>
            </div>
            @if ($this->canWrite())<x-btn type="submit" icon="check">{{ __('Enregistrer') }}</x-btn>@endif
        </form>
    </x-card>

    <x-card :title="__('Sessions ouvertes')">
        <p class="text-sm"><strong class="text-2xl tabular-nums">{{ $openSessions }}</strong> <span class="text-muted">{{ __('utilisateur(s) actif(s) dans le délai ci-dessus.') }}</span></p>
    </x-card>
</div>
