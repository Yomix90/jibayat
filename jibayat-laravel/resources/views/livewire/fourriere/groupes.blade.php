@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-btn variant="secondary" icon="clock" :href="route('fourriere.encheres')">{{ __('Véhicules aliénables') }}</x-btn>
            @if ($this->allowed('fourriere.modifier'))<x-btn icon="plus" wire:click="create">{{ __('Nouveau groupe') }}</x-btn>@endif
        </x-slot:actions>
    </x-page-header>

    @forelse ($groups as $g)
        <x-card :padding="false" wire:key="g-{{ $g->id }}">
            <div class="flex flex-wrap items-center gap-3 border-b border-line px-4 py-3">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#3F3F46] text-white"><x-svg-icon name="gavel" class="h-5 w-5" /></span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">{{ $g->number }} — {{ $g->label }} @if ($g->status === 'vendu')<x-badge color="green">{{ __('Vendu') }}</x-badge>@endif</p>
                    <p class="text-xs text-muted">{{ $g->auction_on ? 'Enchère le '.$g->auction_on->format('d/m/Y') : 'Date à fixer' }}{{ $g->place ? ' · '.$g->place : '' }}
                        · prix d'ouverture {{ Format::money($g->opening_price) }} DH · {{ $g->vehicles->count() }} véhicule(s)</p>
                </div>
                <x-btn variant="secondary" size="sm" icon="printer" :href="route('fourriere.groupe-imprimer', $g)" target="_blank">{{ __('Imprimer') }}</x-btn>
                @if ($this->allowed('fourriere.modifier') && $g->status !== 'vendu')<x-btn variant="ghost" size="sm" icon="pencil" wire:click="edit({{ $g->id }})" aria-label="Modifier le groupe {{ $g->number }}" />@endif
            </div>
            <ul class="divide-y divide-line text-sm">
                @forelse ($g->vehicles as $v)
                    @php($c = $v->impoundCase)
                    <li class="flex flex-wrap items-center gap-3 px-4 py-2.5" wire:key="v-{{ $v->id }}">
                        <span class="w-6 text-xs text-muted">{{ $v->sort_order }}</span>
                        <span class="font-mono text-xs font-bold" dir="ltr">{{ $c?->registration }}</span>
                        <span class="min-w-0 flex-1 truncate text-xs text-muted">{{ $c?->vehicle_type }} · dossier {{ $c?->number }} · état {{ $v->condition }}</span>
                        @if ($c?->sale)
                            <x-badge color="green">Vendu {{ Format::money($c->sale->hammer_price) }} DH · {{ $c->sale->buyer_name }}</x-badge>
                        @elseif ($this->allowed('fourriere.modifier'))
                            <x-btn variant="gold" size="sm" icon="scale" wire:click="openSale({{ $g->id }}, {{ $c?->id }})">{{ __('Vente') }}</x-btn>
                            <x-btn variant="ghost" size="sm" icon="x" wire:click="removeVehicle({{ $v->id }})" wire:confirm="Retirer ce véhicule du groupe ?" :aria-label="__('Retirer')" />
                        @endif
                    </li>
                @empty
                    <li class="px-4 py-3 text-xs text-muted">{{ __('Aucun véhicule : ajoutez-en depuis « Véhicules aliénables ».') }}</li>
                @endforelse
            </ul>
        </x-card>
    @empty
        <x-card><x-empty-state icon="gavel" :title="__('Aucun groupe d\'enchères')">{{ __('Créez un groupe, puis ajoutez-y les véhicules de plus de 365 jours.') }}</x-empty-state></x-card>
    @endforelse

    <x-panel show="showForm" :title="$editingId ? 'Modifier le groupe' : 'Nouveau groupe d’enchères'">
        <form wire:submit="save" class="space-y-3">
            <x-field :label="__('Libellé')" for="g-label" error="form.label"><x-input id="g-label" wire:model="form.label" :placeholder="__('Enchère voitures — 1er semestre')" /></x-field>
            <div class="grid grid-cols-2 gap-2">
                <x-field :label="__('Date de l\'enchère')" for="g-date" error="form.auction_on"><x-input id="g-date" type="date" wire:model="form.auction_on" /></x-field>
                <x-field :label="__('Prix d\'ouverture (DH)')" for="g-price" error="form.opening_price"><x-input id="g-price" type="number" step="0.01" min="0" wire:model="form.opening_price" /></x-field>
            </div>
            <x-field :label="__('Lieu')" for="g-place" error="form.place"><x-input id="g-place" wire:model="form.place" /></x-field>
            <x-field :label="__('Type de véhicules')" for="g-type" error="form.vehicle_type">
                <x-select id="g-type" wire:model="form.vehicle_type"><option value="">{{ __('Tous') }}</option>@foreach ($types as $t)<option value="{{ $t->label }}">{{ $t->label }}</option>@endforeach</x-select>
            </x-field>
            <x-field :label="__('Notes')" for="g-notes" error="form.notes"><x-input id="g-notes" wire:model="form.notes" /></x-field>
            <div class="flex justify-end gap-2"><x-btn variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-btn><x-btn type="submit" icon="check">{{ __('Enregistrer') }}</x-btn></div>
        </form>
    </x-panel>

    @if ($saleGroupId)
        <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="{{ __('Enregistrer la vente') }}">
            <div class="absolute inset-0 bg-navy/40 backdrop-blur-[2px]" wire:click="$set('saleGroupId', null)"></div>
            <div class="relative w-full max-w-lg rounded-t-[24px] bg-white p-5 sm:rounded-[24px]" style="box-shadow: var(--shadow-lg)">
                <h2 class="mb-3 text-[17px] font-semibold">{{ __('Enregistrer la vente') }}</h2>
                <form wire:submit="saveSale" class="space-y-3">
                    <div class="grid gap-2 sm:grid-cols-2">
                        <x-field :label="__('Acheteur')" for="s-buyer" error="sale.buyer_name" class="sm:col-span-2"><x-input id="s-buyer" wire:model="sale.buyer_name" /></x-field>
                        <x-field label="CIN" for="s-cin" error="sale.buyer_cin"><x-input id="s-cin" wire:model="sale.buyer_cin" /></x-field>
                        <x-field :label="__('Téléphone')" for="s-phone" error="sale.buyer_phone"><x-input id="s-phone" wire:model="sale.buyer_phone" /></x-field>
                        <x-field :label="__('Prix d\'adjudication (DH)')" for="s-price" error="sale.hammer_price"><x-input id="s-price" type="number" step="0.01" min="0" wire:model="sale.hammer_price" /></x-field>
                        <x-field :label="__('N° de quittance')" for="s-receipt" error="sale.receipt_number"><x-input id="s-receipt" wire:model="sale.receipt_number" /></x-field>
                    </div>
                    <x-field :label="__('PV de vente (PDF, image, Word)')" for="s-report" error="report">
                        <input id="s-report" type="file" wire:model="report" class="block w-full text-sm file:me-3 file:rounded-lg file:border-0 file:bg-bg file:px-3 file:py-2 file:text-sm file:font-semibold">
                    </x-field>
                    <x-field :label="__('Notes')" for="s-notes" error="sale.notes"><x-input id="s-notes" wire:model="sale.notes" /></x-field>
                    @error('sale')<p class="text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                    <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('saleGroupId', null)">{{ __('Fermer') }}</x-btn><x-btn type="submit" icon="check">{{ __('Enregistrer la vente') }}</x-btn></div>
                </form>
            </div>
        </div>
    @endif
</div>
