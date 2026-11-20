<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />

    <x-field :label="__('Pré-remplir pour un établissement (facultatif)')" for="f-outlet">
        <x-select id="f-outlet" wire:model.live="outletId" class="sm:max-w-md">
            <option value="">{{ __('— Formulaire vierge —') }}</option>
            @foreach ($outlets as $o)<option value="{{ $o->id }}">{{ $o->name }} (n° {{ $o->number }})</option>@endforeach
        </x-select>
    </x-field>

    <div class="grid gap-3 sm:grid-cols-3">
        @foreach ([
            ['creation', 'Déclaration de création', 'إقرار الإحداث', 'À déposer dans les 30 jours du début d’activité (art. 67).', 'file-plus'],
            ['ca', "Déclaration du chiffre d'affaires", 'إقرار بالمداخيل — نموذج 6', 'Chaque trimestre, avant la fin du mois suivant (art. 69).', 'receipt'],
            ['cessation', 'Déclaration de cessation', 'إقرار التوقف', 'Dans les 45 jours de l’arrêt de l’activité.', 'circle-x'],
        ] as [$type, $title, $titleAr, $hint, $icon])
            <x-card>
                <div class="flex items-start gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#8B2E3C] text-white"><x-svg-icon :name="$icon" class="h-5 w-5" /></span>
                    <div class="min-w-0">
                        <p class="font-semibold text-ink">{{ tr($title) }}</p>
                        <p class="font-ar text-sm text-muted" lang="ar">{{ $titleAr }}</p>
                        <p class="mt-1 text-xs text-muted">{{ $hint }}</p>
                    </div>
                </div>
                @if ($type === 'ca' && ! $outletId)
                    <p class="mt-3 text-xs text-muted">{{ __('Choisissez un établissement : le formulaire liste ses trimestres à déclarer.') }}</p>
                @else
                    <x-btn variant="secondary" size="sm" icon="printer" class="mt-3" target="_blank"
                           :href="route('boissons.formulaire', array_filter(['type' => $type, 'etablissement' => $outletId ?: null]))">{{ __('Imprimer') }}</x-btn>
                @endif
            </x-card>
        @endforeach
    </div>
</div>
