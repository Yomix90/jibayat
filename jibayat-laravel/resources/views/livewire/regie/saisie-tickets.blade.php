@php
    use App\Support\Format;
    $canEdit = $this->allowed('regie.modifier');
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-btn variant="secondary" icon="file" :href="route('regie.imprimer.decharge-vierge')" target="_blank">{{ __('Bon vierge') }}</x-btn>
            @if ($canEdit)<x-btn icon="plus" wire:click="openForm">{{ __('Nouvelle décharge') }}</x-btn>@endif
        </x-slot:actions>
    </x-page-header>

    <div class="inline-flex rounded-full border border-line bg-white p-0.5 text-xs font-semibold" role="group" aria-label="{{ __('État') }}">
        @foreach (['remis' => 'Chez les agents', 'solde' => 'Soldées', 'annule' => 'Annulées'] as $k => $l)
            <button type="button" wire:click="$set('status', '{{ $k }}')" class="rounded-full px-3 py-1.5 {{ $status === $k ? 'bg-navy text-white' : 'text-muted' }}">{{ __($l) }}</button>
        @endforeach
    </div>

    <x-card :padding="false">
        <ul class="divide-y divide-line text-sm">
            @forelse ($rows as $d)
                <li class="flex flex-wrap items-center gap-3 px-4 py-3 {{ $d->cancelled_at ? 'opacity-60' : '' }}" wire:key="t-{{ $d->id }}">
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2"><span class="font-mono text-xs font-bold">{{ $d->number }}</span>
                            <span class="font-semibold">{{ $d->employee ? trim($d->employee->last_name.' '.$d->employee->first_name) : '—' }}</span>@if ($d->place)<x-badge>{{ $d->place }}</x-badge>@endif</p>
                        <p class="text-xs text-muted">{{ $d->discharge_date->format('d/m/Y') }} · {{ $d->value?->label }} · {{ number_format($d->quantity, 0, ',', ' ') }} {{ __('ticket(s)') }}
                            @if ($d->first_number) ({{ __('du') }} {{ $d->first_number }} {{ __('au') }} {{ $d->last_number }})@endif
                            · {{ Format::money($d->quantity * (float) $d->value?->unit_value) }} DH</p>
                        @if ($d->status === 'solde')<p class="text-xs text-green-700">{{ __('Soldée le') }} {{ $d->settled_on?->format('d/m/Y') }} : {{ $d->returned }} {{ __('rendu(s)') }}, {{ $d->sold() }} {{ __('vendu(s)') }} = {{ Format::money($d->amount_paid) }} DH</p>@endif
                        @if ($d->corrections)<p class="text-xs text-[#8a5a12]">{{ count($d->corrections) }} {{ __('modification(s)') }} — {{ __('dernière') }} : {{ last($d->corrections)['motif'] }}</p>@endif
                        @if ($d->cancelled_at)<p class="text-xs text-red-600">{{ __('Annulée') }} : {{ $d->cancellation_reason }}</p>@endif
                    </div>
                    <x-btn variant="secondary" size="sm" icon="printer" :href="route('regie.imprimer.decharge', $d)" target="_blank">{{ __('Bon') }}</x-btn>
                    @if (! $d->cancelled_at && $canEdit)
                        @if ($d->status === 'remis')<x-btn variant="gold" size="sm" icon="undo-2" wire:click="askSettle({{ $d->id }})">{{ __('Retour') }}</x-btn>@endif
                        <x-btn variant="ghost" size="sm" icon="pencil" wire:click="openEdit({{ $d->id }})">{{ __('Modifier') }}</x-btn>
                        @if ($d->status === 'remis')<x-btn variant="ghost" size="sm" icon="ban" wire:click="askCancel({{ $d->id }})" :aria-label="__('Annuler')" />@endif
                    @endif
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="ticket" :title="__('Aucune décharge')" /></li>
            @endforelse
        </ul>
        <div class="border-t border-line px-4 py-2">{{ $rows->links() }}</div>
    </x-card>

    <x-panel show="showForm" title="Bon de décharge de tickets">
        <form wire:submit="save" class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <x-field :label="__('Date')" for="d-date" error="form.discharge_date"><x-input id="d-date" type="date" wire:model="form.discharge_date" /></x-field>
                <x-field :label="__('Tickets')" for="d-v" error="form.stamp_value_id"><x-select id="d-v" wire:model="form.stamp_value_id">@foreach ($values as $v)<option value="{{ $v->id }}">{{ $v->label }} ({{ Format::money($v->unit_value) }})</option>@endforeach</x-select></x-field>
            </div>
            <x-field :label="__('Agent (collecteur)')" for="d-e" error="form.employee_id"><x-select id="d-e" wire:model="form.employee_id"><option value="">—</option>@foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->last_name }} {{ $e->first_name }}</option>@endforeach</x-select></x-field>
            <x-field :label="__('Lieu (souk, marché…)')" for="d-p"><x-input id="d-p" wire:model="form.place" /></x-field>
            <div class="grid grid-cols-3 gap-3">
                <x-field :label="__('Du n°')" for="d-f" error="form.first_number"><x-input id="d-f" type="number" wire:model="form.first_number" /></x-field>
                <x-field :label="__('Au n°')" for="d-l" error="form.last_number"><x-input id="d-l" type="number" wire:model="form.last_number" /></x-field>
                <x-field :label="__('ou quantité')" for="d-q" error="form.quantity"><x-input id="d-q" type="number" min="1" wire:model="form.quantity" /></x-field>
            </div>
            <x-field :label="__('Observation')" for="d-n"><x-input id="d-n" wire:model="form.notes" /></x-field>
            <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('showForm', false)">{{ __('Annuler') }}</x-btn><x-btn type="submit" variant="gold" icon="check">{{ __('Enregistrer la décharge') }}</x-btn></div>
        </form>
    </x-panel>

    @if ($settling)
        <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="{{ __('Retour de l’agent') }}">
            <div class="absolute inset-0 bg-navy/40 backdrop-blur-[2px]" wire:click="$set('settleId', null)"></div>
            <form wire:submit="confirmSettle" class="relative w-full max-w-md space-y-3 rounded-t-[24px] bg-white p-5 sm:rounded-[24px]" style="box-shadow: var(--shadow-lg)">
                <h2 class="text-[17px] font-semibold">{{ __('Retour de l’agent') }} — {{ $settling->number }}</h2>
                <p class="text-xs text-muted">{{ number_format($settling->quantity, 0, ',', ' ') }} {{ __('ticket(s) remis') }} · {{ $settling->value?->label }}</p>
                <div class="grid grid-cols-2 gap-3">
                    <x-field :label="__('Tickets rendus')" for="s-r" error="returned"><x-input id="s-r" type="number" min="0" max="{{ $settling->quantity }}" wire:model.live.debounce.300ms="returned" /></x-field>
                    <x-field :label="__('Date du retour')" for="s-d" error="settledOn"><x-input id="s-d" type="date" wire:model="settledOn" /></x-field>
                </div>
                @php($sold = max(0, $settling->quantity - (int) $returned))
                <div class="rounded-xl bg-bg p-3 text-sm">{{ __('Vendus') }} <strong class="tabular-nums">{{ $sold }}</strong> × {{ Format::money($settling->value?->unit_value) }} = <strong class="tabular-nums">{{ Format::money($sold * (float) $settling->value?->unit_value) }} DH</strong> {{ __('à encaisser') }}</div>
                <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('settleId', null)">{{ __('Retour') }}</x-btn><x-btn type="submit" variant="gold" icon="check">{{ __('Solder la décharge') }}</x-btn></div>
            </form>
        </div>
    @endif

    @if ($editId)
        <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="{{ __('Modifier la décharge') }}">
            <div class="absolute inset-0 bg-navy/40 backdrop-blur-[2px]" wire:click="$set('editId', null)"></div>
            <form wire:submit="saveEdit" class="relative w-full max-w-md space-y-3 rounded-t-[24px] bg-white p-5 sm:rounded-[24px]" style="box-shadow: var(--shadow-lg)">
                <h2 class="text-[17px] font-semibold">{{ __('Modifier la décharge') }}</h2>
                <p class="text-xs text-muted">{{ __('Correction de la sortie de tickets (erreur de numéros ou de quantité). L’ancienne valeur et le motif sont conservés ; si la décharge est soldée, sa recette est refaite.') }}</p>
                <div class="grid grid-cols-2 gap-3">
                    <x-field :label="__('Du n°')" for="m-f"><x-input id="m-f" type="number" wire:model="edit.first_number" /></x-field>
                    <x-field :label="__('Au n°')" for="m-l"><x-input id="m-l" type="number" wire:model="edit.last_number" /></x-field>
                    <x-field :label="__('ou quantité')" for="m-q" error="edit.quantity"><x-input id="m-q" type="number" min="1" wire:model="edit.quantity" /></x-field>
                    <x-field :label="__('Tickets rendus')" for="m-r" error="edit.returned"><x-input id="m-r" type="number" min="0" wire:model="edit.returned" /></x-field>
                </div>
                <x-field :label="__('Motif')" for="m-reason" error="editReason"><x-textarea id="m-reason" rows="2" wire:model="editReason" /></x-field>
                <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('editId', null)">{{ __('Retour') }}</x-btn><x-btn type="submit" variant="gold" icon="check">{{ __('Enregistrer') }}</x-btn></div>
            </form>
        </div>
    @endif

    @include('livewire.regie.partials.cancel-dialog', ['title' => __('Annuler la décharge')])
</div>
