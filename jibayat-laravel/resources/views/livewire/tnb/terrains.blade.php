@php use App\Support\Format; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions>
            <x-btn variant="secondary" icon="printer" :href="route('tnb.imprimer-dossiers', ['q' => $search, 'statut' => $situation, 'zone' => $zone, 'lotissement' => $lotissement])" target="_blank">{{ __('Imprimer') }}</x-btn>
            @if ($this->allowed('tnb.ajouter'))<x-btn icon="plus" wire:click="createParcel">{{ __('Nouveau terrain') }}</x-btn>@endif
        </x-slot:actions>
    </x-page-header>

    @foreach ($alerts as $a)
        <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-accent/40 bg-accent/10 px-4 py-3 text-sm" role="alert">
            <x-svg-icon name="triangle-alert" class="h-4 w-4 text-accent" />
            <span>{{ __('Exonération du terrain') }} <strong>{{ $a['permit']->parcel->number }}</strong> ({{ $a['permit']->parcel->taxpayer?->displayName() }})
                se termine fin {{ $a['permit']->exemption_end_year }} — {{ $a['days'] }} jour(s) restant(s).</span>
            <a href="{{ route('tnb.fiche', ['terrain' => $a['permit']->parcel_id]) }}" class="ms-auto text-xs font-semibold text-accent hover:underline">{{ __('Voir la fiche') }}</a>
        </div>
    @endforeach

    <section class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5" aria-label="{{ __('Indicateurs') }}">
        @foreach ([
            ['folder-open', 'Dossiers', $kpi['files'], $kpi['parcels'].' terrain(s)', '#8B5A2B'],
            ['triangle-alert', 'Dossiers avec impayés', $kpi['unpaid'], Format::money($kpi['amount_due']).' DH dus', '#DC2626'],
            ['clock', 'En attente régisseur', $kpi['pending'], Format::money($kpi['amount_pending']).' DH', '#C9A13B'],
            ['check', 'Dossiers à jour', $kpi['up_to_date'], null, '#16A34A'],
            ['map-pin', 'Zones', $zones->count(), $subdivisions->count().' lotissement(s)', '#5B7590'],
        ] as [$icon, $label, $value, $sub, $color])
            <div @class(['surface p-4', 'col-span-2 md:col-span-1' => $loop->last])>
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-lg text-white" style="background: {{ $color }}"><x-svg-icon :name="$icon" class="h-4 w-4" /></span>
                    <p class="text-xs font-semibold text-muted">{{ tr($label) }}</p>
                </div>
                <p class="mt-2 text-2xl font-bold tabular-nums text-ink">{{ $value }}</p>
                @if ($sub)<p class="text-xs font-semibold text-muted">{{ $sub }}</p>@endif
            </div>
        @endforeach
    </section>

    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Redevable, CIN, n° dossier, titre foncier…')" :aria-label="__('Rechercher')" />
        <x-select wire:model.live="situation" :aria-label="__('Situation')">
            <option value="">{{ __('Toutes les situations') }}</option>
            <option value="impaye">{{ __('Avec années non déclarées') }}</option>
            <option value="en_attente">{{ __('En attente régisseur') }}</option>
            <option value="ajour">{{ __('À jour') }}</option>
        </x-select>
        <x-select wire:model.live="zone" :aria-label="__('Zone')">
            <option value="">{{ __('Toutes les zones') }}</option>
            @foreach ($zones as $z)<option value="{{ $z }}">Zone {{ $z }}</option>@endforeach
        </x-select>
        <x-select wire:model.live="lotissement" :aria-label="__('Lotissement')">
            <option value="">{{ __('Tous les lotissements') }}</option>
            @foreach ($subdivisions as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
        </x-select>
    </div>

    <div class="space-y-3">
        @forelse ($dossiers as $d)
            <x-card :padding="false" wire:key="d-{{ $d['file']->id }}">
                <div class="flex flex-wrap items-center gap-3 border-b border-line px-4 py-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#8B5A2B] font-mono text-sm font-bold text-white">{{ $d['file']->number }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-ink">{{ $d['taxpayer']->displayName() }}
                            @if ($d['taxpayer']->displayNameAr())<span class="font-ar font-normal text-muted" lang="ar"> · {{ $d['taxpayer']->displayNameAr() }}</span>@endif</p>
                        <p class="text-xs text-muted">Dossier n° {{ $d['file']->number }} · {{ $d['taxpayer']->cin ?: 'CIN —' }} {{ $d['taxpayer']->phone ? '· '.$d['taxpayer']->phone : '' }} · {{ count($d['parcels']) }} terrain(s)</p>
                    </div>
                    @if ($d['total_due'] > 0)
                        <div class="text-end">
                            <p class="font-bold tabular-nums text-red-600">{{ Format::money($d['total_due']) }} DH</p>
                            @if ($d['total_pending'] > 0)<p class="text-xs text-[#7a5d16]">dont {{ Format::money($d['total_pending']) }} en attente</p>@endif
                        </div>
                    @else
                        <x-badge color="green">{{ __('À jour') }}</x-badge>
                    @endif
                </div>
                <ul class="divide-y divide-line">
                    @forelse ($d['parcels'] as $r)
                        @php($p = $r['parcel'])
                        <li class="flex flex-wrap items-center gap-3 px-4 py-2.5 text-sm" wire:key="p-{{ $p->id }}">
                            <span class="font-mono text-xs font-bold text-ink">T {{ $p->number }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-ink">{{ $p->locationLabel() }}</p>
                                <p class="text-xs text-muted">{{ Format::money($p->area) }} m² · zone {{ $p->zone }} · {{ $statuses[$p->status] ?? $p->status }}</p>
                            </div>
                            <div class="flex flex-wrap gap-1">
                                @if ($r['nb_non_paye'] === 0)
                                    <x-badge color="green">{{ __('À jour') }}</x-badge>
                                @else
                                    <span class="text-xs text-muted">{{ $r['period'] }}</span>
                                    @if ($r['nb_non_declares'])<x-badge color="red">{{ $r['nb_non_declares'] }} an(s) · {{ Format::money($r['total_not_declared']) }} DH</x-badge>@endif
                                    @if ($r['nb_emises'])<x-badge color="gold">{{ $r['nb_emises'] }} en attente</x-badge>@endif
                                @endif
                            </div>
                            <div class="flex gap-1">
                                <x-btn variant="ghost" size="sm" icon="file-text" :href="route('tnb.fiche', ['terrain' => $p->id])">{{ __('Fiche') }}</x-btn>
                                @if ($this->allowed('bulletins.creer') && $r['nb_non_declares'] > 0)
                                    <x-btn variant="gold" size="sm" icon="hand-coins" :href="route('tnb.encaisser', ['terrain' => $p->id])">{{ __('Encaisser') }}</x-btn>
                                @endif
                                @if ($this->allowed('tnb.modifier'))
                                    <x-btn variant="ghost" size="sm" icon="pencil" wire:click="editParcel({{ $p->id }})" aria-label="Modifier le terrain {{ $p->number }}" />
                                @endif
                                @if ($this->allowed('tnb.supprimer'))
                                    <x-btn variant="ghost" size="sm" icon="trash-2" wire:click="deleteParcel({{ $p->id }})"
                                           wire:confirm="Retirer le terrain n° {{ $p->number }} de la liste ?" aria-label="Supprimer le terrain {{ $p->number }}" />
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="px-4 py-3 text-xs text-muted">{{ __('Aucun terrain actif dans ce dossier.') }}</li>
                    @endforelse
                </ul>
            </x-card>
        @empty
            <x-card><x-empty-state icon="map-pin" :title="__('Aucun dossier')">{{ __('Ajoutez un terrain ou modifiez les filtres.') }}</x-empty-state></x-card>
        @endforelse
    </div>

    @include('livewire.tnb.partials.parcel-form')
</div>
