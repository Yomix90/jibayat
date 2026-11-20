<x-app-layout :title="__($page['title'])">
    <div class="space-y-6" style="--from: {{ $page['colors']['from'] }}; --to: {{ $page['colors']['to'] }}">
        <nav class="flex items-center gap-1 text-xs font-semibold text-muted" aria-label="{{ __('Fil d\'Ariane') }}">
            <a href="{{ route('home') }}" class="hover:text-ink">{{ __('Accueil') }}</a>
            <x-svg-icon name="chevron-right" class="h-3.5 w-3.5 rtl:rotate-180" />
            <a href="{{ route('espace', $page['space']) }}" class="hover:text-ink">{{ __($page['space_title']) }}</a>
            <x-svg-icon name="chevron-right" class="h-3.5 w-3.5 rtl:rotate-180" />
            <span class="text-ink">{{ __($page['section_title']) }}</span>
        </nav>

        <header class="flex items-center gap-4">
            <span class="icon-square !h-14 !w-14 !rounded-2xl"><x-svg-icon :name="$page['icon']" class="h-7 w-7" /></span>
            <div>
                <h1 class="text-xl font-bold text-ink">{{ __($page['title']) }}</h1>
                @if ($page['subtitle'])
                    <p class="text-sm text-muted" @if(preg_match('/\p{Arabic}/u', $page['subtitle'])) lang="ar" dir="rtl" @endif>{{ __($page['subtitle']) }}</p>
                @endif
            </div>
        </header>

        <section class="zellige rounded-2xl border border-line bg-white p-6 text-center" style="--zellige-opacity: 0">
            <x-emblem :icon="$page['icon']" :size="72" :fill="$page['colors']['tile']" stroke="rgba(255,255,255,.4)" class="mx-auto" />
            <h2 class="mt-4 text-base font-bold text-ink">{{ __('Page en préparation') }}</h2>
            <p class="mx-auto mt-1 max-w-md text-sm text-muted">
                {{ __('Cette page sera construite pendant la phase 5, à partir de l\'écran équivalent de l\'application Flask.') }}
            </p>
            @if ($page['flask'])
                <p class="mt-4 inline-flex items-center gap-2 rounded-full bg-bg px-3 py-1 text-xs text-muted">
                    <x-svg-icon name="file-text" class="h-3.5 w-3.5" /> {{ __('Référence Flask :') }} <code class="font-mono text-ink">{{ $page['flask'] }}</code>
                </p>
            @else
                <p class="mt-4 inline-flex items-center gap-2 rounded-full bg-bg px-3 py-1 text-xs text-muted">{{ __('Nouvelle page (sans équivalent Flask)') }}</p>
            @endif
        </section>

        @if ($space)
            @foreach ($space['sections'] as $section)
                @continue($section['key'] !== $page['section'])
                <section class="space-y-3">
                    <x-section-title :color="$space['colors']['tile']" :count="count($section['pages'])">Dans « {{ __($section['title']) }} »</x-section-title>
                    <div class="grid grid-cols-3 gap-2.5 sm:grid-cols-4 lg:grid-cols-6">
                        @foreach ($section['pages'] as $other)
                            <x-tile :page="$other" :class="$other['route'] === $page['route'] ? 'ring-2 ring-gold' : ''" />
                        @endforeach
                    </div>
                </section>
            @endforeach
        @endif
    </div>
</x-app-layout>
