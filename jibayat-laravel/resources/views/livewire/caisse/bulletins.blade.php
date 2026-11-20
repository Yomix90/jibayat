@php use App\Support\Format; $canPay = $this->allowed('paiements.valider'); @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        @if ($bulk && $canPay)
            <x-slot:actions><x-btn variant="gold" icon="list-checks" wire:click="openValidate()">Solder la sélection ({{ count($selected) }})</x-btn></x-slot:actions>
        @endif
    </x-page-header>

    <section class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        @foreach ([
            ['caisse.bulletins', 'clock', 'En attente', $kpi['pending'], '#C9A13B'],
            ['caisse.encaissements', 'banknote', 'Encaissés (quittances)', $kpi['paid'], '#16A34A'],
            ['caisse.rejetes', 'file-x', 'Rejetés', $kpi['rejected'], '#DC2626'],
        ] as [$r, $icon, $label, $k, $color])
            <a href="{{ route($r) }}" @class(['rounded-2xl border bg-white p-4 transition hover:shadow-md', 'border-gold ring-2 ring-gold/30' => request()->routeIs($r) || $this->pageRoute() === $r, 'border-line' => ! (request()->routeIs($r) || $this->pageRoute() === $r)])>
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-lg text-white" style="background: {{ $color }}"><x-svg-icon :name="$icon" class="h-4 w-4" /></span>
                    <p class="text-xs font-semibold text-muted">{{ tr($label) }}</p>
                </div>
                <p class="mt-2 text-xl font-bold tabular-nums text-ink">{{ Format::money($k['amount']) }} <span class="text-xs text-muted">DH</span></p>
                <p class="text-xs text-muted">{{ $k['count'] }} bulletin(s)</p>
            </a>
        @endforeach
    </section>

    <div class="grid gap-2 sm:grid-cols-2">
        <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('N° BV, quittance, déclaration, contribuable, CIN…')" :aria-label="__('Rechercher')" />
        <x-select wire:model.live="module" :aria-label="__('Module')">
            <option value="">{{ __('Tous les modules') }}</option>
            @foreach ($modules as $code => $label)<option value="{{ $code }}">{{ tr($label) }}</option>@endforeach
        </x-select>
    </div>

    @forelse ($groups as $number => $items)
        <x-card :padding="false" wire:key="g-{{ $number }}">
            <x-slot:title>
                BV n° {{ $number }}
                <span class="ms-2 text-xs font-medium text-muted">{{ $items->count() }} déclaration(s) · {{ Format::money($items->sum('amount')) }} DH</span>
            </x-slot:title>
            @if ($status === 'en_attente' && $canPay && ! $bulk)
                <x-slot:actions><x-btn variant="gold" size="sm" icon="check" wire:click="openValidate({{ $items->first()->id }})">{{ __('Solder & quittancer') }}</x-btn></x-slot:actions>
            @endif
            <ul class="divide-y divide-line">
                @foreach ($items as $s)
                    <li class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm" wire:key="s-{{ $s->id }}">
                        @if ($bulk && $status === 'en_attente')
                            <input type="checkbox" value="{{ $s->id }}" wire:model.live="selected" class="rounded border-line text-accent focus:ring-gold" aria-label="Sélectionner le bulletin {{ $s->slip_number }}">
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-ink">{{ Format::taxpayer($s) }} <span class="text-xs font-normal text-muted">{{ $s->cin }}</span></p>
                            <p class="truncate text-xs text-muted">{{ $s->decl_number }} · {{ $modules[$s->module] ?? $s->module }} ({{ Format::period($s) }})
                                @if (in_array($s->module, ['LOCATION_LOCAUX', 'AFFERMAGE_SOUKS'])) · {{ \Illuminate\Support\Str::before((string) $s->decl_notes, '|') }}@endif</p>
                            @if ($s->status === 'rejete')<p class="text-xs font-semibold text-red-600">Motif du rejet : {{ $s->rejection_reason }}</p>@endif
                            @if ($s->status === 'paye')<p class="text-xs font-semibold text-online">Quittance {{ $s->receipt_number }} du {{ $s->receipt_date?->format('d/m/Y') }}</p>@endif
                        </div>
                        <p class="whitespace-nowrap font-bold tabular-nums text-ink">{{ Format::money($s->amount) }} DH</p>
                        @if ($status !== 'paye')
                            <div class="flex gap-1">
                                @if ($status === 'en_attente' && $canPay)
                                    <x-btn variant="ghost" size="sm" icon="x" wire:click="openAction('reject', {{ $s->id }})">{{ __('Rejeter') }}</x-btn>
                                @endif
                                @if ($this->allowed('bulletins.creer'))
                                    <x-btn variant="ghost" size="sm" icon="pencil" wire:click="openAction('correct', {{ $s->id }})">{{ __('Corriger') }}</x-btn>
                                @endif
                                @if ($this->allowed('caisse.supprimer'))
                                    <x-btn variant="ghost" size="sm" icon="circle-x" wire:click="openAction('cancel', {{ $s->id }})" aria-label="Annuler le bulletin {{ $s->slip_number }}" />
                                @endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-card>
    @empty
        <x-empty-state icon="receipt" title="{{ ['en_attente' => 'Aucun bulletin en attente', 'rejete' => 'Aucun bulletin rejeté', 'paye' => 'Aucun encaissement'][$status] }}" />
    @endforelse

    {{ $slips->links() }}

    <x-panel show="showValidate" :title="__('Solder & quittancer')">
        <form wire:submit="validateSlips" class="space-y-4">
            <p class="text-sm text-muted">{{ count($selected) }} bulletin(s) sélectionné(s). Les bulletins portant le même n° de BV sont soldés ensemble.</p>
            <div class="grid grid-cols-2 gap-3">
                <x-field :label="__('N° de quittance')" for="v-q" error="receipt_number"><x-input id="v-q" wire:model="receipt_number" autofocus /></x-field>
                <x-field :label="__('Date de quittance')" for="v-d" error="receipt_date"><x-input id="v-d" type="date" wire:model="receipt_date" /></x-field>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <x-btn variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-btn>
                <x-btn type="submit" variant="gold" icon="check">{{ __('Valider le paiement') }}</x-btn>
            </div>
        </form>
    </x-panel>

    <x-panel show="showAction" :title="['reject' => 'Rejeter le bulletin', 'correct' => 'Corriger et réémettre', 'cancel' => 'Annuler le bulletin'][$action] ?? ''">
        <form wire:submit="runAction" class="space-y-4">
            @if ($action === 'correct')
                <div class="grid grid-cols-2 gap-3">
                    <x-field :label="__('N° de bulletin')" for="a-n" error="slip_number"><x-input id="a-n" wire:model="slip_number" /></x-field>
                    <x-field :label="__('Mode')" for="a-m" error="payment_mode">
                        <x-select id="a-m" wire:model="payment_mode"><option value="especes">{{ __('Espèces') }}</option><option value="cheque">{{ __('Chèque') }}</option><option value="virement">{{ __('Virement') }}</option><option value="bulletin_manuel">{{ __('Bulletin manuel') }}</option></x-select>
                    </x-field>
                </div>
                <x-field :label="__('Notes')" for="a-r" error="reason"><x-input id="a-r" wire:model="reason" /></x-field>
            @else
                <x-field :label="__('Motif (obligatoire)')" for="a-r" error="reason"><x-input id="a-r" wire:model="reason" /></x-field>
                @if ($action === 'cancel')
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="cancel_declaration" class="rounded border-line text-accent focus:ring-gold">
                        {{ __('Annuler aussi la déclaration (les périodes redeviennent à encaisser)') }}</label>
                    <p class="text-xs text-muted">{{ __('Le bulletin n\'est pas supprimé : il reste visible dans « Pièces annulées » avec le motif.') }}</p>
                @endif
            @endif
            @error('slip')<p class="text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
            <div class="flex justify-end gap-2 pt-2">
                <x-btn variant="secondary" x-on:click="open = false">{{ __('Fermer') }}</x-btn>
                <x-btn type="submit" :variant="$action === 'correct' ? 'primary' : 'danger'" icon="check">{{ __('Confirmer') }}</x-btn>
            </div>
        </form>
    </x-panel>
</div>
