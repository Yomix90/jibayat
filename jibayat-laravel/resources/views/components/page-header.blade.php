@props(['route' => null, 'title' => null, 'subtitle' => null])
{{-- En-tête d'une page du registre : fil d'Ariane, icône au dégradé de l'espace, titre, actions (slot). --}}
@php
    $page = app(\App\Support\Navigation::class)->page($route ?? request()->route()?->getName());
    $title ??= isset($page['title']) ? __($page['title']) : '';
    $subtitle ??= isset($page['subtitle']) ? __($page['subtitle']) : null;
@endphp
<div class="space-y-4" @if($page) style="--from: {{ $page['colors']['from'] }}; --to: {{ $page['colors']['to'] }}" @endif>
    @if ($page)
        <nav class="flex flex-wrap items-center gap-1 text-xs font-semibold text-muted" aria-label="{{ __('Fil d\'Ariane') }}">
            <a href="{{ route('home') }}" class="rounded hover:text-ink">{{ __('Accueil') }}</a>
            <x-svg-icon name="chevron-right" class="h-3.5 w-3.5 rtl:rotate-180" />
            <a href="{{ route('espace', $page['space']) }}" class="hover:text-ink">{{ __($page['space_title']) }}</a>
            <x-svg-icon name="chevron-right" class="h-3.5 w-3.5 rtl:rotate-180" />
            <span>{{ __($page['section_title']) }}</span>
            @isset($crumb)
                <x-svg-icon name="chevron-right" class="h-3.5 w-3.5 rtl:rotate-180" />
                <span class="text-ink">{{ $crumb }}</span>
            @endisset
        </nav>
    @endif
    <header class="flex flex-wrap items-center gap-4">
        <span class="icon-square !h-14 !w-14 !rounded-2xl"><x-svg-icon :name="$page['icon'] ?? 'settings'" class="h-7 w-7" /></span>
        <div class="min-w-0 flex-1 basis-72">
            <h1 class="text-[1.375rem] font-bold text-ink sm:text-2xl">{{ tr($title) }}</h1>
            @if ($subtitle)
                <p class="text-sm text-muted" @if(preg_match('/\p{Arabic}/u', $subtitle)) lang="ar" @endif>{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">{{ $actions }}</div>
        @endisset
    </header>
</div>
