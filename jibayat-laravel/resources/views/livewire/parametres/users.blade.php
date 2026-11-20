<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        @if ($this->canWrite())
            <x-slot:actions><x-btn icon="user-plus" wire:click="create">{{ __('Nouveau compte') }}</x-btn></x-slot:actions>
        @endif
    </x-page-header>

    <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Rechercher un nom, un identifiant, un e-mail…')" />

    <x-card :padding="false">
        <ul class="divide-y divide-line">
            @forelse ($users as $u)
                <li class="flex flex-wrap items-center gap-3 px-4 py-3" wire:key="u-{{ $u->id }}">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-navy text-sm font-bold text-gold">{{ $u->initials() }}</span>
                    <div @class(['min-w-0 flex-1', 'opacity-60' => ! $u->is_active])>
                        <p class="truncate font-semibold text-ink">{{ $u->name }}
                            @if ($u->name_ar)<span class="font-ar ms-1 text-xs font-normal text-muted" lang="ar">{{ $u->name_ar }}</span>@endif
                        </p>
                        <p class="truncate text-xs text-muted">
                            {{ $u->username }}{{ $u->email ? ' · '.$u->email : '' }}
                            · {{ $u->last_login_at ? 'dernière connexion '.$u->last_login_at->format('d/m/Y H:i') : 'jamais connecté' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <x-badge color="gold">{{ $u->roleLabel() }}</x-badge>
                        @if ($u->must_change_password)<x-badge color="orange">{{ __('Mot de passe à changer') }}</x-badge>@endif
                        @if (! $u->is_active)<x-badge color="red">{{ __('Désactivé') }}</x-badge>@endif
                        @if ($u->legacy_id)<x-badge>{{ __('Importé') }}</x-badge>@endif
                    </div>
                    @if ($this->canWrite())
                        <div class="flex gap-1">
                            <x-btn variant="ghost" size="sm" icon="pencil" wire:click="edit({{ $u->id }})">{{ __('Modifier') }}</x-btn>
                            @if ($u->id !== auth()->id())
                                <x-btn variant="ghost" size="sm" icon="power" wire:click="toggleActive({{ $u->id }})"
                                       wire:confirm="{{ $u->is_active ? 'Désactiver ce compte ? La personne sera déconnectée.' : 'Réactiver ce compte ?' }}">{{ $u->is_active ? 'Désactiver' : 'Réactiver' }}</x-btn>
                            @endif
                        </div>
                    @endif
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="users" :title="__('Aucun compte trouvé')" /></li>
            @endforelse
        </ul>
    </x-card>

    <x-panel show="showForm" :title="$editingId ? 'Modifier le compte' : 'Nouveau compte'">
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <x-field :label="__('Identifiant')" for="u-username" error="username"><x-input id="u-username" wire:model="username" autocomplete="off" /></x-field>
                <x-field :label="__('Rôle')" for="u-role" error="role">
                    <x-select id="u-role" wire:model="role">
                        <option value="">{{ __('— Choisir —') }}</option>
                        @foreach ($roles as $r)<option value="{{ $r->name }}">{{ config('jibayat.roles.'.$r->name, $r->name) }}</option>@endforeach
                    </x-select>
                </x-field>
            </div>
            <x-field :label="__('Nom complet')" for="u-name" error="name"><x-input id="u-name" wire:model="name" /></x-field>
            <x-field :label="__('Nom en arabe')" for="u-name-ar" error="name_ar"><x-input id="u-name-ar" wire:model="name_ar" dir="rtl" lang="ar" class="font-ar" /></x-field>
            <div class="grid grid-cols-2 gap-3">
                <x-field :label="__('E-mail')" for="u-email" error="email"><x-input id="u-email" type="email" wire:model="email" /></x-field>
                <x-field :label="__('Téléphone')" for="u-phone" error="phone"><x-input id="u-phone" wire:model="phone" /></x-field>
            </div>
            <x-field :label="$editingId ? 'Nouveau mot de passe (laisser vide pour ne pas changer)' : 'Mot de passe provisoire'" for="u-password" error="password"
                     :hint="__('10 caractères minimum, avec majuscule, minuscule et chiffre. La personne devra le changer à sa prochaine connexion.')">
                <x-input id="u-password" type="password" wire:model="password" autocomplete="new-password" />
            </x-field>
            <div class="flex justify-end gap-2 pt-2">
                <x-btn variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-btn>
                <x-btn type="submit" icon="check">{{ __('Enregistrer') }}</x-btn>
            </div>
        </form>
    </x-panel>
</div>
