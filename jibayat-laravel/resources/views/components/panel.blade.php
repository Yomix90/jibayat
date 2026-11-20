@props(['show', 'title', 'maxWidth' => 'max-w-lg'])
{{-- Panneau modal piloté par une propriété Livewire booléenne : <x-panel show="showForm" title="…"> --}}
<div x-data="{ open: $wire.entangle('{{ $show }}').live }" x-show="open" x-cloak
     class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center"
     x-on:keydown.escape.window="open = false" role="dialog" aria-modal="true" aria-label="{{ tr($title) }}">
    <div class="absolute inset-0 bg-navy/40 backdrop-blur-[2px]" x-show="open" x-transition.opacity.duration.250ms x-on:click="open = false"></div>
    <div x-show="open"
         x-transition:enter="enter" x-transition:enter-start="sheet-from" x-transition:enter-end="enter-to"
         x-transition:leave="enter" x-transition:leave-start="enter-to" x-transition:leave-end="sheet-from"
         class="relative max-h-[92vh] w-full {{ $maxWidth }} overflow-y-auto overscroll-contain rounded-t-[24px] bg-white px-5 pb-5 pt-2 sm:rounded-[24px] sm:pt-5"
         style="box-shadow: var(--shadow-lg); padding-bottom: max(1.25rem, env(safe-area-inset-bottom))">
        <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-line sm:hidden" aria-hidden="true"></div>
        <div class="mb-4 flex items-center justify-between gap-2">
            <h2 class="text-[17px] font-semibold text-ink">{{ tr($title) }}</h2>
            <button type="button" class="round-btn !h-8 !w-8 text-muted hover:bg-bg" x-on:click="open = false" aria-label="{{ __('Fermer') }}">
                <x-svg-icon name="x" class="h-4 w-4" />
            </button>
        </div>
        {{ $slot }}
    </div>
</div>
