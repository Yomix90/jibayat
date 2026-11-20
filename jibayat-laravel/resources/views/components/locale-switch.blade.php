@props(['dark' => false])
{{-- Bascule de langue FR / ع (formulaire POST : choix mémorisé dans le compte). --}}
@php($ar = app()->getLocale() === 'ar')
<form method="POST" action="{{ route('langue', $ar ? 'fr' : 'ar') }}" {{ $attributes->merge(['class' => 'shrink-0']) }}>
    @csrf
    <button type="submit" class="round-btn font-semibold {{ $dark ? 'bg-white/10 text-white hover:bg-white/20' : 'border border-line bg-white text-ink hover:border-gold' }}"
            title="{{ $ar ? 'Afficher en français' : 'العرض بالعربية' }}" aria-label="{{ $ar ? 'Afficher en français' : 'العرض بالعربية' }}" lang="{{ $ar ? 'fr' : 'ar' }}">
        <span class="{{ $ar ? 'text-sm' : 'font-ar text-base leading-none' }}">{{ $ar ? 'FR' : 'ع' }}</span>
    </button>
</form>
