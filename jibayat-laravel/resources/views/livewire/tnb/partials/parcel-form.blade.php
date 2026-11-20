{{-- Formulaire terrain (trait EditsParcels) : $taxpayers, $statuses, $editingId, $newTaxpayer --}}
<x-panel show="showForm" :title="$editingId ? 'Modifier le terrain' : ($census ?? false ? 'Nouveau terrain recensé' : 'Nouveau terrain')" max-width="max-w-2xl">
    <form wire:submit="saveParcel" class="space-y-4">
        <div class="grid gap-3 sm:grid-cols-3">
            <x-field :label="__('N° de terrain')" for="p-number" error="form.number"><x-input id="p-number" wire:model="form.number" /></x-field>
            <x-field :label="__('Zone')" for="p-zone" error="form.zone">
                <x-select id="p-zone" wire:model="form.zone">@foreach (['A', 'B', 'C', 'D', 'E'] as $z)<option value="{{ $z }}">Zone {{ $z }}</option>@endforeach</x-select>
            </x-field>
            <x-field :label="__('Superficie (m²)')" for="p-area" error="form.area"><x-input id="p-area" type="number" step="0.01" min="0" wire:model="form.area" /></x-field>
        </div>

        @unless ($editingId)
            <fieldset class="space-y-2 rounded-xl border border-line p-3">
                <legend class="px-1 text-xs font-bold uppercase tracking-[.06em] text-muted">{{ __('Redevable') }}</legend>
                @if (! $newTaxpayer)
                    <div class="grid gap-2 sm:grid-cols-[1fr_1.4fr]">
                        <x-input type="search" wire:model.live.debounce.300ms="taxpayerSearch" :placeholder="__('Chercher nom, CIN…')" :aria-label="__('Chercher un contribuable')" />
                        <x-select wire:model="form.taxpayer_id" :aria-label="__('Redevable')">
                            <option value="">{{ __('— Choisir —') }}</option>
                            @foreach ($taxpayers as $t)<option value="{{ $t->id }}">{{ $t->displayName() }}{{ $t->cin ? ' · '.$t->cin : '' }}</option>@endforeach
                        </x-select>
                    </div>
                    @error('form.taxpayer_id')<p class="text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                    <button type="button" wire:click="$set('newTaxpayer', true)" class="text-xs font-semibold text-accent hover:underline">{{ __('+ Nouveau contribuable') }}</button>
                @else
                    <div class="grid gap-2 sm:grid-cols-2">
                        <x-field :label="__('Nom')" for="t-ln" error="taxpayerForm.last_name"><x-input id="t-ln" wire:model="taxpayerForm.last_name" /></x-field>
                        <x-field :label="__('Prénom')" for="t-fn"><x-input id="t-fn" wire:model="taxpayerForm.first_name" /></x-field>
                        <x-field label="الاسم العائلي" for="t-lna"><x-input id="t-lna" dir="rtl" class="font-ar" wire:model="taxpayerForm.last_name_ar" /></x-field>
                        <x-field label="الاسم الشخصي" for="t-fna"><x-input id="t-fna" dir="rtl" class="font-ar" wire:model="taxpayerForm.first_name_ar" /></x-field>
                        <x-field label="CIN" for="t-cin"><x-input id="t-cin" wire:model="taxpayerForm.cin" /></x-field>
                        <x-field :label="__('Téléphone')" for="t-ph"><x-input id="t-ph" wire:model="taxpayerForm.phone" /></x-field>
                    </div>
                    <button type="button" wire:click="$set('newTaxpayer', false)" class="text-xs font-semibold text-muted hover:underline">{{ __('Choisir un contribuable existant') }}</button>
                @endif
            </fieldset>
        @endunless

        <fieldset class="grid gap-3 rounded-xl border border-line p-3 sm:grid-cols-2">
            <legend class="px-1 text-xs font-bold uppercase tracking-[.06em] text-muted">{{ __('Situation du terrain') }}</legend>
            <x-field :label="__('Lotissement')" for="p-sub" error="form.subdivision"><x-input id="p-sub" wire:model="form.subdivision" /></x-field>
            <x-field :label="__('N° de lot / parcelle')" for="p-lot" error="form.parcel_number"><x-input id="p-lot" wire:model="form.parcel_number" /></x-field>
            <x-field :label="__('Titre foncier / réquisition')" for="p-tf" error="form.land_title"><x-input id="p-tf" wire:model="form.land_title" /></x-field>
            <x-field :label="__('Quartier')" for="p-dist" error="form.district"><x-input id="p-dist" wire:model="form.district" /></x-field>
            <x-field :label="__('Adresse')" for="p-addr" error="form.address"><x-input id="p-addr" wire:model="form.address" /></x-field>
            <x-field label="العنوان" for="p-addr-ar" error="form.address_ar"><x-input id="p-addr-ar" dir="rtl" class="font-ar" wire:model="form.address_ar" /></x-field>
        </fieldset>

        <div class="grid gap-3 sm:grid-cols-3">
            <x-field :label="__('Statut')" for="p-status" error="form.status">
                <x-select id="p-status" wire:model="form.status">@foreach ($statuses as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</x-select>
            </x-field>
            <x-field :label="__('Date d\'acquisition')" for="p-acq" error="form.acquired_on"><x-input id="p-acq" type="date" wire:model="form.acquired_on" /></x-field>
            <x-field :label="__('1re année imposable')" for="p-start" error="form.tax_start_year" hint="Vide = année d'acquisition (min. 2020).">
                <x-input id="p-start" type="number" min="1990" wire:model="form.tax_start_year" />
            </x-field>
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <x-btn variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-btn>
            <x-btn type="submit" icon="check">{{ __('Enregistrer') }}</x-btn>
        </div>
    </form>
</x-panel>
