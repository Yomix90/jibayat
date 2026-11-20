@php use App\Support\Format; $t = $outlet->taxpayer; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" :title="$outlet->name" :subtitle="'N° '.$outlet->number.' · '.$t?->displayName()">
        <x-slot:actions>
            @if ($this->allowed('bulletins.creer') && count($quarters) && $outlet->is_active)
                <x-btn variant="gold" icon="hand-coins" :href="route('boissons.encaisser', ['etablissement' => $outlet->id])">{{ __('Encaisser') }}</x-btn>
            @endif
            <x-btn variant="secondary" icon="mail-warning" :href="route('boissons.avis-imprimer', $outlet)" target="_blank">{{ __('Avis de non-paiement') }}</x-btn>
            <x-btn variant="secondary" icon="calendar-range" :href="route('boissons.declaration-annuelle', ['etablissement' => $outlet->id])">{{ __('Déclaration annuelle') }}</x-btn>
            <x-btn variant="ghost" icon="chevron-left" wire:click="$set('outletId', null)">{{ __('Autre établissement') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    @if ($outlet->status === 'cesse' || ! $outlet->is_active)
        <div class="rounded-2xl border border-line bg-bg px-4 py-3 text-sm text-muted">{{ ! $outlet->is_active ? 'Établissement retiré de la liste.' : 'Établissement en cessation d’activité.' }}</div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card :title="__('Exploitant')">
            <dl class="space-y-1.5 text-sm">
                <div><dt class="text-xs text-muted">{{ __('Nom') }}</dt><dd class="font-semibold text-ink">{{ $t?->displayName() }}</dd></div>
                @if ($t?->displayNameAr())<div><dd class="font-ar text-ink" lang="ar" dir="rtl">{{ $t->displayNameAr() }}</dd></div>@endif
                <div class="grid grid-cols-2 gap-2">
                    <div><dt class="text-xs text-muted">{{ __('CIN / RC') }}</dt><dd>{{ $t?->cin ?: ($t?->rc ?: '—') }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('Téléphone') }}</dt><dd>{{ $t?->phone ?: '—' }}</dd></div>
                </div>
            </dl>
        </x-card>

        <x-card :title="__('Établissement')">
            <dl class="space-y-1.5 text-sm">
                <div><dt class="text-xs text-muted">{{ __('Type / tarif') }}</dt><dd class="font-semibold text-ink">{{ $outlet->typeLabel() }} · {{ rtrim(rtrim(number_format($situation['rate'], 2, ',', ''), '0'), ',') }} %</dd></div>
                <div><dt class="text-xs text-muted">{{ __('Adresse') }}</dt><dd>{{ $outlet->address ?: '—' }}</dd></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><dt class="text-xs text-muted">{{ __('Autorisation') }}</dt><dd>{{ $outlet->authorization_number ?: '—' }}{{ $outlet->authorized_on ? ' du '.$outlet->authorized_on->format('d/m/Y') : '' }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('Superficie') }}</dt><dd>{{ Format::money($outlet->area) }} m²</dd></div>
                </div>
                <div><dt class="text-xs text-muted">{{ __('Imposable depuis') }}</dt><dd>T{{ $outlet->tax_start_quarter }} {{ $outlet->tax_start_year ?? 2022 }}</dd></div>
            </dl>
        </x-card>

        <x-card :title="__('Situation')">
            @if ($situation['nb_non_paye'] === 0)
                <x-empty-state icon="check" :title="__('Établissement à jour')" />
            @else
                <p class="text-sm font-semibold text-ink">{{ $period }}</p>
                <p class="font-ar text-sm text-muted" lang="ar" dir="rtl">{{ $periodAr }}</p>
                <div class="mt-2 flex flex-wrap gap-1">
                    @if ($situation['nb_non_declares'])<x-badge color="red">{{ $situation['nb_non_declares'] }} trimestre(s) à déclarer</x-badge>@endif
                    @if ($situation['nb_emises'])<x-badge color="gold">{{ $situation['nb_emises'] }} en attente · {{ Format::money($situation['montant_en_attente']) }} DH</x-badge>@endif
                </div>
                @if ($situation['last_base'] > 0)<p class="mt-2 text-xs text-muted">Estimation sur le dernier CA ({{ Format::money($situation['last_base']) }} DH) : {{ Format::money($situation['total_impaye']) }} DH</p>@endif
            @endif
        </x-card>
    </div>

    @include('livewire.boissons.partials.declarations', ['history' => $history])

    <div class="flex flex-wrap justify-end gap-2">
        <x-btn variant="ghost" size="sm" icon="printer" target="_blank" :href="route('boissons.formulaire', ['type' => 'creation', 'etablissement' => $outlet->id])">{{ __('Formulaire de création') }}</x-btn>
        <x-btn variant="ghost" size="sm" icon="printer" target="_blank" :href="route('boissons.formulaire', ['type' => 'cessation', 'etablissement' => $outlet->id])">{{ __('Formulaire de cessation') }}</x-btn>
        @if ($this->allowed('boissons.modifier') && $outlet->is_active)
            <x-btn variant="ghost" size="sm" icon="circle-x" wire:click="cease"
                   wire:confirm="{{ $outlet->status === 'cesse' ? 'Remettre cet établissement en activité ?' : 'Marquer cet établissement en cessation d’activité ?' }}">
                {{ $outlet->status === 'cesse' ? 'Reprise d’activité' : 'Cessation d’activité' }}</x-btn>
        @endif
    </div>
</div>
