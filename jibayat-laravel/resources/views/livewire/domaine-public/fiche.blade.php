@php use App\Support\Format; use App\Domain\DomainePublic\Services\OccupationService; $t = $occupation->taxpayer; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" :title="'Occupation n° '.$occupation->number.' — '.$occupation->type" :subtitle="$t?->displayName()">
        <x-slot:actions>
            @if ($this->allowed('bulletins.creer') && $situation['nb_non_declares'] > 0 && $occupation->is_active)
                <x-btn variant="gold" icon="hand-coins" :href="route('domaine-public.encaisser', ['occupation' => $occupation->id])">{{ __('Encaisser') }}</x-btn>
            @endif
            <x-btn variant="secondary" icon="mail-warning" :href="route('domaine-public.avis-imprimer', $occupation)" target="_blank">{{ __('Avis de non-paiement') }}</x-btn>
            <x-btn variant="ghost" icon="chevron-left" wire:click="$set('occupationId', null)">{{ __('Autre occupation') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card :title="__('Occupant')">
            <dl class="space-y-1.5 text-sm">
                <div><dt class="text-xs text-muted">{{ __('Nom') }}</dt><dd class="font-semibold text-ink">{{ $t?->displayName() }}</dd></div>
                @if ($t?->displayNameAr())<div><dd class="font-ar" lang="ar" dir="rtl">{{ $t->displayNameAr() }}</dd></div>@endif
                <div class="grid grid-cols-2 gap-2">
                    <div><dt class="text-xs text-muted">{{ __('CIN / RC') }}</dt><dd>{{ $t?->cin ?: ($t?->rc ?: '—') }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('Téléphone') }}</dt><dd>{{ $t?->phone ?: '—' }}</dd></div>
                </div>
            </dl>
        </x-card>
        <x-card :title="__('Autorisation')">
            <dl class="space-y-1.5 text-sm">
                <div><dt class="text-xs text-muted">{{ __('Emplacement') }}</dt><dd class="font-semibold">{{ $occupation->location }}</dd></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><dt class="text-xs text-muted">{{ __('Superficie') }}</dt><dd class="font-semibold">{{ Format::money($occupation->area) }} m²</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('N° d\'autorisation') }}</dt><dd>{{ $occupation->authorization_number ?: '—' }}</dd></div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div><dt class="text-xs text-muted">{{ __('Du') }}</dt><dd>{{ $occupation->starts_on?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('Au') }}</dt><dd>{{ $occupation->ends_on?->format('d/m/Y') ?? '—' }} @if ($situation['est_expire'])<x-badge>{{ __('Expirée') }}</x-badge>@endif</dd></div>
                </div>
                <div><dt class="text-xs text-muted">{{ __('Redevance (arrêté fiscal)') }}</dt>
                    <dd>{{ Format::money($situation['tariff']->value) }} {{ $situation['tariff']->unit }} × {{ Format::money($occupation->area) }} m² =
                        <strong>{{ Format::money($situation['trim_base']) }} DH/trim.</strong> · {{ Format::money($situation['an_base']) }} DH/an</dd></div>
            </dl>
            @if ($this->allowed('domaine_public.modifier') && $occupation->is_active)
                <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-line pt-3">
                    <span class="text-xs font-semibold text-muted">{{ __('Renouveler :') }}</span>
                    @foreach (OccupationService::DURATIONS as $k => $label)
                        <x-btn variant="secondary" size="sm" wire:click="renew('{{ $k }}')" wire:confirm="Prolonger l'autorisation de {{ tr($label) }} ?">+ {{ tr($label) }}</x-btn>
                    @endforeach
                </div>
            @endif
        </x-card>
        <x-card :title="__('Situation')">
            @if ($situation['nb_impayes'] === 0)
                <x-empty-state icon="check" :title="__('Occupation à jour')" />
            @else
                <p class="text-2xl font-bold tabular-nums text-red-600">{{ Format::money($situation['montant_impaye']) }} DH</p>
                <p class="text-sm">{{ $period }}</p>
                <p class="font-ar text-sm text-muted" lang="ar" dir="rtl">{{ $periodAr }}</p>
                <div class="mt-2 flex flex-wrap gap-1">
                    @if ($situation['nb_non_declares'])<x-badge color="red">{{ $situation['nb_non_declares'] }} trimestre(s) à liquider</x-badge>@endif
                    @if ($situation['nb_emises'])<x-badge color="gold">{{ $situation['nb_emises'] }} en attente</x-badge>@endif
                </div>
            @endif
        </x-card>
    </div>

    @include('livewire.domaine-public.partials.history', ['history' => $history])
</div>
