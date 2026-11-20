<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />
    <form wire:submit="save" class="max-w-3xl">
        <x-card :title="__('Identité et coordonnées')">
            @include('livewire.caisse.partials.taxpayer-form')
            <div class="mt-5 flex justify-end gap-2">
                <x-btn variant="secondary" :href="route('caisse.contribuables')">{{ __('Annuler') }}</x-btn>
                <x-btn type="submit" variant="gold" icon="user-plus" wire:loading.attr="disabled">{{ __('Créer le contribuable') }}</x-btn>
            </div>
        </x-card>
    </form>
</div>
