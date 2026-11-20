<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        @if ($this->canWrite())
            <x-slot:actions><x-btn icon="plus" wire:click="create">{{ __('Nouvelle rubrique') }}</x-btn></x-slot:actions>
        @endif
    </x-page-header>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-start text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr>
                        <th class="px-4 py-2.5 text-start">{{ __('Module') }}</th>
                        <th class="px-4 py-2.5 text-start">{{ __('Code budgétaire') }}</th>
                        <th class="px-4 py-2.5 text-start">{{ __('Libellé') }}</th>
                        <th class="px-4 py-2.5 text-end">{{ __('Tarifs') }}</th>
                        <th class="px-4 py-2.5 text-start">{{ __('Statut') }}</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($rubrics as $r)
                        <tr wire:key="rub-{{ $r->id }}" @class(['opacity-60' => ! $r->is_active])>
                            <td class="px-4 py-3 font-mono text-xs font-semibold text-ink">{{ $r->module }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $r->code ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-ink">{{ $r->label }}</p>
                                @if ($r->label_ar)<p class="font-ar text-xs text-muted" lang="ar" dir="rtl">{{ $r->label_ar }}</p>@endif
                                <p class="mt-0.5 flex flex-wrap gap-1"><x-badge>{{ tr(\App\Domain\Regie\Services\Ledger::familyLabel($r->family)) }}</x-badge>@if ($r->manual_entry)<x-badge color="gold">{{ __('Saisie à la régie') }}</x-badge>@endif @if ($r->special_tax)<x-badge color="orange">{{ __('Taxe spéciale') }}</x-badge>@endif</p>
                            </td>
                            <td class="px-4 py-3 text-end tabular-nums">{{ $r->tariffs_count }}</td>
                            <td class="px-4 py-3">
                                @if ($r->is_active)<x-badge color="green">{{ __('Active') }}</x-badge>@else<x-badge>{{ __('Inactive') }}</x-badge>@endif
                            </td>
                            <td class="px-4 py-3 text-end">
                                @if ($this->canWrite())
                                    <div class="flex justify-end gap-1">
                                        <x-btn variant="ghost" size="sm" icon="pencil" wire:click="edit({{ $r->id }})">{{ __('Modifier') }}</x-btn>
                                        <x-btn variant="ghost" size="sm" wire:click="toggle({{ $r->id }})">{{ $r->is_active ? 'Désactiver' : 'Activer' }}</x-btn>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-4"><x-empty-state icon="list" :title="__('Aucune rubrique')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-panel show="showForm" :title="$editingId ? 'Modifier la rubrique' : 'Nouvelle rubrique'">
        <form wire:submit="save" class="space-y-4">
            <x-field :label="__('Code module')" for="module" error="module" :hint="__('Ex. TNB, DEBITS_BOISSONS — fixé à la création.')">
                <x-input id="module" wire:model="module" :disabled="(bool) $editingId" class="font-mono uppercase" />
            </x-field>
            <x-field :label="__('Code budgétaire')" for="code" error="code"><x-input id="code" wire:model="code" class="font-mono" /></x-field>
            <x-field :label="__('Libellé')" for="label" error="label"><x-input id="label" wire:model="label" /></x-field>
            <x-field :label="__('Libellé arabe')" for="label_ar" error="label_ar"><x-input id="label_ar" wire:model="label_ar" dir="rtl" lang="ar" class="font-ar" /></x-field>
            <x-field :label="__('Description')" for="description" error="description"><x-textarea id="description" wire:model="description" /></x-field>
            <x-field :label="__('Famille (récapitulatif comptable)')" for="family" error="family"><x-select id="family" wire:model="family">@foreach (\App\Domain\Regie\Services\Ledger::FAMILIES as $k => $l)<option value="{{ $k }}">{{ tr($l) }}</option>@endforeach</x-select></x-field>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="manual_entry" class="h-4 w-4 rounded border-line text-gold"> {{ __('Recette saisie directement à la régie (Saisie des recettes)') }}</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="special_tax" class="h-4 w-4 rounded border-line text-gold"> {{ __('Taxe spéciale (bordereau de la taxe d’abattage)') }}</label>
            <div class="flex justify-end gap-2 pt-2">
                <x-btn variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-btn>
                <x-btn type="submit" icon="check">{{ __('Enregistrer') }}</x-btn>
            </div>
        </form>
    </x-panel>
</div>
