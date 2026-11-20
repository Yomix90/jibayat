{{-- Champs du contribuable (form.*) --}}
<div class="space-y-4">
    <div class="inline-flex rounded-full border border-line bg-white p-0.5 text-sm font-semibold" role="radiogroup" aria-label="{{ __('Type de personne') }}">
        @foreach (['physique' => 'Personne physique', 'morale' => 'Personne morale'] as $v => $l)
            <label class="cursor-pointer rounded-full px-4 py-1.5 {{ ($form['person_type'] ?? '') === $v ? 'bg-navy text-white' : 'text-muted' }}">
                <input type="radio" class="sr-only" value="{{ $v }}" wire:model.live="form.person_type"> {{ tr($l) }}
            </label>
        @endforeach
    </div>
    @if (($form['person_type'] ?? '') === 'morale')
        <div class="grid gap-3 sm:grid-cols-2">
            <x-field :label="__('Raison sociale')" for="t-company" error="form.company_name"><x-input id="t-company" wire:model="form.company_name" /></x-field>
            <x-field label="الاسم التجاري" for="t-company-ar" error="form.company_name_ar"><x-input id="t-company-ar" wire:model="form.company_name_ar" dir="rtl" /></x-field>
            <x-field label="ICE" for="t-ice" error="form.ice"><x-input id="t-ice" wire:model="form.ice" inputmode="numeric" /></x-field>
            <x-field label="RC" for="t-rc" error="form.rc"><x-input id="t-rc" wire:model="form.rc" /></x-field>
        </div>
        <p class="text-xs font-bold uppercase tracking-[.06em] text-muted">{{ __('Représentant') }}</p>
    @endif
    <div class="grid gap-3 sm:grid-cols-2">
        <x-field :label="__('Nom')" for="t-last" error="form.last_name"><x-input id="t-last" wire:model="form.last_name" /></x-field>
        <x-field :label="__('Prénom')" for="t-first" error="form.first_name"><x-input id="t-first" wire:model="form.first_name" /></x-field>
        <x-field label="النسب" for="t-last-ar" error="form.last_name_ar"><x-input id="t-last-ar" wire:model="form.last_name_ar" dir="rtl" /></x-field>
        <x-field label="الاسم" for="t-first-ar" error="form.first_name_ar"><x-input id="t-first-ar" wire:model="form.first_name_ar" dir="rtl" /></x-field>
        <x-field label="CIN" for="t-cin" error="form.cin"><x-input id="t-cin" wire:model.blur="form.cin" class="uppercase" /></x-field>
        <x-field :label="__('Téléphone')" for="t-phone" error="form.phone"><x-input id="t-phone" type="tel" wire:model="form.phone" /></x-field>
        <x-field :label="__('Adresse')" for="t-address" error="form.address"><x-input id="t-address" wire:model="form.address" /></x-field>
        <x-field label="العنوان" for="t-address-ar" error="form.address_ar"><x-input id="t-address-ar" wire:model="form.address_ar" dir="rtl" /></x-field>
        <x-field :label="__('Ville')" for="t-city" error="form.city"><x-input id="t-city" wire:model="form.city" /></x-field>
        <x-field :label="__('Code postal')" for="t-zip" error="form.postal_code"><x-input id="t-zip" wire:model="form.postal_code" inputmode="numeric" /></x-field>
        <x-field :label="__('E-mail')" for="t-email" error="form.email" class="sm:col-span-2"><x-input id="t-email" type="email" wire:model="form.email" /></x-field>
    </div>
</div>
