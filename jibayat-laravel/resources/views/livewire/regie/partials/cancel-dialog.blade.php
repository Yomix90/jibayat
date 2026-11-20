{{-- Annulation motivée d'une pièce officielle (elle reste visible avec son motif). Paramètres : $title, $hint. --}}
@if ($cancelId)
    <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="{{ $title }}">
        <div class="absolute inset-0 bg-navy/40 backdrop-blur-[2px]" wire:click="$set('cancelId', null)"></div>
        <form wire:submit="confirmCancel" class="relative w-full max-w-md space-y-3 rounded-t-[24px] bg-white p-5 sm:rounded-[24px]" style="box-shadow: var(--shadow-lg)">
            <h2 class="text-[17px] font-semibold">{{ $title }}</h2>
            <p class="text-xs text-muted">{{ $hint ?? __('La pièce reste au registre, marquée annulée avec son motif ; elle apparaît dans « Pièces annulées ».') }}</p>
            <x-field :label="__('Motif')" for="c-reason" error="cancelReason"><x-textarea id="c-reason" rows="2" wire:model="cancelReason" /></x-field>
            <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('cancelId', null)">{{ __('Retour') }}</x-btn><x-btn type="submit" variant="danger" icon="ban">{{ __('Annuler la pièce') }}</x-btn></div>
        </form>
    </div>
@endif
