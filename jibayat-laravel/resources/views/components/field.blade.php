@props(['label', 'for' => null, 'error' => null, 'hint' => null])
{{-- Champ de formulaire : libellé, contrôle (slot), aide et erreur de validation. --}}
<div {{ $attributes }}>
    <label @if($for) for="{{ $for }}" @endif class="block text-[13px] font-semibold text-ink">{{ tr($label) }}</label>
    <div class="mt-1">{{ $slot }}</div>
    @if ($hint)<p class="mt-1 text-xs text-muted">{{ $hint }}</p>@endif
    @if ($error)
        @error($error)<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
    @endif
</div>
