@php use App\Support\Format; use App\Domain\Fourriere\Services\ImpoundCalculator; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" :title="$case->registration.' — dossier '.$case->number" :subtitle="$case->vehicle_type">
        <x-slot:actions>
            @if ($this->allowed('bulletins.creer') && $case->status === 'en_fourriere')
                <x-btn variant="gold" icon="hand-coins" :href="route('fourriere.encaisser', ['dossier' => $case->id])">{{ __('Encaisser') }}</x-btn>
            @endif
            @if ($canRelease && $this->allowed('fourriere.modifier'))
                <x-btn icon="key-round" wire:click="release" wire:confirm="Valider la sortie et la restitution du véhicule {{ $case->registration }} ?">{{ __('Valider la sortie') }}</x-btn>
            @endif
            <x-btn variant="ghost" icon="chevron-left" wire:click="$set('caseId', null)">{{ __('Autre dossier') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card :title="__('Véhicule')">
            <dl class="space-y-1.5 text-sm">
                <div class="grid grid-cols-2 gap-2">
                    <div><dt class="text-xs text-muted">{{ __('Immatriculation') }}</dt><dd class="font-mono font-semibold" dir="ltr">{{ $case->registration }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('N° de dépôt') }}</dt><dd>{{ $case->deposit_number ?: '—' }}</dd></div>
                </div>
                <div><dt class="text-xs text-muted">{{ __('Type · tarif') }}</dt><dd>{{ $case->vehicle_type }} · {{ Format::money($case->daily_rate) }} DH/jour</dd></div>
                <div><dt class="text-xs text-muted">{{ __('Déposant · motif') }}</dt><dd>{{ $case->depositor ?: '—' }} · {{ $case->reason ?: '—' }}</dd></div>
                @if ($case->notes)<div><dt class="text-xs text-muted">{{ __('Notes') }}</dt><dd>{{ $case->notes }}</dd></div>@endif
            </dl>
        </x-card>
        <x-card :title="__('Garde')">
            <p class="text-xs text-muted">Entré le {{ $case->impounded_on?->format('d/m/Y') }}</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ $info['days'] }} jour(s)
                @if ($case->status !== 'sortie' && $info['plus365'])<x-badge color="red">{{ __('aliénable (+365)') }}</x-badge>@elseif ($case->status !== 'sortie' && $info['plus265'])<x-badge color="gold">{{ __('+265 jours') }}</x-badge>@endif</p>
            @if ($case->status !== 'sortie')
                <p class="mt-2 text-sm">{{ $info['days'] }} × {{ Format::money($case->daily_rate) }} + remorquage {{ Format::money($case->towing_fee) }} =</p>
                <p class="text-xl font-bold tabular-nums text-red-600">{{ Format::money($info['amount']) }} DH</p>
            @else
                <p class="mt-2"><x-badge color="green">Sorti le {{ ($case->released_on ?? $case->release_validated_on)?->format('d/m/Y') }}</x-badge></p>
            @endif
        </x-card>
        <x-card :title="__('Propriétaire')">
            <dl class="space-y-1.5 text-sm">
                <div><dt class="text-xs text-muted">{{ __('Nom') }}</dt><dd class="font-semibold">{{ $case->owner_name ?: '—' }}</dd></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><dt class="text-xs text-muted">CIN</dt><dd>{{ $case->owner_cin ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('Téléphone') }}</dt><dd>{{ $case->owner_phone ?: '—' }}</dd></div>
                </div>
                <div><dt class="text-xs text-muted">{{ __('Statut') }}</dt><dd><x-badge>{{ ImpoundCalculator::STATUSES[$case->status] ?? $case->status }}</x-badge></dd></div>
                @if ($case->sale)
                    <div><dt class="text-xs text-muted">{{ __('Vendu aux enchères') }}</dt><dd>{{ $case->sale->buyer_name }} · {{ Format::money($case->sale->hammer_price) }} DH le {{ $case->sale->sold_on?->format('d/m/Y') }}</dd></div>
                @elseif ($case->auctionEntry)
                    <div><dt class="text-xs text-muted">{{ __('Enchères') }}</dt><dd>Groupe {{ $case->auctionEntry->auctionGroup?->number }}</dd></div>
                @endif
            </dl>
        </x-card>
    </div>

    <x-card :title="__('Paiements')" :padding="false">
        <ul class="divide-y divide-line text-sm">
            @forelse ($payments as $p)
                <li class="flex flex-wrap items-center gap-3 px-4 py-2.5" wire:key="p-{{ $p->id }}">
                    <span class="font-mono text-xs font-semibold">{{ $p->number }}</span>
                    <span class="flex-1 text-xs text-muted">BV {{ $p->slip_number ?? '—' }} · {{ $p->notes }}</span>
                    <span class="font-semibold tabular-nums">{{ Format::money($p->total) }} DH</span>
                    @if ($p->status === 'paye')<x-badge color="green">Payé · Q {{ $p->receipt_number }}</x-badge>
                    @elseif ($p->slip_status === 'rejete')<x-badge color="red">Rejeté : {{ $p->rejection_reason }}</x-badge>
                    @else<x-badge color="gold">{{ __('En attente régisseur') }}</x-badge>@endif
                </li>
            @empty
                <li class="px-4 py-3 text-xs text-muted">{{ __('Aucun paiement. La sortie exige un paiement validé par le régisseur.') }}</li>
            @endforelse
        </ul>
    </x-card>
</div>
