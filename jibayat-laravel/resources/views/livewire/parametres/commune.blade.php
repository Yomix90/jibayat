<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />

    <form wire:submit="save" class="space-y-6">
        <x-card :title="__('Identité de la commune')">
            @php($disabled = ! $this->canWrite())
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['name', 'Nom de la commune', false], ['name_ar', 'الاسم بالعربية', true],
                    ['president', 'Président', false], ['president_ar', 'الرئيس', true],
                    ['province', 'Province', false], ['province_ar', 'الإقليم', true],
                    ['region', 'Région', false], ['region_ar', 'الجهة', true],
                    ['code', 'Code commune', false],
                ] as [$field, $label, $ar])
                    <x-field :label="$label" :for="'c-'.$field" :error="'form.'.$field" wire:key="c-{{ $field }}">
                        <x-input :id="'c-'.$field" wire:model="form.{{ $field }}" :disabled="$disabled"
                                 :dir="$ar ? 'rtl' : 'ltr'" :lang="$ar ? 'ar' : 'fr'" @class(['font-ar' => $ar]) />
                    </x-field>
                @endforeach
            </div>
        </x-card>

        <x-card :title="__('Aperçu de l\'en-tête officiel')">
            <div class="grid grid-cols-2 gap-4 text-xs">
                <div class="space-y-0.5 font-semibold uppercase">
                    <p>{{ __('Royaume du Maroc') }}</p><p>{{ __('Ministère de l\'Intérieur') }}</p>
                    <p>{{ $form['region'] ?: 'Région' }}</p><p>{{ $form['province'] ?: 'Province' }}</p><p>{{ $form['name'] ?: 'Commune' }}</p>
                </div>
                <div class="font-ar space-y-0.5 text-end font-semibold" dir="rtl" lang="ar">
                    <p>المملكة المغربية</p><p>وزارة الداخلية</p>
                    <p>{{ $form['region_ar'] ?: 'الجهة' }}</p><p>{{ $form['province_ar'] ?: 'الإقليم' }}</p><p>{{ $form['name_ar'] ?: 'الجماعة' }}</p>
                </div>
            </div>
        </x-card>

        @if ($this->canWrite())
            <div class="flex justify-end"><x-btn type="submit" icon="check">{{ __('Enregistrer') }}</x-btn></div>
        @endif
    </form>
</div>
