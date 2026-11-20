<section id="empreinte" class="max-w-xl" x-data="{ alias: '', busy: false, error: '', supported: !!(window.jibayatPasskey && window.jibayatPasskey.supported()) }">
    <h2 class="text-lg font-semibold text-ink">{{ __('Empreinte / clé d’accès') }}</h2>
    <p class="mt-1 text-sm text-muted">{{ __('Connectez-vous avec l’empreinte digitale, la reconnaissance faciale ou le code de cet appareil, sans saisir votre mot de passe.') }}</p>

    <ul class="mt-4 divide-y divide-line rounded-xl border border-line text-sm">
        @forelse ($credentials as $c)
            <li class="flex items-center gap-3 px-3 py-2.5" wire:key="pk-{{ $c->id }}">
                <x-svg-icon name="fingerprint" class="h-5 w-5 text-gold" />
                <span class="min-w-0 flex-1"><span class="block font-semibold">{{ $c->alias ?: 'Appareil' }}</span>
                    <span class="text-xs text-muted">Ajouté le {{ $c->created_at?->format('d/m/Y') }}</span></span>
                <x-btn variant="ghost" size="sm" icon="trash-2" wire:click="remove('{{ $c->id }}')" wire:confirm="Retirer cet appareil ? Vous ne pourrez plus vous connecter avec son empreinte." aria-label="Retirer {{ $c->alias }}" />
            </li>
        @empty
            <li class="px-3 py-3 text-xs text-muted">{{ __('Aucune empreinte enregistrée.') }}</li>
        @endforelse
    </ul>

    <template x-if="supported">
        <form class="mt-4 flex flex-wrap items-end gap-2" x-on:submit.prevent="busy = true; error = ''; window.jibayatPasskey.register(alias).then(() => { alias = ''; $wire.registered() }).catch(() => error = 'Enregistrement annulé ou impossible sur cet appareil.').finally(() => busy = false)">
            <x-field :label="__('Nom de l’appareil')" for="pk-alias" class="min-w-0 flex-1"><x-input id="pk-alias" x-model="alias" :placeholder="__('Ex. PC de la régie')" maxlength="60" /></x-field>
            <x-btn type="submit" variant="gold" icon="fingerprint" x-bind:disabled="busy">{{ __('Enregistrer mon empreinte') }}</x-btn>
        </form>
    </template>
    <p x-show="!supported" class="mt-3 text-xs text-muted">{{ __('Ce navigateur ne prend pas en charge l’empreinte (WebAuthn). Elle fonctionne en HTTPS ou sur « localhost ».') }}</p>
    <p x-show="error" x-text="error" class="mt-2 text-xs font-semibold text-red-600" role="alert"></p>
</section>
