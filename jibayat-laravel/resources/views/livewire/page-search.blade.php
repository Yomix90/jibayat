<div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
    <label class="flex h-12 items-center gap-3 rounded-full border border-line bg-white px-4 shadow-sm focus-within:border-gold">
        <x-svg-icon name="search" class="h-5 w-5 shrink-0 text-accent" />
        <span class="sr-only">{{ __('Rechercher une page, un contribuable, un dossier ou une quittance') }}</span>
        <input type="search" wire:model.live.debounce.250ms="query" @focus="open = true" @input="open = true"
               placeholder="{{ __('Chercher : page, contribuable, CIN, n° de dossier, quittance…') }}" autocomplete="off"
               class="h-full w-full border-0 bg-transparent p-0 text-[15px] placeholder:text-muted focus:ring-0">
    </label>

    @if (mb_strlen(trim($query)) >= 2)
        <div x-show="open" x-transition.opacity
             class="absolute inset-x-0 top-14 z-30 max-h-[70vh] overflow-y-auto surface p-1.5 shadow-xl">
            @if ($results->count())
                <p class="px-2.5 pb-1 pt-1.5 text-[11px] font-bold uppercase tracking-[.08em] text-muted">{{ __('Pages') }}</p>
                @foreach ($results as $page)
                    <a href="{{ route($page['route']) }}" wire:key="p-{{ $page['route'] }}"
                       class="flex items-center gap-3 rounded-xl px-2.5 py-2 hover:bg-bg focus:bg-bg focus:outline-none"
                       style="--from: {{ $page['colors']['from'] }}; --to: {{ $page['colors']['to'] }}">
                        <span class="icon-square !h-9 !w-9 !rounded-lg"><x-svg-icon :name="$page['icon']" class="h-[18px] w-[18px]" /></span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-ink">{{ __($page['title']) }}</span>
                            <span class="block truncate text-xs text-muted">{{ __($page['space_title']) }} · {{ __($page['section_title']) }}{{ __($page['subtitle']) ? ' · '.__($page['subtitle']) : '' }}</span>
                        </span>
                    </a>
                @endforeach
            @endif
            @if ($records->count())
                <p class="px-2.5 pb-1 pt-2 text-[11px] font-bold uppercase tracking-[.08em] text-muted">{{ __('Données') }}</p>
                @foreach ($records as $i => $r)
                    <a href="{{ $r['url'] }}" wire:key="r-{{ $i }}" class="flex items-center gap-3 rounded-xl px-2.5 py-2 hover:bg-bg focus:bg-bg focus:outline-none">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-navy text-gold"><x-svg-icon :name="$r['icon']" class="h-[18px] w-[18px]" /></span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-ink">{{ tr($r['title']) }}</span>
                            <span class="block truncate text-xs text-muted">{{ tr($r['type']) }} · {{ $r['subtitle'] }}</span>
                        </span>
                    </a>
                @endforeach
            @endif
            @if (! $results->count() && ! $records->count())
                <p class="px-3 py-4 text-center text-sm text-muted">Aucun résultat pour « {{ $query }} ».</p>
            @endif
        </div>
    @endif
</div>
