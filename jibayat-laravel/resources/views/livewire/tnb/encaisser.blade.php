@php use App\Support\Format; use App\Domain\Tnb\Services\TnbCalculator; $t = $parcel->taxpayer; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" :title="'Terrain n° '.$parcel->number.($file ? ' — dossier '.$file : '')"
                   :subtitle="$t?->displayName().' · '.Format::money($parcel->area).' m² · zone '.$parcel->zone">
        <x-slot:actions>
            <x-btn variant="secondary" size="sm" icon="file-text" :href="route('tnb.fiche', ['terrain' => $parcel->id])">{{ __('Fiche') }}</x-btn>
            <x-btn variant="ghost" size="sm" icon="chevron-left" wire:click="$set('parcelId', null)">{{ __('Autre terrain') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <section class="grid gap-3 sm:grid-cols-3">
        <div class="surface p-4">
            <p class="text-xs font-semibold text-muted">{{ __('Années à déclarer') }}</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-red-600">{{ Format::money($situation['total_not_declared']) }} DH</p>
            <p class="text-xs text-muted">{{ TnbCalculator::periodLabel(array_column($situation['not_declared'], 'annee')) }}
                · <span class="font-ar" lang="ar">{{ TnbCalculator::periodLabel(array_column($situation['not_declared'], 'annee'), true) }}</span></p>
        </div>
        <div class="surface p-4">
            <p class="text-xs font-semibold text-muted">{{ __('Émis, en attente du régisseur') }}</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-[#7a5d16]">{{ Format::money($pendingTotal) }} DH</p>
            <p class="text-xs text-muted">{{ count($pendingRows) }} année(s) · montants émis</p>
        </div>
        <div class="surface p-4">
            <p class="text-xs font-semibold text-muted">{{ __('Déjà payé') }}</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-online">{{ Format::money($paidTotal) }} DH</p>
            @if ($exempt)<p class="text-xs text-muted">Exonéré (permis) : {{ implode(', ', $exempt) }}</p>@endif
        </div>
    </section>

    @if (count($selectable))
        <form wire:submit="declare" class="grid gap-4 lg:grid-cols-3">
            <x-card class="lg:col-span-2" :padding="false">
                <x-slot:title>Calcul par année au {{ \Illuminate\Support\Carbon::parse($declared_on ?: today())->format('d/m/Y') }}</x-slot:title>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                            <tr>
                                <th class="px-3 py-2"></th><th class="px-3 py-2 text-start">{{ __('Année') }}</th><th class="px-3 py-2 text-end">{{ __('Tarif') }}</th>
                                <th class="px-3 py-2 text-end">{{ __('Principal') }}</th><th class="px-3 py-2 text-end">{{ __('Pénalité') }}</th><th class="px-3 py-2 text-end">{{ __('Majoration') }}</th>
                                <th class="px-3 py-2 text-end">{{ __('Amende') }}</th><th class="px-3 py-2 text-end">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($selectable as $y)
                                <tr wire:key="y-{{ $y['annee'] }}" @class(['bg-gold/10' => in_array($y['annee'], array_map('intval', $selected), true)])>
                                    <td class="px-3 py-2"><input type="checkbox" value="{{ $y['annee'] }}" wire:model.live="selected" class="rounded border-line text-accent focus:ring-gold" aria-label="Année {{ $y['annee'] }}"></td>
                                    <td class="px-3 py-2 font-semibold">{{ $y['annee'] }}
                                        @isset($y['reissue'])<x-badge color="red" class="ms-1">{{ $y['reissue']->rejected ? 'bulletin rejeté' : 'à réémettre' }}</x-badge>@endisset
                                        @if ($y['total'] < TnbCalculator::BELOW_THRESHOLD)<x-badge class="ms-1">{{ __('sous seuil') }}</x-badge>@endif
                                        @unless ($y['tariff'])<x-badge color="red" class="ms-1">{{ __('aucun tarif') }}</x-badge>@endunless
                                    </td>
                                    <td class="px-3 py-2 text-end tabular-nums text-xs text-muted">{{ Format::money($y['taux']) }} {{ $y['tariff']?->unit === 'DH' ? 'DH' : 'DH/m²' }}</td>
                                    <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($y['principal']) }}</td>
                                    <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($y['penalite']) }}</td>
                                    <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($y['majoration']) }}</td>
                                    <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($y['amende']) }}</td>
                                    <td class="px-3 py-2 text-end font-bold tabular-nums">{{ Format::money($y['total']) }}</td>
                                </tr>
                            @endforeach
                            @foreach ($pendingRows as $year => $d)
                                <tr class="text-muted" wire:key="yp-{{ $year }}">
                                    <td class="px-3 py-2"><x-svg-icon name="clock" class="h-4 w-4 text-gold" /></td>
                                    <td class="px-3 py-2">{{ $year }} <x-badge color="gold" class="ms-1">{{ __('émis') }}</x-badge></td>
                                    <td colspan="5" class="px-3 py-2 text-xs">{{ __('Bulletin en attente — il se règle à la caisse du régisseur.') }}</td>
                                    <td class="px-3 py-2 text-end tabular-nums">{{ Format::money($d->total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="border-t border-line px-4 py-2 text-[11px] text-muted">{{ __('Échéance au 28 février de chaque année. Après l\'échéance : amende de non-déclaration (minimum 500 DH), pénalité et majoration de retard. Total inférieur à 200 DH : déclaration « sous seuil », sans bulletin.') }}</p>
                @error('selected')<p class="px-4 pb-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                @error('years')<p class="px-4 pb-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
            </x-card>

            <x-card :title="__('Déclaration et bulletin')">
                <div class="space-y-3">
                    <div class="rounded-xl bg-navy p-3 text-white">
                        <p class="text-xs text-white/70">{{ count($selected) }} année(s) sélectionnée(s)</p>
                        <p class="mt-1 text-2xl font-bold tabular-nums text-gold">{{ Format::money($total) }} DH</p>
                    </div>
                    <x-field :label="__('Date de déclaration')" for="d-date" error="declared_on" :hint="__('Les pénalités sont calculées à cette date.')">
                        <x-input id="d-date" type="date" wire:model.live="declared_on" />
                    </x-field>
                    <x-field label="N° de bulletin (vide = automatique)" for="d-slip" error="slip_number"><x-input id="d-slip" wire:model="slip_number" /></x-field>
                    @if ($this->allowed('bulletins.creer'))
                        <x-btn type="submit" variant="gold" icon="hand-coins" class="w-full" :disabled="! count($selected)"
                               wire:confirm="Émettre la déclaration des années cochées ?">{{ __('Déclarer et émettre') }}</x-btn>
                    @else
                        <p class="text-xs text-muted">{{ __('Vous n\'avez pas le droit d\'émettre des bulletins.') }}</p>
                    @endif
                </div>
            </x-card>
        </form>
    @else
        <x-card>
            <x-empty-state icon="check" :title="__('Aucune année à déclarer')">
                {{ $pendingTotal > 0 ? 'Les années dues sont émises ('.Format::money($pendingTotal).' DH en attente du régisseur).' : 'Terrain à jour.' }}
            </x-empty-state>
        </x-card>
    @endif

    @include('livewire.tnb.partials.declarations', ['history' => $history, 'cancellable' => false])
</div>
