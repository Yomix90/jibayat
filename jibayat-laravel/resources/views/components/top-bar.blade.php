@props(['title' => null])
{{-- Barre du haut : menu (téléphone), recherche de page, état de session, empreinte, compte et déconnexion. --}}
@php($user = auth()->user())
@php($hasPasskey = $user->webAuthnCredentials()->whereEnabled()->exists())
<header class="material scroll-edge sticky top-0 z-30">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-2 px-3 lg:gap-3 lg:px-8">
        <button type="button" class="round-btn text-ink hover:bg-bg lg:hidden" x-on:click="menu = true"
                aria-controls="menu-lateral" :aria-expanded="menu.toString()" aria-label="{{ __('Ouvrir le menu') }}">
            <x-svg-icon name="menu" class="h-6 w-6" />
        </button>
        <a href="{{ route('home') }}" class="flex items-center gap-2 lg:hidden" aria-label="{{ __('Accueil') }}">
            <x-emblem icon="landmark" :size="34" fill="#C9A13B" stroke="rgba(30,42,58,.25)" icon-class="relative h-[42%] w-[42%] text-navy" />
        </a>

        <x-page-search class="min-w-0 flex-1 lg:max-w-xl" />

        <div class="ms-auto hidden items-center gap-1.5 rounded-full bg-bg px-3 py-1.5 text-xs text-muted xl:flex"
             title="Fermeture après {{ config('jibayat.auto_logout_minutes') }} min sans activité">
            <span class="h-2 w-2 rounded-full bg-online" aria-hidden="true"></span>
            {{ $hasPasskey ? __('Empreinte activée') : __('Session ouverte') }} · {{ __('fermeture dans') }} <span class="tabular-nums text-ink" data-session-countdown></span>
        </div>

        <x-locale-switch />

        <livewire:notification-bell />

        <a href="{{ route('profile.edit') }}#empreinte" class="round-btn hidden shrink-0 sm:grid {{ $hasPasskey ? 'bg-gold text-navy' : 'border border-gold/60 text-[#7a5d16]' }} hover:brightness-110"
           title="{{ $hasPasskey ? __('Empreinte activée') : __('Activer la connexion par empreinte') }}" aria-label="{{ $hasPasskey ? __('Empreinte activée') : __('Activer la connexion par empreinte') }}">
            <x-svg-icon name="fingerprint" class="h-5 w-5" />
        </a>

        <div class="relative shrink-0" x-data="{ open: false }" x-on:click.outside="open = false">
            <button type="button" x-on:click="open = !open" :aria-expanded="open.toString()" aria-haspopup="menu"
                    class="pressable flex items-center gap-2 rounded-full p-1 pe-1 hover:bg-black/5 sm:pe-3" aria-label="{{ __('Mon compte') }}">
                <span class="grid h-9 w-9 place-items-center rounded-full bg-navy text-xs font-bold text-gold">{{ $user->initials() }}</span>
                <span class="hidden text-start leading-tight sm:block">
                    <span class="block max-w-[10rem] truncate text-sm font-semibold text-ink">{{ $user->name }}</span>
                    <span class="block text-[11px] font-semibold text-[#7a5d16]">{{ $user->roleLabel() }}</span>
                </span>
            </button>
            <div x-show="open" x-cloak role="menu"
                 x-transition:enter="enter" x-transition:enter-start="enter-from" x-transition:enter-end="enter-to"
                 x-transition:leave="enter" x-transition:leave-start="enter-to" x-transition:leave-end="enter-from"
                 class="absolute end-0 top-12 w-60 origin-top-right overflow-hidden rounded-2xl border border-line bg-white py-1 rtl:origin-top-left"
                 style="box-shadow: var(--shadow-lg)">
                <p class="border-b border-line px-4 py-2 text-xs text-muted">
                    Fermeture après {{ config('jibayat.auto_logout_minutes') }} min sans activité
                </p>
                <a href="{{ route('profile.edit') }}" role="menuitem" class="flex items-center gap-2 px-4 py-2.5 text-sm hover:bg-bg">
                    <x-svg-icon name="user-round" class="h-4 w-4 text-muted" /> {{ __('Mon profil & mot de passe') }}
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" role="menuitem" class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-red-600 hover:bg-bg">
                        <x-svg-icon name="log-out" class="h-4 w-4" /> {{ __('Se déconnecter') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
