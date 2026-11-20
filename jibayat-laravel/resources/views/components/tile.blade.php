@props(['page'])
{{-- Tuile de page : icône au dégradé de l'espace, titre centré, sous-titre gris (FR ou AR). --}}
@php($isAr = $page['subtitle'] && preg_match('/\p{Arabic}/u', $page['subtitle']))
<a href="{{ route($page['route']) }}" {{ $attributes->merge(['class' => 'tile']) }}
   style="--from: {{ $page['colors']['from'] }}; --to: {{ $page['colors']['to'] }}">
    <span class="icon-square"><x-svg-icon :name="$page['icon']" class="h-[22px] w-[22px]" /></span>
    <span class="text-[13px] font-bold leading-tight text-ink">{{ __($page['title']) }}</span>
    @if ($page['subtitle'])
        <span @class(['text-[11.5px] leading-tight text-muted', 'font-ar' => $isAr]) @if($isAr) lang="ar" dir="rtl" @endif>{{ __($page['subtitle']) }}</span>
    @endif
</a>
