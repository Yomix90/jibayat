@php
    use App\Support\Format;
    use App\Domain\Regie\Services\Ledger;
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>@if ($this->allowed('recettes.modifier'))<x-btn icon="plus" wire:click="openForm">{{ __('Nouveau bordereau') }}</x-btn>@endif</x-slot:actions>
    </x-page-header>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-3" aria-label="{{ __('Indicateurs') }}">
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Encaisse à verser') }}</p><p class="mt-1 text-lg font-bold tabular-nums">{{ Format::money($balance) }} DH</p></div>
        <div class="surface p-4"><p class="text-xs font-semibold text-muted">{{ __('Versements non accusés') }}</p><p class="mt-1 text-lg font-bold tabular-nums {{ $pending ? 'text-[#8a5a12]' : '' }}">{{ $pending }}</p></div>
    </section>

    <x-card :padding="false">
        <ul class="divide-y divide-line text-sm">
            @forelse ($rows as $r)
                <li class="flex flex-wrap items-center gap-3 px-4 py-3 {{ $r->cancelled_at ? 'opacity-60' : '' }}" wire:key="v-{{ $r->id }}">
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2"><span class="font-mono text-xs font-bold">{{ $r->number }}</span><span class="font-semibold">{{ $r->accountant }}</span></p>
                        <p class="text-xs text-muted">{{ __('Versé le') }} {{ $r->remitted_on->format('d/m/Y') }} · {{ __('recettes du') }} {{ $r->period_from?->format('d/m/Y') }} {{ __('au') }} {{ $r->period_to?->format('d/m/Y') }} · {{ tr(Ledger::modeLabel($r->payment_mode)) }}@if ($r->bank_reference) · {{ $r->bank_reference }}@endif</p>
                        @if ($r->acknowledged_on)<p class="text-xs text-green-700">{{ __('Accusé le') }} {{ $r->acknowledged_on->format('d/m/Y') }}@if ($r->acknowledgement_ref) · {{ $r->acknowledgement_ref }}@endif</p>@endif
                        @if ($r->cancelled_at)<p class="text-xs text-red-600">{{ __('Annulé') }} : {{ $r->cancellation_reason }}</p>@endif
                    </div>
                    @if (! $r->cancelled_at)@if ($r->status === 'accuse')<x-badge color="green">{{ __('Accusé') }}</x-badge>@else<x-badge color="gold">{{ __('En attente d’accusé') }}</x-badge>@endif @endif
                    <span class="w-32 text-end font-semibold tabular-nums">{{ Format::money($r->amount) }}</span>
                    <x-btn variant="secondary" size="sm" icon="printer" :href="route('regie.imprimer.versement', $r)" target="_blank">{{ __('Imprimer') }}</x-btn>
                    @if (! $r->cancelled_at)
                        @if ($r->status !== 'accuse' && $this->allowed('recettes.modifier'))<x-btn variant="ghost" size="sm" icon="stamp" wire:click="askAck({{ $r->id }})">{{ __('Accusé') }}</x-btn>@endif
                        @if ($this->allowed('paiements.valider'))<x-btn variant="ghost" size="sm" icon="ban" wire:click="askCancel({{ $r->id }})" :aria-label="__('Annuler')" />@endif
                    @endif
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="coins" :title="__('Aucun bordereau de versement')" /></li>
            @endforelse
        </ul>
        <div class="border-t border-line px-4 py-2">{{ $rows->links() }}</div>
    </x-card>

    <x-panel show="showForm" title="Bordereau de versement au comptable" max-width="max-w-xl">
        <form wire:submit="save" class="space-y-3">
            <div class="grid grid-cols-3 gap-3">
                <x-field :label="__('Recettes du')" for="v-from" error="form.period_from"><x-input id="v-from" type="date" wire:model.live="form.period_from" /></x-field>
                <x-field :label="__('au')" for="v-to" error="form.period_to"><x-input id="v-to" type="date" wire:model.live="form.period_to" /></x-field>
                <x-field :label="__('Date du versement')" for="v-on" error="form.remitted_on"><x-input id="v-on" type="date" wire:model="form.remitted_on" /></x-field>
            </div>
            @if ($proposal)
                <div class="rounded-xl bg-bg p-3 text-xs">
                    @foreach ($proposal['lines'] as $l)<div class="flex justify-between py-0.5"><span>{{ $l['code'] }} · {{ $l['label'] }}</span><span class="tabular-nums">{{ Format::money($l['amount']) }}</span></div>@endforeach
                    <div class="mt-1 flex justify-between border-t border-line pt-1 font-semibold"><span>{{ __('Recettes de la période') }}</span><span class="tabular-nums">{{ Format::money($proposal['total']) }}</span></div>
                    @if ($proposal['already'] > 0)<div class="flex justify-between text-muted"><span>{{ __('Déjà versé sur la période') }}</span><span class="tabular-nums">{{ Format::money($proposal['already']) }}</span></div>@endif
                    <div class="flex justify-between text-muted"><span>{{ __('Solde de caisse à la fin de la période') }}</span><span class="tabular-nums">{{ Format::money($proposal['balance']) }}</span></div>
                </div>
            @endif
            <div class="grid grid-cols-2 gap-3">
                <x-field :label="__('Montant versé (DH)')" for="v-amt" error="form.amount"><x-input id="v-amt" type="number" step="0.01" min="0" wire:model="form.amount" /></x-field>
                <x-field :label="__('Mode')" for="v-mode" error="form.payment_mode"><x-select id="v-mode" wire:model="form.payment_mode"><option value="especes">{{ __('Espèces') }}</option><option value="cheque">{{ __('Chèque') }}</option><option value="virement">{{ __('Virement') }}</option></x-select></x-field>
            </div>
            <x-field :label="__('Comptable de rattachement')" for="v-acc" error="form.accountant"><x-input id="v-acc" wire:model="form.accountant" /></x-field>
            <x-field :label="__('Référence bancaire / n° de quittance du comptable')" for="v-ref"><x-input id="v-ref" wire:model="form.bank_reference" /></x-field>
            <x-field :label="__('Observation')" for="v-notes"><x-input id="v-notes" wire:model="form.notes" /></x-field>
            <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('showForm', false)">{{ __('Annuler') }}</x-btn><x-btn type="submit" variant="gold" icon="check">{{ __('Enregistrer le bordereau') }}</x-btn></div>
        </form>
    </x-panel>

    @if ($ackId)
        <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="{{ __('Accusé du comptable') }}">
            <div class="absolute inset-0 bg-navy/40 backdrop-blur-[2px]" wire:click="$set('ackId', null)"></div>
            <form wire:submit="confirmAck" class="relative w-full max-w-md space-y-3 rounded-t-[24px] bg-white p-5 sm:rounded-[24px]" style="box-shadow: var(--shadow-lg)">
                <h2 class="text-[17px] font-semibold">{{ __('Accusé du comptable') }}</h2>
                <x-field :label="__('Date de l’accusé')" for="a-on" error="ackOn"><x-input id="a-on" type="date" wire:model="ackOn" /></x-field>
                <x-field :label="__('Référence (n° de quittance du comptable)')" for="a-ref" error="ackRef"><x-input id="a-ref" wire:model="ackRef" /></x-field>
                <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('ackId', null)">{{ __('Retour') }}</x-btn><x-btn type="submit" variant="gold" icon="check">{{ __('Enregistrer') }}</x-btn></div>
            </form>
        </div>
    @endif

    @include('livewire.regie.partials.cancel-dialog', ['title' => __('Annuler le bordereau')])
</div>
