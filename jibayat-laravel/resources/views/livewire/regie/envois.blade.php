<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>@if ($this->allowed('recettes.modifier'))<x-btn icon="plus" wire:click="openForm">{{ __('Nouvel envoi') }}</x-btn>@endif</x-slot:actions>
    </x-page-header>

    <x-card :padding="false">
        <ul class="divide-y divide-line text-sm">
            @forelse ($rows as $d)
                <li class="flex flex-wrap items-start gap-3 px-4 py-3 {{ $d->cancelled_at ? 'opacity-60' : '' }}" wire:key="e-{{ $d->id }}">
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2"><span class="font-mono text-xs font-bold">{{ $d->number }}</span><span class="font-semibold">{{ $d->recipient }}</span></p>
                        <p class="text-xs text-muted">{{ __('Envoyé le') }} {{ $d->sent_on->format('d/m/Y') }} · {{ count($d->items) }} {{ __('pièce(s)') }}</p>
                        <ul class="mt-1 list-disc ps-5 text-xs text-muted">@foreach ($d->items as $i)<li>{{ $i['label'] }}@if (($i['count'] ?? 1) > 1) ({{ $i['count'] }})@endif</li>@endforeach</ul>
                        @if ($d->acknowledged_on)<p class="mt-1 text-xs text-green-700">{{ __('Reçu le') }} {{ $d->acknowledged_on->format('d/m/Y') }} {{ __('par') }} {{ $d->acknowledged_by_name }}</p>@endif
                        @if ($d->cancelled_at)<p class="text-xs text-red-600">{{ __('Annulé') }} : {{ $d->cancellation_reason }}</p>@endif
                    </div>
                    @if (! $d->cancelled_at)@if ($d->acknowledged_on)<x-badge color="green">{{ __('Reçu') }}</x-badge>@else<x-badge color="gold">{{ __('En attente d’accusé') }}</x-badge>@endif @endif
                    <x-btn variant="secondary" size="sm" icon="printer" :href="route('regie.imprimer.envoi', $d)" target="_blank">{{ __('Imprimer') }}</x-btn>
                    @if (! $d->cancelled_at)
                        @if (! $d->acknowledged_on && $this->allowed('recettes.modifier'))<x-btn variant="ghost" size="sm" icon="stamp" wire:click="askAck({{ $d->id }})">{{ __('Accusé') }}</x-btn>@endif
                        @if ($this->allowed('paiements.valider'))<x-btn variant="ghost" size="sm" icon="ban" wire:click="askCancel({{ $d->id }})" :aria-label="__('Annuler')" />@endif
                    @endif
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="send" :title="__('Aucun envoi au Trésor')" /></li>
            @endforelse
        </ul>
        <div class="border-t border-line px-4 py-2">{{ $rows->links() }}</div>
    </x-card>

    <x-panel show="showForm" title="Bordereau d’envoi au Trésor" max-width="max-w-xl">
        <form wire:submit="save" class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <x-field :label="__('Date d’envoi')" for="e-on" error="sentOn"><x-input id="e-on" type="date" wire:model="sentOn" /></x-field>
                <x-field :label="__('Destinataire (receveur)')" for="e-to" error="recipient"><x-input id="e-to" wire:model="recipient" /></x-field>
            </div>
            <fieldset class="space-y-1">
                <legend class="text-xs font-semibold text-muted">{{ __('Pièces non encore envoyées') }}</legend>
                <div class="max-h-60 space-y-1 overflow-y-auto rounded-xl border border-line p-2">
                    @forelse ($candidates as $c)
                        <label class="flex items-center gap-2 rounded-lg px-2 py-1 text-sm hover:bg-bg"><input type="checkbox" value="{{ $c['type'] }}:{{ $c['ref'] }}" wire:model="chosen" class="h-4 w-4 rounded border-line text-gold">
                            <span class="flex-1">{{ $c['label'] }}</span><x-badge>{{ tr($types[$c['type']] ?? $c['type']) }}</x-badge></label>
                    @empty
                        <p class="p-2 text-xs text-muted">{{ __('Toutes les pièces ont été envoyées.') }}</p>
                    @endforelse
                </div>
            </fieldset>
            <fieldset class="space-y-2">
                <legend class="text-xs font-semibold text-muted">{{ __('Autres pièces') }}</legend>
                @foreach ($extra as $i => $e)
                    <div class="grid grid-cols-6 gap-2" wire:key="x-{{ $i }}"><x-input class="col-span-5" wire:model="extra.{{ $i }}.label" :placeholder="__('Désignation de la pièce')" :aria-label="__('Désignation')" /><x-input type="number" min="1" wire:model="extra.{{ $i }}.count" :aria-label="__('Nombre')" /></div>
                @endforeach
                <x-btn variant="ghost" size="sm" icon="plus" wire:click="addExtra">{{ __('Ajouter une pièce') }}</x-btn>
            </fieldset>
            @error('items')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <x-field :label="__('Observation')" for="e-n"><x-input id="e-n" wire:model="notes" /></x-field>
            <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('showForm', false)">{{ __('Annuler') }}</x-btn><x-btn type="submit" variant="gold" icon="send">{{ __('Enregistrer l’envoi') }}</x-btn></div>
        </form>
    </x-panel>

    @if ($ackId)
        <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="{{ __('Accusé de réception') }}">
            <div class="absolute inset-0 bg-navy/40 backdrop-blur-[2px]" wire:click="$set('ackId', null)"></div>
            <form wire:submit="confirmAck" class="relative w-full max-w-md space-y-3 rounded-t-[24px] bg-white p-5 sm:rounded-[24px]" style="box-shadow: var(--shadow-lg)">
                <h2 class="text-[17px] font-semibold">{{ __('Accusé de réception') }}</h2>
                <x-field :label="__('Reçu le')" for="k-on" error="ackOn"><x-input id="k-on" type="date" wire:model="ackOn" /></x-field>
                <x-field :label="__('Par (nom du receveur)')" for="k-n" error="ackName"><x-input id="k-n" wire:model="ackName" /></x-field>
                <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('ackId', null)">{{ __('Retour') }}</x-btn><x-btn type="submit" variant="gold" icon="check">{{ __('Enregistrer') }}</x-btn></div>
            </form>
        </div>
    @endif

    @include('livewire.regie.partials.cancel-dialog', ['title' => __('Annuler le bordereau d’envoi')])
</div>
