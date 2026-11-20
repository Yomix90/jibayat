<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#1E2A3A">
        <title>{{ __('Connexion') }} · Jibayat</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center px-4 py-8">
            <div class="w-full max-w-sm overflow-hidden rounded-[24px] bg-white shadow-xl ring-1 ring-line">
                <div class="zellige relative bg-navy px-6 py-7 text-center text-white" style="--zellige-opacity: .12">
                    <x-locale-switch dark class="absolute end-3 top-3" />
                    <x-emblem icon="landmark" :size="64" fill="#C9A13B" stroke="rgba(255,255,255,.35)" icon-class="relative h-[42%] w-[42%] text-navy" class="mx-auto" />
                    <h1 class="mt-3 text-xl font-bold">{{ __('Jibayat') }}</h1>
                    <p class="font-ar text-sm text-white/75" lang="ar">جبايات · تدبير مداخيل الجماعة</p>
                    <p class="mt-1 text-xs text-white/60">{{ __('Gestion fiscale communale') }}</p>
                </div>
                <div class="px-6 py-6">
                    {{ $slot }}
                </div>
            </div>
            <p class="mt-6 text-xs text-muted">{{ __('Version') }} {{ config('jibayat.version') }}</p>
        </div>
    </body>
</html>
