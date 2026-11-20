@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            @if ($this->allowed('config.gerer'))<x-btn icon="plus" wire:click="open('sector')">{{ __('Nouveau secteur') }}</x-btn>@endif
        </x-slot:actions>
    </x-page-header>

    <p class="text-sm text-muted">
        {{ __('Arrêté fiscal actif :') }} <strong>{{ $activeOrder?->number ?? 'aucun' }}</strong>.
        Chaque groupe de tarif est recopié dans l'arrêté sous le code « secteur-T{n°} » : c'est cette valeur qui sert au calcul des loyers.
    </p>

    @forelse ($sectors as $s)
        <x-card :padding="false" wire:key="s-{{ $s->id }}">
            <x-slot:title><span class="font-mono text-xs text-muted">{{ $s->code }}</span> {{ $s->label }}</x-slot:title>
            <x-slot:actions>
                @if ($this->allowed('config.gerer'))
                    <x-btn variant="secondary" size="sm" icon="plus" wire:click="open('tariff', null, {{ $s->id }})">{{ __('Groupe de tarif') }}</x-btn>
                    <x-btn variant="ghost" size="sm" icon="pencil" wire:click="open('sector', {{ $s->id }})" aria-label="Modifier le secteur {{ $s->code }}" />
                    <x-btn variant="ghost" size="sm" icon="trash-2" wire:click="deleteSector({{ $s->id }})" wire:confirm="Supprimer le secteur {{ $s->code }}, ses tarifs et ses boutiques ?" aria-label="Supprimer le secteur {{ $s->code }}" />
                @endif
            </x-slot:actions>
            @if ($s->description)<p class="border-b border-line px-4 py-2 text-xs text-muted">{{ $s->description }}</p>@endif
            <div class="divide-y divide-line">
                @forelse ($s->leaseTariffs as $t)
                    @php($st = $syncStatus[$t->id])
                    <div class="space-y-2 px-4 py-3" wire:key="t-{{ $t->id }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="flex-1 font-semibold text-ink">{{ $t->label }}
                                <span class="ms-1 text-sm font-bold tabular-nums">{{ Format::money($t->value) }} {{ $t->unit }}</span>
                            </p>
                            @if ($st['in_sync'])<x-badge color="green">Arrêté : {{ Format::money($st['fiscal_value']) }}</x-badge>
                            @elseif ($st['fiscal_value'] !== null)<x-badge color="orange">Arrêté : {{ Format::money($st['fiscal_value']) }} (écart)</x-badge>
                            @else<x-badge color="red">{{ __('Non synchronisé') }}</x-badge>@endif
                            <x-badge>{{ $t->leases_count }} bail(s)</x-badge>
                            @unless ($t->is_active)<x-badge>{{ __('Inactif') }}</x-badge>@endunless
                            @if ($this->allowed('config.gerer'))
                                <x-btn variant="ghost" size="sm" icon="pencil" wire:click="open('tariff', {{ $t->id }})" aria-label="Modifier {{ $t->label }}" />
                                <x-btn variant="ghost" size="sm" icon="trash-2" wire:click="deleteTariff({{ $t->id }})" wire:confirm="Supprimer le tarif « {{ $t->label }} » et ses boutiques ?" aria-label="Supprimer {{ $t->label }}" />
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            @foreach ($t->shops as $b)
                                <button type="button" @if ($this->allowed('locations.modifier')) wire:click="open('shop', {{ $b->id }})" @endif wire:key="b-{{ $b->id }}"
                                        @class(['rounded-full px-2.5 py-1 text-xs font-semibold ring-1', 'bg-green-50 text-green-700 ring-green-200' => $b->status === 'disponible',
                                            'bg-bg text-ink ring-line' => $b->status === 'exploitee', 'bg-red-50 text-red-700 ring-red-200' => $b->status === 'fermee'])
                                        title="{{ $b->label }} · {{ Format::money($b->area) }} m² · {{ $b->status }}">{{ $b->number }}</button>
                            @endforeach
                            @if ($this->allowed('locations.modifier'))
                                <button type="button" wire:click="open('shop', null, {{ $t->id }})" class="rounded-full border border-dashed border-line px-2.5 py-1 text-xs font-semibold text-accent hover:border-gold">{{ __('+ Boutiques') }}</button>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-3 text-sm text-muted">{{ __('Aucun groupe de tarif.') }}</p>
                @endforelse
            </div>
        </x-card>
    @empty
        <x-empty-state icon="grid-2x2" :title="__('Aucun secteur')">{{ __('Créez un secteur (ex. « Marché central »), puis ses groupes de tarif et ses boutiques.') }}</x-empty-state>
    @endforelse

    <p class="flex flex-wrap gap-3 text-xs text-muted">
        <span><span class="me-1 inline-block h-2.5 w-2.5 rounded-full bg-green-200"></span>{{ __('disponible') }}</span>
        <span><span class="me-1 inline-block h-2.5 w-2.5 rounded-full bg-line"></span>{{ __('exploitée') }}</span>
        <span><span class="me-1 inline-block h-2.5 w-2.5 rounded-full bg-red-200"></span>{{ __('fermée') }}</span>
    </p>

    <x-panel show="showForm" :title="['sector' => 'Secteur', 'tariff' => 'Groupe de tarif', 'shop' => $editingId ? 'Boutique' : 'Ajouter des boutiques'][$panel] ?? ''">
        <form wire:submit="save" class="space-y-4">
            @if ($panel === 'sector')
                <div class="grid grid-cols-2 gap-3">
                    <x-field :label="__('Code')" for="s-code" error="form.code" hint="Vide = SEC-n° automatique"><x-input id="s-code" wire:model="form.code" class="font-mono uppercase" /></x-field>
                    <x-field :label="__('Ordre')" for="s-order" error="form.sort_order"><x-input id="s-order" type="number" min="0" wire:model="form.sort_order" /></x-field>
                </div>
                <x-field :label="__('Libellé')" for="s-label" error="form.label"><x-input id="s-label" wire:model="form.label" /></x-field>
                <x-field :label="__('Description')" for="s-desc" error="form.description"><x-textarea id="s-desc" wire:model="form.description" /></x-field>
            @elseif ($panel === 'tariff')
                <x-field :label="__('Libellé')" for="t-label" error="form.label"><x-input id="t-label" wire:model="form.label" /></x-field>
                <div class="grid grid-cols-2 gap-3">
                    <x-field :label="__('Type')" for="t-type" error="form.tariff_type">
                        <x-select id="t-type" wire:model="form.tariff_type"><option value="fixe">{{ __('Fixe (DH/mois)') }}</option><option value="m2">{{ __('Au m² (DH/m²/mois)') }}</option></x-select>
                    </x-field>
                    <x-field :label="__('Valeur')" for="t-value" error="form.value"><x-input id="t-value" type="number" step="0.01" min="0" wire:model="form.value" /></x-field>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="form.is_active" class="rounded border-line text-accent focus:ring-gold"> {{ __('Actif') }}</label>
            @elseif ($panel === 'shop')
                @if ($editingId)
                    <div class="grid grid-cols-2 gap-3">
                        <x-field :label="__('Numéro')" for="b-num" error="form.number"><x-input id="b-num" wire:model="form.number" /></x-field>
                        <x-field :label="__('Superficie (m²)')" for="b-area" error="form.area"><x-input id="b-area" type="number" step="0.01" min="0" wire:model="form.area" /></x-field>
                    </div>
                @else
                    <x-field :label="__('Numéros (séparés par des virgules)')" for="b-nums" error="form.numbers"><x-input id="b-nums" wire:model="form.numbers" placeholder="B-01, B-02, B-03" /></x-field>
                    <x-field :label="__('Superficie (m²)')" for="b-area" error="form.area"><x-input id="b-area" type="number" step="0.01" min="0" wire:model="form.area" /></x-field>
                @endif
                <x-field :label="__('Libellé')" for="b-label" error="form.label"><x-input id="b-label" wire:model="form.label" /></x-field>
                <x-field :label="__('Statut')" for="b-status" error="form.status">
                    <x-select id="b-status" wire:model="form.status"><option value="disponible">{{ __('Disponible') }}</option><option value="exploitee">{{ __('Exploitée') }}</option><option value="fermee">{{ __('Fermée') }}</option></x-select>
                </x-field>
                <x-field :label="__('Notes')" for="b-notes" error="form.notes"><x-input id="b-notes" wire:model="form.notes" /></x-field>
            @endif
            <div class="flex flex-wrap justify-between gap-2 pt-2">
                <span>
                    @if ($panel === 'shop' && $editingId && $this->allowed('locations.modifier'))
                        <x-btn variant="ghost" size="sm" icon="trash-2" class="!text-red-600" wire:click="deleteShop({{ $editingId }})" wire:confirm="Supprimer cette boutique ?" x-on:click="open = false">{{ __('Supprimer') }}</x-btn>
                    @endif
                </span>
                <span class="flex gap-2">
                    <x-btn variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-btn>
                    <x-btn type="submit" icon="check">{{ __('Enregistrer') }}</x-btn>
                </span>
            </div>
        </form>
    </x-panel>
</div>
