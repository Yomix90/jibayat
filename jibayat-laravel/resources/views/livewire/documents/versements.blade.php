@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />

    <x-card :title="__('1. Versements des employés (carnets affectés)')" :padding="false">
        <ul class="divide-y divide-line text-sm">
            @forelse ($assigned as $b)
                <li class="flex flex-wrap items-center gap-3 px-4 py-2.5" wire:key="a-{{ $b->id }}">
                    <span class="font-mono text-xs font-bold">{{ $b->number }}</span>
                    <span class="min-w-0 flex-1 truncate text-xs text-muted">{{ $b->stampValue?->label }} · {{ $b->employee?->last_name }} {{ $b->employee?->first_name }} · {{ $b->service?->name }} · depuis le {{ $b->assigned_on?->format('d/m/Y') }}</span>
                    <span class="text-xs font-semibold tabular-nums">{{ Format::money((float) $b->stampValue?->unit_value * (int) $b->stampValue?->units_per_book) }} DH</span>
                    @if ($this->allowed('paiements.valider'))<x-btn variant="gold" size="sm" icon="hand-coins" wire:click="openPayment({{ $b->id }})">{{ __('Versement') }}</x-btn>@endif
                </li>
            @empty
                <li class="px-4 py-3 text-xs text-muted">{{ __('Aucun carnet affecté en attente de versement.') }}</li>
            @endforelse
        </ul>
    </x-card>

    <form wire:submit="createRemittance">
        <x-card :padding="false">
            <x-slot:title>{{ __('2. Bordereau de versement au percepteur') }}</x-slot:title>
            <x-slot:actions><span class="text-xs text-muted">{{ __('En caisse :') }} <strong class="tabular-nums text-ink">{{ Format::money($pendingTotal) }} DH</strong></span></x-slot:actions>
            <ul class="divide-y divide-line text-sm">
                @forelse ($pending as $b)
                    <li wire:key="p-{{ $b->id }}">
                        <label class="flex cursor-pointer flex-wrap items-center gap-3 px-4 py-2.5 hover:bg-bg">
                            <input type="checkbox" value="{{ $b->id }}" wire:model.live="selected" class="rounded border-line text-accent focus:ring-gold">
                            <span class="font-mono text-xs font-bold">{{ $b->number }}</span>
                            <span class="min-w-0 flex-1 truncate text-xs text-muted">{{ $b->employee?->last_name }} {{ $b->employee?->first_name }} · versé le {{ $b->employee_paid_on?->format('d/m/Y') }}</span>
                            <span class="font-semibold tabular-nums">{{ Format::money($b->amount_paid) }} DH</span>
                            @if ((float) $b->difference != 0)<x-badge :color="(float) $b->difference < 0 ? 'red' : 'green'">écart {{ (float) $b->difference > 0 ? '+' : '' }}{{ Format::money($b->difference) }}</x-badge>@endif
                        </label>
                    </li>
                @empty
                    <li class="px-4 py-3 text-xs text-muted">{{ __('Aucun carnet versé en attente de bordereau.') }}</li>
                @endforelse
            </ul>
            @if ($pending->count() && $this->allowed('paiements.valider'))
                <div class="grid items-end gap-2 border-t border-line p-4 sm:grid-cols-[1fr_1fr_auto]">
                    <x-field label="N° du bordereau (registre du percepteur ; vide = automatique)" for="r-number" error="remittanceNumber"><x-input id="r-number" wire:model="remittanceNumber" /></x-field>
                    <x-field :label="__('Date du versement')" for="r-date" error="remittedOn"><x-input id="r-date" type="date" wire:model="remittedOn" /></x-field>
                    <x-btn type="submit" variant="gold" icon="file-check" :disabled="! count($selected)">Créer le bordereau · {{ Format::money($selectedTotal) }} DH</x-btn>
                </div>
                @error('selected')<p class="px-4 pb-3 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                @error('books')<p class="px-4 pb-3 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
            @endif
        </x-card>
    </form>

    <x-card :title="__('Bordereaux')" :padding="false">
        <ul class="divide-y divide-line text-sm">
            @forelse ($remittances as $r)
                <li class="flex flex-wrap items-center gap-3 px-4 py-2.5" wire:key="r-{{ $r->id }}">
                    <span class="font-mono text-xs font-bold">{{ $r->number }}</span>
                    <span class="flex-1 text-xs text-muted">Versé le {{ $r->remitted_on?->format('d/m/Y') }} · {{ $r->books_count }} carnet(s)</span>
                    <span class="font-semibold tabular-nums">{{ Format::money($r->total_amount) }} DH</span>
                    <x-btn variant="secondary" size="sm" icon="printer" :href="route('documents.bordereau-imprimer', $r)" target="_blank">{{ __('Imprimer') }}</x-btn>
                </li>
            @empty
                <li class="px-4 py-3 text-xs text-muted">{{ __('Aucun bordereau.') }}</li>
            @endforelse
        </ul>
    </x-card>

    @if ($payId)
        <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="{{ __('Versement de l\'employé') }}">
            <div class="absolute inset-0 bg-navy/40 backdrop-blur-[2px]" wire:click="$set('payId', null)"></div>
            <div class="relative w-full max-w-md rounded-t-[24px] bg-white p-5 sm:rounded-[24px]" style="box-shadow: var(--shadow-lg)">
                <h2 class="mb-3 text-[17px] font-semibold">{{ __('Versement de l\'employé') }}</h2>
                <form wire:submit="savePayment" class="space-y-3">
                    <div class="grid grid-cols-2 gap-2">
                        <x-field :label="__('Montant versé (DH)')" for="v-amount" error="amount"><x-input id="v-amount" type="number" step="0.01" min="0" wire:model="amount" /></x-field>
                        <x-field :label="__('Date')" for="v-date" error="paidOn"><x-input id="v-date" type="date" wire:model="paidOn" /></x-field>
                    </div>
                    <x-field :label="__('Observation')" for="v-obs" error="observation"><x-input id="v-obs" wire:model="observation" /></x-field>
                    <p class="text-xs text-muted">{{ __('Un montant différent de la valeur du carnet est enregistré comme écart de caisse.') }}</p>
                    <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('payId', null)">{{ __('Fermer') }}</x-btn><x-btn type="submit" icon="check">{{ __('Enregistrer') }}</x-btn></div>
                </form>
            </div>
        </div>
    @endif
</div>
