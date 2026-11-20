@php
    use App\Support\Format;
    use App\Domain\Regie\Services\Ledger;
    $canValidate = $this->allowed('paiements.valider');
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            @if ($this->allowed('recettes.ajouter'))<x-btn icon="pen-line" :href="route('regie.saisie')">{{ __('Saisir des recettes') }}</x-btn>@endif
        </x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-3" aria-label="{{ __('Indicateurs') }}">
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Recettes') }}</p><p class="mt-1 text-2xl font-bold tabular-nums">{{ $count }}</p></div>
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Montant') }}</p><p class="mt-1 text-2xl font-bold tabular-nums">{{ Format::money($total) }} <span class="text-sm">DH</span></p></div>
        @if ($drafts && $canValidate && $count)
            <div class="surface flex items-center justify-center p-4"><x-btn variant="gold" icon="check-check" wire:click="validateAll" wire:confirm="{{ __('Valider toutes les recettes en brouillon affichées ?') }}">{{ __('Tout valider') }} ({{ $count }})</x-btn></div>
        @endif
    </section>

    <div class="grid gap-2 sm:grid-cols-6">
        <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('N°, référence, partie versante…')" :aria-label="__('Rechercher')" class="sm:col-span-2" />
        <x-input type="date" wire:model.live="from" :aria-label="__('Du')" />
        <x-input type="date" wire:model.live="to" :aria-label="__('Au')" />
        <x-select wire:model.live="rubric" :aria-label="__('Rubrique')"><option value="">{{ __('Toutes les rubriques') }}</option>@foreach ($rubrics as $r)<option value="{{ $r->id }}">{{ $r->code }} · {{ $r->label }}</option>@endforeach</x-select>
        <x-select wire:model.live="mode" :aria-label="__('Mode de paiement')"><option value="">{{ __('Tous les modes') }}</option>@foreach (Ledger::ENTRY_MODES as $m)<option value="{{ $m }}">{{ tr(Ledger::modeLabel($m)) }}</option>@endforeach</x-select>
        @unless ($drafts)
            <x-select wire:model.live="status" :aria-label="__('État')" class="sm:col-span-2"><option value="valide">{{ __('Validées') }}</option><option value="annule">{{ __('Annulées') }}</option><option value="">{{ __('Toutes') }}</option></x-select>
        @endunless
    </div>

    @if ($drafts && $canValidate && $selected)
        <div class="material sticky top-3 z-10 flex items-center justify-between gap-2 rounded-full px-4 py-2" style="box-shadow: var(--shadow-md)">
            <span class="text-sm">{{ count($selected) }} {{ __('sélectionnée(s)') }}</span>
            <x-btn variant="gold" size="sm" icon="check" wire:click="validateSelected">{{ __('Valider la sélection') }}</x-btn>
        </div>
    @endif

    <x-card :padding="false">
        <ul class="divide-y divide-line text-sm">
            @forelse ($rows as $e)
                <li class="flex flex-wrap items-center gap-3 px-4 py-3 {{ $e->cancelled_at ? 'opacity-60' : '' }}" wire:key="r-{{ $e->id }}">
                    @if ($drafts && $canValidate)<input type="checkbox" value="{{ $e->id }}" wire:model.live="selected" class="h-4 w-4 rounded border-line text-gold" aria-label="{{ __('Sélectionner') }} {{ $e->number }}">@endif
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2"><span class="font-mono text-xs font-bold">{{ $e->number }}</span>
                            <span class="font-semibold">{{ $e->rubric?->code }} · {{ $e->rubric?->label }}</span>
                            @if ($e->source === 'decharge')<x-badge color="blue">{{ __('Décharge de tickets') }}</x-badge>@endif</p>
                        <p class="text-xs text-muted">{{ $e->entry_date->format('d/m/Y') }} · {{ tr(Ledger::modeLabel($e->payment_mode)) }}
                            @if ($e->quantity) · {{ rtrim(rtrim(number_format((float) $e->quantity, 2, ',', ' '), '0'), ',') }} × {{ Format::money($e->unit_price) }}@endif
                            @if ($e->reference) · {{ __('Réf.') }} {{ $e->reference }}@endif @if ($e->payer) · {{ $e->payer }}@endif @if ($e->description) · {{ $e->description }}@endif</p>
                        <p class="text-xs text-muted">{{ __('Saisie par') }} {{ $e->author?->name ?? '—' }}@if ($e->validator) · {{ __('validée par') }} {{ $e->validator->name }} {{ $e->validated_at?->format('d/m/Y H:i') }}@endif
                            @if ($e->cancelled_at) · <span class="text-red-600">{{ __('Annulée') }} : {{ $e->cancellation_reason }}</span>@endif</p>
                    </div>
                    <span class="w-28 text-end font-semibold tabular-nums">{{ Format::money($e->amount) }}</span>
                    @if (! $e->cancelled_at)
                        @if ($e->status === 'brouillon')
                            @if ($this->allowed('recettes.ajouter'))<x-btn variant="ghost" size="sm" icon="pencil" wire:click="openEdit({{ $e->id }})" :aria-label="__('Modifier')" />@endif
                            @if ($canValidate)<x-btn variant="secondary" size="sm" icon="check" wire:click="validateOne({{ $e->id }})">{{ __('Valider') }}</x-btn>@endif
                        @endif
                        @if ($e->status === 'brouillon' ? $this->allowed('recettes.ajouter') : $canValidate)<x-btn variant="ghost" size="sm" icon="ban" wire:click="askCancel({{ $e->id }})" :aria-label="__('Annuler')" />@endif
                    @endif
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="inbox" :title="$drafts ? __('Aucune recette en attente de validation') : __('Aucune recette pour ces critères')" /></li>
            @endforelse
        </ul>
        <div class="border-t border-line px-4 py-2">{{ $rows->links() }}</div>
    </x-card>

    <x-panel show="showEdit" title="Modifier le brouillon">
        <form wire:submit="saveEdit" class="space-y-3">
            <x-field :label="__('Rubrique')" for="ed-r" error="edit.rubric_id"><x-select id="ed-r" wire:model="edit.rubric_id">@foreach ($rubrics as $r)<option value="{{ $r->id }}">{{ $r->code }} · {{ $r->label }}</option>@endforeach</x-select></x-field>
            <div class="grid grid-cols-2 gap-3">
                <x-field :label="__('Mode de paiement')" for="ed-m" error="edit.payment_mode"><x-select id="ed-m" wire:model="edit.payment_mode">@foreach (Ledger::ENTRY_MODES as $m)<option value="{{ $m }}">{{ tr(Ledger::modeLabel($m)) }}</option>@endforeach</x-select></x-field>
                <x-field :label="__('Montant')" for="ed-a" error="edit.amount"><x-input id="ed-a" type="number" step="0.01" min="0" wire:model="edit.amount" /></x-field>
                <x-field :label="__('Quantité')" for="ed-q"><x-input id="ed-q" type="number" step="0.01" min="0" wire:model="edit.quantity" /></x-field>
                <x-field :label="__('Prix unitaire')" for="ed-u"><x-input id="ed-u" type="number" step="0.01" min="0" wire:model="edit.unit_price" /></x-field>
            </div>
            <x-field :label="__('Référence')" for="ed-ref" error="edit.reference"><x-input id="ed-ref" wire:model="edit.reference" /></x-field>
            <x-field :label="__('Partie versante')" for="ed-p"><x-input id="ed-p" wire:model="edit.payer" /></x-field>
            <x-field :label="__('Observation')" for="ed-d"><x-input id="ed-d" wire:model="edit.description" /></x-field>
            @error('entry')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('showEdit', false)">{{ __('Annuler') }}</x-btn><x-btn type="submit" variant="gold" icon="check">{{ __('Enregistrer') }}</x-btn></div>
        </form>
    </x-panel>

    @include('livewire.regie.partials.cancel-dialog', ['title' => __('Annuler la recette')])
</div>
