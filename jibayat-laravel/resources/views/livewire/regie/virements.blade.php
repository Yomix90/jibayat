@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            @include('livewire.regie.partials.period', ['yearOnly' => true])
            @if ($this->allowed('recettes.ajouter'))<x-btn icon="plus" :href="route('regie.saisie')">{{ __('Saisir un virement') }}</x-btn>@endif
        </x-slot:actions>
    </x-page-header>

    <p class="text-sm text-muted">{{ __('Saisissez la recette avec le mode « Virement Ministère » (référence de l’ordre de virement obligatoire). Elle ne passe pas par la caisse : elle n’entre ni dans le livre de caisse ni dans l’état mensuel (Modèle 10).') }}</p>

    <x-card :padding="false">
        <ul class="divide-y divide-line text-sm">
            @forelse ($rows as $e)
                <li class="flex flex-wrap items-center gap-3 px-4 py-3 {{ $e->cancelled_at ? 'opacity-60' : '' }}" wire:key="w-{{ $e->id }}">
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2"><span class="font-mono text-xs font-bold">{{ $e->number }}</span><span class="font-semibold">{{ $e->payer ?: __('Ministère') }}</span></p>
                        <p class="text-xs text-muted">{{ $e->entry_date->format('d/m/Y') }} · {{ $e->rubric?->code }} {{ $e->rubric?->label }} · {{ __('Réf.') }} {{ $e->reference }}@if ($e->description) · {{ $e->description }}@endif</p>
                        @if ($e->cancelled_at)<p class="text-xs text-red-600">{{ __('Annulée') }} : {{ $e->cancellation_reason }}</p>@endif
                    </div>
                    @if ($e->status === 'brouillon' && ! $e->cancelled_at)<x-badge color="gold">{{ __('Brouillon') }}</x-badge>@endif
                    <span class="w-32 text-end font-semibold tabular-nums">{{ Format::money($e->amount) }}</span>
                    @if (! $e->cancelled_at && $e->status === 'valide')<x-btn variant="secondary" size="sm" icon="printer" :href="route('regie.imprimer.virement', $e)" target="_blank">{{ __('Autorisation') }}</x-btn>@endif
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="arrow-right-left" :title="__('Aucun virement du Ministère cette année')" /></li>
            @endforelse
        </ul>
    </x-card>
</div>
