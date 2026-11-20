@php
    use App\Support\Format;
    use App\Domain\Regie\Services\MinutesService;
    $ar = app()->isLocale('ar');
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-btn variant="secondary" icon="file" :href="route('regie.imprimer.pv-vierge', $type)" target="_blank">{{ __('Modèle vierge') }}</x-btn>
            @if ($this->allowed('paiements.valider'))<x-btn icon="plus" wire:click="openForm">{{ __('Établir le PV') }}</x-btn>@endif
        </x-slot:actions>
    </x-page-header>

    <p class="text-sm text-muted">
        @switch($type)
            @case('arrete') {{ __('Chaque fin de mois, le régisseur arrête sa caisse : le solde du livre de caisse est comparé au billetage.') }} @break
            @case('verification') {{ __('Chaque trimestre, le comptable ou un vérificateur contrôle la caisse : solde théorique, billetage, écart.') }} @break
            @case('recolement') {{ __('Le dernier jour du trimestre, les tickets et vignettes en stock à la régie sont comptés et comparés au stock théorique.') }} @break
            @default {{ __('Passation entre régisseurs : caisse, stock de tickets et vignettes, opérations en suspens.') }}
        @endswitch
    </p>

    <x-card :padding="false">
        <ul class="divide-y divide-line text-sm">
            @forelse ($rows as $r)
                <li class="flex flex-wrap items-center gap-3 px-4 py-3 {{ $r->cancelled_at ? 'opacity-60' : '' }}" wire:key="pv-{{ $r->id }}">
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2"><span class="font-mono text-xs font-bold">{{ $r->number }}</span>
                            <span class="font-semibold">{{ __('Du') }} {{ $r->report_date->format('d/m/Y') }}</span>
                            @if ($t['periodicity'] === 'month')<x-badge>{{ tr(\App\Domain\Regie\Services\Ledger::MONTHS[$r->period]) }} {{ $r->year }}</x-badge>@elseif ($t['periodicity'] === 'quarter')<x-badge>T{{ $r->period }} {{ $r->year }}</x-badge>@endif</p>
                        <p class="text-xs text-muted">
                            @if ($t['cash']){{ __('Théorique') }} {{ Format::money($r->theoretical_balance) }} · {{ __('constaté') }} {{ Format::money($r->counted_balance) }} @endif
                            @if ($t['stock']) · {{ count($r->data['stock'] ?? []) }} {{ __('article(s) compté(s)') }}@endif
                            @if ($r->data['people']['verifier'] ?? null) · {{ $r->data['people']['verifier'] }}@endif
                            @if ($type === 'remise') · {{ $r->data['people']['outgoing'] ?? '' }} ➜ {{ $r->data['people']['incoming'] ?? '' }}@endif
                            · {{ $r->author?->name }}</p>
                        @if ($r->cancelled_at)<p class="text-xs text-red-600">{{ __('Annulé') }} : {{ $r->cancellation_reason }}</p>@endif
                    </div>
                    @php($stockGap = collect($r->data['stock'] ?? [])->sum('difference'))
                    @if ((float) $r->difference != 0 || $stockGap != 0)<x-badge color="red">{{ __('Écart') }} @if ((float) $r->difference != 0){{ Format::money($r->difference) }} DH @endif @if ($stockGap != 0){{ $stockGap }} u.@endif</x-badge>@else<x-badge color="green">{{ __('Sans écart') }}</x-badge>@endif
                    <x-btn variant="secondary" size="sm" icon="printer" :href="route('regie.imprimer.pv', $r)" target="_blank">{{ __('Imprimer') }}</x-btn>
                    @if (! $r->cancelled_at && $this->allowed('paiements.valider'))<x-btn variant="ghost" size="sm" icon="ban" wire:click="askCancel({{ $r->id }})" :aria-label="__('Annuler')" />@endif
                </li>
            @empty
                <li class="p-4"><x-empty-state icon="clipboard-check" :title="__('Aucun procès-verbal')" /></li>
            @endforelse
        </ul>
        <div class="border-t border-line px-4 py-2">{{ $rows->links() }}</div>
    </x-card>

    <x-panel show="showForm" :title="$ar ? $t['ar'] : $t['fr']" max-width="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <x-field :label="__('Date du procès-verbal')" for="pv-date" error="date"><x-input id="pv-date" type="date" wire:model.live="date" /></x-field>

            @if ($type === 'verification')
                <div class="grid grid-cols-2 gap-3">
                    <x-field :label="__('Vérificateur')" for="pv-v" error="people.verifier"><x-input id="pv-v" wire:model="people.verifier" /></x-field>
                    <x-field :label="__('Qualité')" for="pv-q"><x-input id="pv-q" wire:model="people.quality" :placeholder="__('Percepteur, inspecteur…')" /></x-field>
                </div>
            @elseif ($type === 'remise')
                <div class="grid grid-cols-2 gap-3">
                    <x-field :label="__('Régisseur sortant')" for="pv-o" error="people.outgoing"><x-input id="pv-o" wire:model="people.outgoing" /></x-field>
                    <x-field :label="__('Régisseur entrant')" for="pv-i" error="people.incoming"><x-input id="pv-i" wire:model="people.incoming" /></x-field>
                </div>
            @else
                <x-field :label="__('Régisseur')" for="pv-r"><x-input id="pv-r" wire:model="people.regisseur" /></x-field>
            @endif

            @if ($t['cash'])
                <fieldset class="space-y-2">
                    <legend class="text-xs font-bold uppercase tracking-[.06em] text-muted">{{ __('Billetage') }}</legend>
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                        @foreach (MinutesService::DENOMINATIONS as $den)
                            <label class="flex items-center gap-2 rounded-lg border border-line px-2 py-1 text-sm"><span class="w-14 text-end font-semibold tabular-nums">{{ $den }} DH</span><span class="text-muted">×</span>
                                <input type="number" min="0" step="1" wire:model.live.debounce.300ms="bills.{{ $den }}" class="w-full border-0 bg-transparent p-1 text-end tabular-nums focus:ring-0" aria-label="{{ __('Nombre de') }} {{ $den }} DH"></label>
                        @endforeach
                        <label class="col-span-3 flex items-center gap-2 rounded-lg border border-line px-2 py-1 text-sm sm:col-span-4"><span class="font-semibold">{{ __('Chèques en caisse (DH)') }}</span>
                            <input type="number" min="0" step="0.01" wire:model.live.debounce.300ms="cheques" class="flex-1 border-0 bg-transparent p-1 text-end tabular-nums focus:ring-0" aria-label="{{ __('Chèques en caisse') }}"></label>
                    </div>
                    @if ($theory)
                        @php($gap = round($counted - $theory['balance'], 2))
                        <div class="grid grid-cols-3 gap-2 rounded-xl bg-bg p-3 text-sm">
                            <div><p class="text-xs text-muted">{{ __('Solde théorique (livre de caisse)') }}</p><p class="font-bold tabular-nums">{{ Format::money($theory['balance']) }}</p></div>
                            <div><p class="text-xs text-muted">{{ __('Constaté') }}</p><p class="font-bold tabular-nums">{{ Format::money($counted) }}</p></div>
                            <div><p class="text-xs text-muted">{{ __('Écart') }}</p><p class="font-bold tabular-nums {{ $gap == 0 ? 'text-online' : 'text-red-600' }}">{{ Format::money($gap) }}</p></div>
                        </div>
                    @endif
                </fieldset>
            @endif

            @if ($t['stock'] && $theory)
                <fieldset class="space-y-2">
                    <legend class="text-xs font-bold uppercase tracking-[.06em] text-muted">{{ __('Tickets et vignettes en stock à la régie') }}</legend>
                    @forelse ($theory['stock'] as $v)
                        @php($c = $counts[$v['id']] ?? '')
                        <div class="grid grid-cols-6 items-center gap-2 text-sm" wire:key="st-{{ $v['id'] }}">
                            <span class="col-span-2">{{ $v['label'] }} <span class="text-xs text-muted">({{ Format::money($v['unit_value']) }})</span></span>
                            <span class="text-end text-xs text-muted">{{ __('théorique') }} <strong class="tabular-nums text-ink">{{ $v['theoretical'] }}</strong></span>
                            <x-input type="number" min="0" step="1" wire:model.live.debounce.300ms="counts.{{ $v['id'] }}" class="col-span-2 text-end" :aria-label="__('Compté').' '.$v['label']" />
                            <span class="text-end tabular-nums {{ $c === '' ? 'text-muted' : ((int) $c - $v['theoretical'] == 0 ? 'text-online' : 'text-red-600') }}">{{ $c === '' ? '—' : (int) $c - $v['theoretical'] }}</span>
                        </div>
                        @error("counts.{$v['id']}")<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    @empty
                        <p class="text-xs text-muted">{{ __('Aucune valeur de ticket ou de vignette active.') }}</p>
                    @endforelse
                </fieldset>
            @endif

            @if ($type === 'remise' && $theory)
                <div class="rounded-xl bg-bg p-3 text-xs">
                    <p class="mb-1 font-semibold">{{ __('Opérations en suspens remises') }}</p>
                    @foreach ($theory['pending'] as $l => $n)<div class="flex justify-between"><span>{{ tr($l) }}</span><strong class="tabular-nums">{{ $n }}</strong></div>@endforeach
                </div>
            @endif

            <x-field :label="__('Observations')" for="pv-n" error="notes"><x-textarea id="pv-n" rows="2" wire:model="notes" /></x-field>
            <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('showForm', false)">{{ __('Annuler') }}</x-btn><x-btn type="submit" variant="gold" icon="check">{{ __('Établir le procès-verbal') }}</x-btn></div>
        </form>
    </x-panel>

    @include('livewire.regie.partials.cancel-dialog', ['title' => __('Annuler le procès-verbal')])
</div>
