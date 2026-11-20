<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#1E2A3A">
        <title>{{ isset($title) ? __($title).' · ' : '' }}{{ __('Jibayat') }}</title>
        <style>[x-cloak]{display:none!important}</style>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased" data-auto-logout="{{ config('jibayat.auto_logout_minutes') }}"
          data-status-url="{{ route('session.status') }}" data-touch-url="{{ route('session.touch') }}"
          x-data="{ menu: false }" x-on:keydown.escape.window="menu = false">
        <a href="#contenu" class="sr-only focus:not-sr-only focus:absolute focus:start-3 focus:top-3 focus:z-50 focus:rounded-lg focus:bg-white focus:px-3 focus:py-2">{{ __('Aller au contenu') }}</a>

        {{-- Menu latéral : fixe sur ordinateur, tiroir sur téléphone --}}
        <div class="fixed inset-0 z-40 bg-navy/40 lg:hidden" x-show="menu" x-transition.opacity.duration.250ms x-cloak x-on:click="menu = false"></div>
        <aside id="menu-lateral"
               class="fixed inset-y-0 start-0 z-50 w-72 -translate-x-full border-e border-line bg-white transition-transform duration-300 ease-[cubic-bezier(.32,.72,0,1)] rtl:translate-x-full lg:translate-x-0 rtl:lg:translate-x-0"
               :class="menu && '!translate-x-0'" aria-label="{{ __('Menu') }}">
            <x-desktop-nav />
        </aside>

        <div class="min-h-screen lg:ps-72">
            <x-top-bar :title="$title ?? null" />
            <main id="contenu" class="mx-auto w-full max-w-7xl px-4 pb-10 pt-4 lg:px-8 lg:pt-6">
                {{ $slot }}
            </main>
        </div>
        <x-toast />
    </body>
</html>
