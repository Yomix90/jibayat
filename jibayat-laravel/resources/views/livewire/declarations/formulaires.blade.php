@php
    use App\Domain\Declarations\Services\DeclarationService;
    $ar = app()->isLocale('ar');
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />
    <p class="max-w-3xl text-sm text-muted">{{ __('Chaque formulaire est bilingue, conforme aux articles de la loi 47-06 modifiée par la loi 07-20. Imprimez-le vierge pour le redevable, ou enregistrez la déclaration pour imprimer un formulaire prérempli.') }}</p>
    @foreach (DeclarationService::TYPES as $module => $types)
        <section aria-label="{{ $ar ? DeclarationService::MODULES[$module]['ar'] : DeclarationService::MODULES[$module]['fr'] }}">
            <x-section-title :count="count($types)">{{ $ar ? DeclarationService::MODULES[$module]['ar'] : DeclarationService::MODULES[$module]['fr'] }}</x-section-title>
            <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($types as $k => $t)
                    <div class="surface flex flex-col gap-1 p-4" wire:key="{{ $module }}-{{ $k }}">
                        <div class="flex items-start justify-between gap-2"><p class="font-semibold">{{ $ar ? $t['ar'] : $t['fr'] }}</p><x-badge>{{ $t['art'] === '—' ? __('Interne') : __('Art.').' '.$t['art'] }}</x-badge></div>
                        <p class="text-xs text-muted" @if (! $ar) dir="rtl" @endif>{{ $ar ? $t['fr'] : $t['ar'] }}</p>
                        <p class="text-xs">{{ __($t['hint']) }}</p>
                        <div class="mt-auto flex gap-2 pt-3">
                            <x-btn variant="secondary" size="sm" icon="printer" :href="route('declarations.formulaire', [$module, $k])" target="_blank">{{ __('Formulaire vierge') }}</x-btn>
                            <x-btn variant="ghost" size="sm" icon="file-plus" :href="route('declarations.nouvelle', ['taxe' => $module, 'type' => $k])">{{ __('Enregistrer') }}</x-btn>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
