@php use App\Support\Format; $t = $vehicle->taxpayer; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" :title="$vehicle->registration ?: 'Véhicule n° '.$vehicle->number" :subtitle="$vehicle->type.' · '.$t?->displayName()">
        <x-slot:actions>
            @if ($this->allowed('bulletins.creer') && count($quarters) && $vehicle->is_active)
                <x-btn variant="gold" icon="hand-coins" :href="route('transport.encaisser', ['vehicule' => $vehicle->id])">{{ __('Encaisser') }}</x-btn>
            @endif
            <x-btn variant="secondary" icon="mail-warning" :href="route('transport.avis-imprimer', $vehicle)" target="_blank">{{ __('Avis de non-paiement') }}</x-btn>
            <x-btn variant="ghost" icon="chevron-left" wire:click="$set('vehicleId', null)">{{ __('Autre véhicule') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card :title="__('Propriétaire')">
            <dl class="space-y-1.5 text-sm">
                <div><dt class="text-xs text-muted">{{ __('Nom') }}</dt><dd class="font-semibold text-ink">{{ $t?->displayName() }}</dd></div>
                @if ($t?->displayNameAr())<div><dd class="font-ar" lang="ar" dir="rtl">{{ $t->displayNameAr() }}</dd></div>@endif
                <div class="grid grid-cols-2 gap-2">
                    <div><dt class="text-xs text-muted">CIN</dt><dd>{{ $t?->cin ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('Téléphone') }}</dt><dd>{{ $t?->phone ?: '—' }}</dd></div>
                </div>
            </dl>
        </x-card>
        <x-card :title="__('Véhicule')">
            <dl class="space-y-1.5 text-sm">
                <div class="grid grid-cols-2 gap-2">
                    <div><dt class="text-xs text-muted">{{ __('Immatriculation') }}</dt><dd class="font-mono font-semibold" dir="ltr">{{ $vehicle->registration ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('Places') }}</dt><dd>{{ $vehicle->seats ?: '—' }}</dd></div>
                </div>
                <div><dt class="text-xs text-muted">{{ __('Agrément') }}</dt><dd>{{ $vehicle->authorization_number ?: '—' }}{{ $vehicle->authorized_on ? ' du '.$vehicle->authorized_on->format('d/m/Y') : '' }}</dd></div>
                <div><dt class="text-xs text-muted">{{ __('Par trimestre') }}</dt><dd class="font-semibold">stationnement {{ Format::money($situation['sta']) }} + TPV {{ Format::money($situation['tpv']) }} = {{ Format::money($situation['per_quarter']) }} DH</dd></div>
                <div><dt class="text-xs text-muted">{{ __('Imposable depuis') }}</dt><dd>T{{ $vehicle->tax_start_quarter }} {{ $vehicle->tax_start_year ?? 2020 }}</dd></div>
            </dl>
        </x-card>
        <x-card :title="__('Situation')">
            @if ($situation['nb_non_paye'] === 0)
                <x-empty-state icon="check" :title="__('Véhicule à jour')" />
            @else
                <p class="text-2xl font-bold tabular-nums text-red-600">{{ Format::money($situation['montant_impaye']) }} DH</p>
                <p class="text-sm text-ink">{{ $period }}</p>
                <p class="font-ar text-sm text-muted" lang="ar" dir="rtl">{{ $periodAr }}</p>
                <div class="mt-2 flex flex-wrap gap-1">
                    @if ($situation['nb_non_declares'])<x-badge color="red">{{ $situation['nb_non_declares'] }} trimestre(s) à payer</x-badge>@endif
                    @if ($situation['nb_emises'])<x-badge color="gold">{{ $situation['nb_emises'] }} en attente</x-badge>@endif
                </div>
                            @endif
        </x-card>
    </div>

    @include('livewire.transport.partials.declarations', ['history' => $history])
</div>
