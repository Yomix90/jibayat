<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        @if ($this->canWrite())<x-slot:actions><x-btn variant="gold" icon="database-backup" wire:click="backupNow" wire:loading.attr="disabled">{{ __('Sauvegarder maintenant') }}</x-btn></x-slot:actions> @endif
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card :padding="false" class="lg:col-span-2">
            <x-slot:title>Sauvegardes disponibles ({{ count($backups) }})</x-slot:title>
            <ul class="divide-y divide-line text-sm">
                @forelse ($backups as $b)
                    <li class="flex flex-wrap items-center gap-3 px-4 py-2.5" wire:key="b-{{ $b['name'] }}">
                        <x-svg-icon name="database" class="h-4 w-4 text-muted" />
                        <span class="min-w-0 flex-1"><span class="block truncate font-mono text-xs font-semibold">{{ $b['name'] }}</span>
                            <span class="text-xs text-muted">{{ $b['date']->format('d/m/Y H:i') }} · {{ number_format($b['size'] / 1024, 0, ',', ' ') }} Ko</span></span>
                        @if (str_contains($b['name'], '_auto'))<x-badge color="blue">{{ __('Automatique') }}</x-badge>@elseif (str_contains($b['name'], '_precaution'))<x-badge color="gold">{{ __('Précaution') }}</x-badge>@else<x-badge>{{ __('Manuelle') }}</x-badge>@endif
                        @if ($this->canWrite())
                            <x-btn variant="secondary" size="sm" icon="download" :href="route('parametres.sauvegarde-telecharger', $b['name'])">{{ __('Télécharger') }}</x-btn>
                            <x-btn variant="ghost" size="sm" icon="trash-2" wire:click="delete('{{ $b['name'] }}')" wire:confirm="Supprimer définitivement la sauvegarde {{ $b['name'] }} ?" aria-label="Supprimer {{ $b['name'] }}" />
                        @endif
                    </li>
                @empty
                    <li class="p-4"><x-empty-state icon="database" :title="__('Aucune sauvegarde pour l’instant')" /></li>
                @endforelse
            </ul>
        </x-card>

        <x-card :title="__('Sauvegarde automatique')">
            <form wire:submit="saveSettings" class="space-y-3">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="settings.auto" @disabled(! $this->canWrite()) class="rounded border-line text-accent focus:ring-gold"> Chaque jour</label>
                <x-field :label="__('Heure')" for="s-time" error="settings.time"><x-input id="s-time" type="time" wire:model="settings.time" :disabled="! $this->canWrite()" /></x-field>
                <x-field :label="__('Sauvegardes conservées')" for="s-keep" error="settings.keep" :hint="__('Les plus anciennes sont supprimées au-delà.')"><x-input id="s-keep" type="number" min="1" max="365" wire:model="settings.keep" :disabled="! $this->canWrite()" /></x-field>
                <x-field :label="__('Copier aussi dans le dossier')" for="s-copy" error="settings.copy_dir" :hint="__('Ex. un disque externe ou un dossier synchronisé (Google Drive). Laisser vide pour ne pas copier.')">
                    <x-input id="s-copy" wire:model="settings.copy_dir" :placeholder="__('D:\\Sauvegardes\\Jibayat')" :disabled="! $this->canWrite()" /></x-field>
                @if ($this->canWrite())<x-btn type="submit" variant="secondary" icon="check" class="w-full">{{ __('Enregistrer') }}</x-btn>@endif
            </form>
        </x-card>
    </div>

    <x-card :title="__('Restaurer une sauvegarde')">
        <p class="text-sm text-muted">La restauration remplace toutes les données. Pour éviter une erreur de manipulation, elle se fait sur le serveur avec la commande ci-dessous ; une sauvegarde de précaution est faite automatiquement avant.</p>
        <pre class="mt-2 overflow-x-auto rounded-xl bg-navy p-3 text-xs text-white">{{ __('php artisan jibayat:restore jibayat_AAAA-MM-JJ_HHMMSS_manuel.sql.gz') }}</pre>
        <p class="mt-2 text-xs text-muted">{{ __('Les documents joints (scans, PV) se trouvent dans') }} <span class="font-mono">{{ __('storage/app/private') }}</span> {{ __(': copiez ce dossier avec les sauvegardes de la base.') }}</p>
    </x-card>
</div>
