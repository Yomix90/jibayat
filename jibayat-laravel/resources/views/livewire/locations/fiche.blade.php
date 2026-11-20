@php use App\Support\Format; $t = $lease->taxpayer; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" :title="'Bail n° '.$lease->number" :subtitle="$t?->displayName()">
        <x-slot:actions>
            @if ($this->allowed('bulletins.creer') && $situation['nb_non_declares'] > 0)
                <x-btn variant="gold" icon="hand-coins" :href="route('locations.encaisser', ['bail' => $lease->id])">{{ __('Encaisser') }}</x-btn>
            @endif
            <x-btn variant="secondary" icon="mail-warning" :href="route('locations.avis-imprimer', $lease)" target="_blank">{{ __('Avis de non-paiement') }}</x-btn>
            <x-btn variant="ghost" icon="chevron-left" wire:click="$set('leaseId', null)">{{ __('Autre bail') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card :title="__('Locataire')">
            <dl class="space-y-1.5 text-sm">
                <div><dt class="text-xs text-muted">{{ __('Nom') }}</dt><dd class="font-semibold text-ink">{{ $t?->displayName() }}</dd></div>
                @if ($t?->displayNameAr())<div><dd class="font-ar text-ink" lang="ar" dir="rtl">{{ $t->displayNameAr() }}</dd></div>@endif
                <div class="grid grid-cols-2 gap-2">
                    <div><dt class="text-xs text-muted">CIN</dt><dd>{{ $t?->cin ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('Téléphone') }}</dt><dd>{{ $t?->phone ?: '—' }}</dd></div>
                </div>
                <div><dt class="text-xs text-muted">{{ __('N° contribuable') }}</dt><dd class="font-mono text-xs">{{ $t?->number ?: '—' }}</dd></div>
            </dl>
        </x-card>

        <x-card :title="__('Local et tarif')">
            <dl class="space-y-1.5 text-sm">
                <div><dt class="text-xs text-muted">{{ __('Local') }}</dt><dd class="font-semibold text-ink">{{ $lease->shop?->number ?? $lease->premises_ref ?? '—' }} {{ $lease->shop?->label ? '· '.$lease->shop->label : '' }}</dd></div>
                <div><dt class="text-xs text-muted">{{ __('Secteur / tarif') }}</dt><dd>{{ $lease->leaseSector?->label ?? '—' }} · {{ $lease->leaseTariff?->label ?? '—' }}</dd></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><dt class="text-xs text-muted">{{ __('Superficie') }}</dt><dd>{{ Format::money($lease->area) }} m²</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('Loyer actuel') }}</dt><dd class="font-bold">{{ Format::money($rent) }} DH</dd></div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div><dt class="text-xs text-muted">{{ __('Début') }}</dt><dd>{{ $lease->starts_on?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('Fin') }}</dt><dd>{{ $lease->ends_on?->format('d/m/Y') ?? '—' }}</dd></div>
                </div>
                <div><dt class="text-xs text-muted">{{ __('Statut') }}</dt><dd>@if ($lease->is_active)<x-badge color="green">{{ ucfirst($lease->status) }}</x-badge>@else<x-badge>{{ __('Archivé') }}</x-badge>@endif</dd></div>
            </dl>
        </x-card>

        <x-card :title="__('Situation')">
            @if ($situation['nb_non_paye'] === 0)
                <x-empty-state icon="check" :title="__('Bail à jour')" />
            @else
                <p class="text-2xl font-bold tabular-nums text-red-600">{{ Format::money($situation['total_du']) }} DH</p>
                <p class="text-sm text-ink">{{ $situation['periode'] }}</p>
                <p class="font-ar text-sm text-muted" lang="ar" dir="rtl">{{ $periodAr }}</p>
                <div class="mt-2 flex flex-wrap gap-1">
                    @if ($situation['nb_non_declares'])<x-badge color="red">{{ $situation['nb_non_declares'] }} mois à émettre · {{ Format::money($situation['total_non_declare']) }} DH</x-badge>@endif
                    @if ($situation['nb_emises'])<x-badge color="gold">{{ $situation['nb_emises'] }} mois en attente · {{ Format::money($situation['total_en_attente']) }} DH</x-badge>@endif
                </div>
            @endif
        </x-card>
    </div>

    @if ($history)
        <x-card :title="__('Historique du tarif (arrêtés fiscaux)')">
            <ul class="flex flex-wrap gap-2 text-xs">
                @foreach ($history as $h)
                    <li class="rounded-full bg-bg px-3 py-1"><strong>{{ Format::money($h['value']) }} DH</strong> du {{ $h['start']->format('d/m/Y') }}{{ $h['end'] ? ' au '.$h['end']->format('d/m/Y') : '' }} · {{ $h['order'] }}</li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <x-card :title="__('Déclarations et paiements')" :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr><th class="px-4 py-2 text-start">N°</th><th class="px-4 py-2 text-start">{{ __('Mois') }}</th><th class="px-4 py-2 text-end">{{ __('Montant') }}</th><th class="px-4 py-2 text-start">{{ __('Bulletin / quittance') }}</th><th class="px-4 py-2 text-start">{{ __('Statut') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($declarations as $d)
                        <tr wire:key="d-{{ $d->id }}">
                            <td class="px-4 py-2.5 font-mono text-xs">{{ $d->number }}</td>
                            <td class="px-4 py-2.5 text-xs">{{ \Illuminate\Support\Str::before((string) $d->notes, '|') }}</td>
                            <td class="px-4 py-2.5 text-end font-semibold tabular-nums">{{ Format::money($d->total) }}</td>
                            <td class="px-4 py-2.5 text-xs">BV {{ $d->slip_number ?? '—' }}{{ $d->receipt_number ? ' · Q '.$d->receipt_number : '' }}</td>
                            <td class="px-4 py-2.5">
                                @switch($d->status)
                                    @case('paye')<x-badge color="green">{{ __('Payé') }}</x-badge>@break
                                    @case('annule')<x-badge>{{ __('Annulé') }}</x-badge>@break
                                    @default <x-badge color="gold">{{ $d->slip_status === 'rejete' ? 'Bulletin rejeté' : 'En attente' }}</x-badge>
                                @endswitch
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4 text-center text-sm text-muted">{{ __('Aucune déclaration.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
