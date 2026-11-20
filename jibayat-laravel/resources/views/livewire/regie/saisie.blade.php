@php
    use App\Support\Format;
    use App\Domain\Regie\Services\Ledger;
    $ar = app()->isLocale('ar');
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-input type="date" wire:model.live="date" max="{{ today()->toDateString() }}" :aria-label="__('Date des recettes')" class="!w-44" />
            <x-btn variant="secondary" icon="list-checks" :href="route('regie.brouillons')">{{ __('Brouillons') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    @if ($locked)
        <div class="surface flex items-center gap-3 border-red-200 bg-red-50 p-4 text-sm text-red-700"><x-svg-icon name="lock" class="h-5 w-5" />{{ __('Ce mois est clôturé : aucune recette ne peut y être saisie.') }}</div>
    @endif

    <form wire:submit="save" class="space-y-3">
        <x-card :padding="false">
            <x-slot:title>{{ __('Recettes du') }} {{ $date ? \Carbon\Carbon::parse($date)->format('d/m/Y') : '' }}</x-slot:title>
            <x-slot:actions><span class="text-xs text-muted">{{ count($lines) }} {{ __('ligne(s)') }} · <strong class="tabular-nums text-ink">{{ Format::money($total) }} DH</strong></span></x-slot:actions>
            <div class="divide-y divide-line">
                @foreach ($lines as $i => $l)
                    <div class="grid gap-2 p-3 sm:grid-cols-12" wire:key="line-{{ $i }}">
                        <div class="sm:col-span-4">
                            <x-select wire:model.live="lines.{{ $i }}.rubric_id" :aria-label="__('Rubrique')">
                                <option value="">— {{ __('Rubrique') }} —</option>
                                @foreach ($rubrics as $r)<option value="{{ $r->id }}">{{ $r->code }} · {{ $ar && $r->label_ar ? $r->label_ar : $r->label }}</option>@endforeach
                            </x-select>
                            @error("lines.$i.rubric_id")<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <x-select wire:model.live="lines.{{ $i }}.payment_mode" :aria-label="__('Mode de paiement')">@foreach ($modes as $k => $m)<option value="{{ $k }}">{{ tr($m) }}</option>@endforeach</x-select>
                            @error("lines.$i.payment_mode")<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="grid grid-cols-3 gap-2 sm:col-span-4">
                            <x-input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="lines.{{ $i }}.quantity" :placeholder="__('Qté')" :aria-label="__('Quantité')" />
                            <x-input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="lines.{{ $i }}.unit_price" :placeholder="__('P.U.')" :aria-label="__('Prix unitaire')" />
                            @if (is_numeric($l['quantity']) && is_numeric($l['unit_price']))
                                <p class="self-center text-end text-sm font-semibold tabular-nums">{{ Format::money((float) $l['quantity'] * (float) $l['unit_price']) }}</p>
                            @else
                                <x-input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="lines.{{ $i }}.amount" :placeholder="__('Montant')" :aria-label="__('Montant')" class="text-end" />
                            @endif
                            @error("lines.$i.amount")<p class="col-span-3 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex items-start justify-end sm:col-span-2">
                            <x-btn variant="ghost" size="sm" icon="trash-2" wire:click="removeLine({{ $i }})" :aria-label="__('Retirer la ligne')" />
                        </div>
                        <div class="sm:col-span-3"><x-input wire:model="lines.{{ $i }}.reference" :placeholder="__('Référence (n° quittance, chèque…)')" :aria-label="__('Référence')" />
                            @error("lines.$i.reference")<p class="text-xs text-red-600">{{ $message }}</p>@enderror</div>
                        <div class="sm:col-span-3"><x-input wire:model="lines.{{ $i }}.payer" :placeholder="__('Partie versante')" :aria-label="__('Partie versante')" /></div>
                        <div class="sm:col-span-6"><x-input wire:model="lines.{{ $i }}.description" :placeholder="__('Observation')" :aria-label="__('Observation')" /></div>
                    </div>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line p-3">
                <x-btn variant="secondary" size="sm" icon="plus" wire:click="addLine">{{ __('Ajouter une ligne') }}</x-btn>
                <p class="text-xs text-muted">{{ __('Quantité × prix unitaire calcule le montant (tickets, droits à l’unité).') }}</p>
            </div>
        </x-card>
        @error('lines')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        @error('date')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        @unless ($locked)
            <div class="material sticky bottom-3 z-10 flex flex-wrap items-center justify-end gap-2 rounded-full px-4 py-2" style="box-shadow: var(--shadow-md)">
                <span class="me-auto text-sm font-semibold tabular-nums">{{ Format::money($total) }} DH</span>
                <x-btn type="submit" variant="secondary" icon="save" wire:loading.attr="disabled">{{ __('Enregistrer en brouillon') }}</x-btn>
                @if ($this->allowed('paiements.valider'))<x-btn variant="gold" icon="check" wire:click="saveAndValidate" wire:loading.attr="disabled">{{ __('Enregistrer et valider') }}</x-btn>@endif
            </div>
        @endunless
    </form>

    <x-card :padding="false">
        <x-slot:title>{{ __('Saisies de la journée') }}</x-slot:title>
        <x-slot:actions><span class="text-xs text-muted">{{ $day->whereNull('cancelled_at')->count() }} · {{ Format::money($day->whereNull('cancelled_at')->sum('amount')) }} DH</span></x-slot:actions>
        <ul class="divide-y divide-line text-sm">
            @forelse ($day as $e)
                <li class="flex flex-wrap items-center gap-3 px-4 py-2.5 {{ $e->cancelled_at ? 'opacity-60' : '' }}" wire:key="e-{{ $e->id }}">
                    <span class="font-mono text-xs font-bold">{{ $e->number }}</span>
                    <span class="min-w-0 flex-1 truncate">{{ $e->rubric?->code }} · {{ $e->rubric?->label }} @if ($e->reference)<span class="text-muted">· {{ $e->reference }}</span>@endif @if ($e->payer)<span class="text-muted">· {{ $e->payer }}</span>@endif</span>
                    <x-badge>{{ tr(Ledger::modeLabel($e->payment_mode)) }}</x-badge>
                    @if ($e->cancelled_at)<x-badge color="red">{{ __('Annulée') }}</x-badge>@elseif ($e->status === 'valide')<x-badge color="green">{{ __('Validée') }}</x-badge>@else<x-badge color="gold">{{ __('Brouillon') }}</x-badge>@endif
                    <span class="w-28 text-end font-semibold tabular-nums">{{ Format::money($e->amount) }}</span>
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="pen-line" :title="__('Aucune recette saisie ce jour')" /></li>
            @endforelse
        </ul>
    </x-card>
</div>
