<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="login" value="Identifiant" />
            <x-text-input id="login" class="mt-1 block w-full" type="text" name="login" :value="old('login')"
                          required autofocus autocomplete="username" :placeholder="__('nom d\'utilisateur ou e-mail')" />
            <x-input-error :messages="$errors->get('login')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Mot de passe" />
            <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="inline-flex items-center">
            <input id="remember_me" type="checkbox" class="rounded border-line text-accent shadow-sm focus:ring-gold" name="remember">
            <span class="ms-2 text-sm text-muted">{{ __('Mémoriser la session sur ce poste') }}</span>
        </label>

        <x-primary-button class="w-full justify-center">
            <x-svg-icon name="log-out" class="me-2 h-4 w-4 rotate-180" /> {{ __('Se connecter') }}
        </x-primary-button>

        <div id="passkey-login" class="hidden space-y-2">
            <div class="flex items-center gap-3 text-xs text-muted"><span class="h-px flex-1 bg-line"></span>{{ __('ou') }}<span class="h-px flex-1 bg-line"></span></div>
            <button type="button" id="passkey-btn"
                    class="pressable flex min-h-11 w-full items-center justify-center gap-2 rounded-full bg-gold px-4 text-sm font-semibold text-navy hover:brightness-105">
                <x-svg-icon name="fingerprint" class="h-5 w-5" /> {{ __('Se connecter avec l’empreinte') }}
            </button>
            <p id="passkey-error" class="hidden text-center text-xs font-semibold text-red-600" role="alert"></p>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const box = document.getElementById('passkey-login');
                if (!window.jibayatPasskey || !window.jibayatPasskey.supported()) return;
                box.classList.remove('hidden');
                document.getElementById('passkey-btn').addEventListener('click', async () => {
                    const err = document.getElementById('passkey-error');
                    err.classList.add('hidden');
                    try {
                        await window.jibayatPasskey.login(document.getElementById('login').value.trim());
                    } catch (e) {
                        err.textContent = 'Empreinte non reconnue. Enregistrez-la d’abord depuis « Mon profil », ou utilisez votre mot de passe.';
                        err.classList.remove('hidden');
                    }
                });
            });
        </script>

        <p class="flex items-center justify-center gap-1.5 text-center text-xs text-muted">
            <x-svg-icon name="shield-check" class="h-3.5 w-3.5 text-gold" />
            Fermeture automatique après {{ config('jibayat.auto_logout_minutes') }} min sans activité
        </p>
    </form>
</x-guest-layout>
