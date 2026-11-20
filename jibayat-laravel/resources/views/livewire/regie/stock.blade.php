@php
    use App\Support\Format;
    use App\Domain\Regie\Services\StockService;
    $canEdit = $this->allowed('regie.modifier');
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-btn variant="secondary" icon="printer" :href="route('regie.imprimer.etat-stock')" target="_blank">{{ __('État de stock') }}</x-btn>
            @if ($canEdit)<x-btn icon="arrow-down-to-line" wire:click="openMove('retrait')">{{ __('Retrait à la TGR') }}</x-btn>@endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-3 md:grid-cols-2">
        @forelse ($state as $v)
            @php($low = $v['regie'] <= 0 || ($v['threshold'] && $v['regie'] < $v['threshold']))
            <x-card wire:key="v-{{ $v['id'] }}">
                <div class="flex items-start justify-between gap-2">
                    <div><p class="font-semibold">{{ $v['label'] }}</p><p class="text-xs text-muted">{{ ucfirst($v['type']) }} · {{ Format::money($v['unit_value']) }} DH · {{ $v['units_per_book'] }} {{ __('par carnet') }}</p></div>
                    @if ($low)<x-badge color="red">{{ $v['regie'] <= 0 ? __('Rupture') : __('Stock bas') }}</x-badge>@endif
                </div>
                <div class="mt-3 grid grid-cols-4 gap-2 text-center">
                    @foreach ([[__('Réserve TGR'), $v['tgr']], [__('Régie'), $v['regie']], [__('Chez les agents'), $v['with_agents']], [__('Consommé'), $v['consumed']]] as [$l, $n])
                        <div class="rounded-xl bg-bg px-2 py-2"><p class="text-[11px] text-muted">{{ $l }}</p><p class="font-bold tabular-nums">{{ number_format($n, 0, ',', ' ') }}</p><p class="text-[10px] text-muted tabular-nums">{{ Format::short($n * $v['unit_value']) }} DH</p></div>
                    @endforeach
                </div>
                @if ($canEdit)
                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-btn variant="secondary" size="sm" icon="arrow-down-to-line" wire:click="openMove('retrait', {{ $v['id'] }})">{{ __('Retrait TGR') }}</x-btn>
                        <x-btn variant="ghost" size="sm" icon="sliders-horizontal" wire:click="openMove('ajustement_regie', {{ $v['id'] }})">{{ __('Ajuster') }}</x-btn>
                    </div>
                @endif
            </x-card>
        @empty
            <x-empty-state class="md:col-span-2" icon="warehouse" :title="__('Aucune valeur de ticket ou de vignette')">{{ __('Créez les valeurs dans Paramètres ➜ Intervenants, puis la dotation TGR dans Configuration du stock.') }}</x-empty-state>
        @endforelse
    </div>

    <x-card :padding="false" :title="__('Derniers mouvements')">
        <ul class="divide-y divide-line text-sm">
            @forelse ($movements as $m)
                <li class="flex flex-wrap items-center gap-3 px-4 py-2.5 {{ $m->cancelled_at ? 'opacity-60' : '' }}">
                    <span class="w-24 text-xs text-muted">{{ $m->moved_on->format('d/m/Y') }}</span>
                    <span class="min-w-0 flex-1">{{ tr(StockService::MOVEMENTS[$m->movement] ?? $m->movement) }} · <strong>{{ $m->value?->label }}</strong>@if ($m->reference) · {{ $m->reference }}@endif @if ($m->notes)<span class="text-muted"> · {{ $m->notes }}</span>@endif
                        @if ($m->cancelled_at)<span class="text-xs text-red-600"> · {{ __('Annulé') }} : {{ $m->cancellation_reason }}</span>@endif</span>
                    <span class="font-semibold tabular-nums">{{ $m->quantity > 0 ? '+' : '' }}{{ number_format($m->quantity, 0, ',', ' ') }}</span>
                    @if (! $m->cancelled_at && $canEdit)<x-btn variant="ghost" size="sm" icon="ban" wire:click="askCancel({{ $m->id }})" :aria-label="__('Annuler')" />@endif
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="warehouse" :title="__('Aucun mouvement')" /></li>
            @endforelse
        </ul>
    </x-card>

    <x-panel show="showMove" title="Mouvement de stock">
        <form wire:submit="saveMove" class="space-y-3">
            <x-field :label="__('Valeur')" for="mv-v" error="move.stamp_value_id"><x-select id="mv-v" wire:model="move.stamp_value_id">@foreach ($values as $v)<option value="{{ $v->id }}">{{ $v->label }} ({{ Format::money($v->unit_value) }})</option>@endforeach</x-select></x-field>
            <x-field :label="__('Mouvement')" for="mv-m" error="move.movement"><x-select id="mv-m" wire:model="move.movement">@foreach (StockService::MOVEMENTS as $k => $l)<option value="{{ $k }}">{{ tr($l) }}</option>@endforeach</x-select></x-field>
            <div class="grid grid-cols-2 gap-3">
                <x-field :label="__('Quantité (unités ; négatif pour un ajustement à la baisse)')" for="mv-q" error="move.quantity"><x-input id="mv-q" type="number" step="1" wire:model="move.quantity" /></x-field>
                <x-field :label="__('Date')" for="mv-d" error="move.moved_on"><x-input id="mv-d" type="date" wire:model="move.moved_on" /></x-field>
            </div>
            <x-field :label="__('Référence (bon de retrait, PV…)')" for="mv-r" error="move.reference"><x-input id="mv-r" wire:model="move.reference" /></x-field>
            <x-field :label="__('Observation')" for="mv-n" error="move.notes"><x-input id="mv-n" wire:model="move.notes" /></x-field>
            <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('showMove', false)">{{ __('Annuler') }}</x-btn><x-btn type="submit" variant="gold" icon="check">{{ __('Enregistrer') }}</x-btn></div>
        </form>
    </x-panel>

    @include('livewire.regie.partials.cancel-dialog', ['title' => __('Annuler le mouvement')])
</div>
