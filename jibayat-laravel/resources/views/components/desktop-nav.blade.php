{{--
    Barre latérale : trois groupes séparés (modules fiscaux, régie & documents, configuration).
    Un module s'ouvre sur son tableau de bord (son lien n'est donc pas répété dans le sous-menu) ;
    la flèche affiche ses autres pages.
--}}
@php($currentRoute = request()->route()?->getName())
@php($currentSpace = request()->route('space') ?? (str_contains((string) $currentRoute, '.') ? explode('.', $currentRoute)[0] : null))
<nav {{ $attributes->merge(['class' => 'flex h-full flex-col']) }} aria-label="Navigation principale">
    <div class="zellige flex items-center gap-3 bg-navy px-5 py-5 text-white" style="--zellige-opacity: .12">
        <a href="{{ route('home') }}" class="flex flex-1 items-center gap-3">
            <x-emblem icon="landmark" :size="40" fill="#C9A13B" stroke="rgba(255,255,255,.35)" icon-class="relative h-[42%] w-[42%] text-navy" />
            <span>
                <span class="block text-lg font-bold leading-tight">{{ __('Jibayat') }}</span>
                <span class="font-ar block text-xs text-white/70" lang="ar">جبايات</span>
            </span>
        </a>
        <button type="button" class="round-btn !h-8 !w-8 bg-white/10 text-white lg:hidden" x-on:click="menu = false" aria-label="{{ __('Fermer le menu') }}">
            <x-svg-icon name="x" class="h-4 w-4" />
        </button>
    </div>
    <div class="flex-1 overflow-y-auto px-3 py-4">
        <a href="{{ route('home') }}" @class([
            'pressable flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold',
            'bg-bg text-ink' => $currentRoute === 'home',
            'text-muted hover:bg-bg hover:text-ink' => $currentRoute !== 'home',
        ])>
            <x-svg-icon name="layout-dashboard" class="h-[18px] w-[18px]" /> {{ __('Tableau de bord général') }}
        </a>

        @foreach ($navGroups as $group)
            <div class="mt-4 border-t border-line pt-3 first-of-type:mt-3" role="group" aria-labelledby="nav-group-{{ $group['key'] }}">
                <p id="nav-group-{{ $group['key'] }}" class="px-3 pb-1.5 text-[10.5px] font-bold uppercase tracking-[.1em] text-muted">{{ __($group['title']) }}</p>
                <div class="space-y-0.5">
                    @foreach ($group['spaces'] as $space)
                        @php($active = $currentSpace === $space['key'])
                        @php($dashboard = $space['dashboard_route'] && collect($space['sections'])->contains(fn ($s) => isset($s['pages'][$space['dashboard_route']])) ? $space['dashboard_route'] : null)
                        @php($sections = collect($space['sections'])->map(fn ($s) => array_merge($s, ['pages' => array_filter($s['pages'], fn ($p) => $p['route'] !== $dashboard)]))->filter(fn ($s) => count($s['pages'])))
                        <div x-data="{ open: @js($active) }">
                            <div class="flex items-center">
                                @if ($dashboard)
                                    <a href="{{ route($dashboard) }}" @class([
                                        'pressable flex flex-1 items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold',
                                        'bg-bg text-ink' => $active,
                                        'text-ink/80 hover:bg-bg' => ! $active,
                                    ]) style="--from: {{ $space['colors']['from'] }}; --to: {{ $space['colors']['to'] }}"
                                       @if ($currentRoute === $dashboard) aria-current="page" @endif title="{{ __('Tableau de bord') }} · {{ __($space['title']) }}">
                                        <span class="icon-square !h-7 !w-7 !rounded-lg"><x-svg-icon :name="$space['icon']" class="h-4 w-4" /></span>
                                        <span class="flex-1 truncate">{{ __($space['title']) }}</span>
                                    </a>
                                @else
                                    <button type="button" @click="open = !open" @class([
                                        'pressable flex flex-1 items-center gap-3 rounded-xl px-3 py-2 text-start text-sm font-semibold',
                                        'bg-bg text-ink' => $active,
                                        'text-ink/80 hover:bg-bg' => ! $active,
                                    ]) style="--from: {{ $space['colors']['from'] }}; --to: {{ $space['colors']['to'] }}" :aria-expanded="open.toString()">
                                        <span class="icon-square !h-7 !w-7 !rounded-lg"><x-svg-icon :name="$space['icon']" class="h-4 w-4" /></span>
                                        <span class="flex-1 truncate">{{ __($space['title']) }}</span>
                                    </button>
                                @endif
                                @if ($sections->isNotEmpty())
                                    <button type="button" @click="open = !open" class="round-btn !h-8 !w-8 text-muted hover:bg-bg"
                                            :aria-expanded="open.toString()" aria-label="{{ __('Afficher les pages') }} · {{ __($space['title']) }}">
                                        <span class="inline-block transition-transform duration-200 rtl:-scale-x-100" x-bind:class="open && 'rotate-90'"><x-svg-icon name="chevron-right" class="h-4 w-4" /></span>
                                    </button>
                                @endif
                            </div>
                            <div x-show="open" x-cloak class="mb-1 ms-6 mt-1 space-y-2 border-s border-line ps-3">
                                @foreach ($sections as $section)
                                    <div>
                                        <p class="px-2 pb-0.5 pt-1 text-[10.5px] font-bold uppercase tracking-[.08em] text-muted">{{ __($section['title']) }}</p>
                                        @foreach ($section['pages'] as $page)
                                            <a href="{{ route($page['route']) }}" @class([
                                                'pressable block truncate rounded-lg px-2 py-1.5 text-[13px]',
                                                'bg-gold/15 font-semibold text-ink' => $currentRoute === $page['route'],
                                                'text-ink/75 hover:bg-bg' => $currentRoute !== $page['route'],
                                            ]) @if ($currentRoute === $page['route']) aria-current="page" @endif>{{ __($page['title']) }}</a>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
    <div class="border-t border-line px-5 py-3 text-xs text-muted">{{ __('Version') }} {{ config('jibayat.version') }}</div>
</nav>
