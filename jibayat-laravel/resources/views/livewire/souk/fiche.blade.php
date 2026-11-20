@php use App\Support\Format; use App\Domain\Souk\Services\SoukCalculator; use App\Domain\Souk\Models\SoukContract; $t = $contract->taxpayer; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" :title="'Contrat n° '.$contract->number.' — '.$contract->souk_name" :subtitle="$t?->displayName()">
        <x-slot:actions>
            @if ($this->allowed('bulletins.creer') && $situation['nb_non_declares'] > 0 && $contract->is_active)
                <x-btn variant="gold" icon="hand-coins" :href="route('souk.encaisser', ['contrat' => $contract->id])">{{ __('Encaisser') }}</x-btn>
            @endif
            <x-btn variant="secondary" icon="mail-warning" :href="route('souk.avis-imprimer', $contract)" target="_blank">{{ __('Avis de non-paiement') }}</x-btn>
            <x-btn variant="ghost" icon="chevron-left" wire:click="$set('contractId', null)">{{ __('Autre contrat') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card :title="__('Fermier')">
            <dl class="space-y-1.5 text-sm">
                <div><dt class="text-xs text-muted">{{ __('Nom') }}</dt><dd class="font-semibold text-ink">{{ $t?->displayName() }}</dd></div>
                @if ($t?->displayNameAr())<div><dd class="font-ar" lang="ar" dir="rtl">{{ $t->displayNameAr() }}</dd></div>@endif
                <div class="grid grid-cols-2 gap-2">
                    <div><dt class="text-xs text-muted">CIN</dt><dd>{{ $t?->cin ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('Téléphone') }}</dt><dd>{{ $t?->phone ?: '—' }}</dd></div>
                </div>
            </dl>
        </x-card>
        <x-card :title="__('Contrat')">
            <dl class="space-y-1.5 text-sm">
                <div><dt class="text-xs text-muted">{{ __('Souk / emplacement / activité') }}</dt><dd class="font-semibold">{{ $contract->souk_name }} · {{ $contract->pitch_number ?: '—' }} · {{ $contract->activity ?: '—' }}</dd></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><dt class="text-xs text-muted">{{ __('Début') }}</dt><dd>{{ $contract->starts_on?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('Fin') }}</dt><dd>{{ $contract->ends_on?->format('d/m/Y') ?? '—' }}</dd></div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div><dt class="text-xs text-muted">{{ __('Redevance actuelle') }}</dt><dd class="font-bold">{{ Format::money($situation['current_fee']) }} DH/mois</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('Revalorisation') }}</dt><dd>+{{ rtrim(rtrim(number_format(SoukCalculator::rate($contract), 2, ',', ''), '0'), ',') }} % / {{ SoukCalculator::duration($contract) }} an(s)</dd></div>
                </div>
                <div><dt class="text-xs text-muted">{{ __('Statut') }}</dt><dd>@if ($contract->status === 'actif')<x-badge color="green">{{ __('En cours') }}</x-badge>@else<x-badge>{{ SoukContract::STATUSES[$contract->status] ?? $contract->status }}</x-badge>@endif</dd></div>
            </dl>
        </x-card>
        <x-card :title="__('Situation')">
            @if ($situation['nb_mois_impaye'] === 0)
                <x-empty-state icon="check" :title="__('Contrat à jour')" />
            @else
                <p class="text-2xl font-bold tabular-nums text-red-600">{{ Format::money($situation['total_impaye']) }} DH</p>
                <p class="text-sm">{{ SoukCalculator::periodLabel($situation['months']) }}</p>
                <p class="font-ar text-sm text-muted" lang="ar" dir="rtl">{{ SoukCalculator::periodLabel($situation['months'], true) }}</p>
                <ul class="mt-2 space-y-0.5 text-xs text-muted">
                    @foreach ($groups as $g)<li>{{ $g['nb_mois'] }} mois × {{ Format::money($g['redevance_mensuelle']) }} = <span class="font-semibold text-ink">{{ Format::money($g['montant']) }} DH</span></li>@endforeach
                </ul>
            @endif
        </x-card>
    </div>

    <x-card :title="__('Périodes et revalorisations')" :padding="false">
        @if ($this->allowed('souk.modifier') && $contract->is_active)
            <x-slot:actions>
                <x-btn variant="secondary" size="sm" icon="refresh-cw" wire:click="openRenew">{{ __('Prolonger') }}</x-btn>
                @if ($contract->status !== 'resilie')<x-btn variant="ghost" size="sm" icon="circle-x" wire:click="openTerminate">{{ __('Résilier') }}</x-btn>@endif
            </x-slot:actions>
        @endif
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                    <tr><th class="px-4 py-2 text-start">{{ __('Période') }}</th><th class="px-4 py-2 text-start">{{ __('Du') }}</th><th class="px-4 py-2 text-start">{{ __('Au') }}</th><th class="px-4 py-2 text-end">{{ __('Redevance / mois') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($periods as $p)
                        <tr><td class="px-4 py-2">{{ $p['renouvellement'] ? 'Renouvellement '.$p['renouvellement'] : 'Période initiale' }}</td>
                            <td class="px-4 py-2">{{ \Illuminate\Support\Carbon::parse($p['date_debut'])->format('d/m/Y') }}</td>
                            <td class="px-4 py-2">{{ \Illuminate\Support\Carbon::parse($p['date_fin'])->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-end font-semibold tabular-nums">{{ Format::money($p['redevance_mensuelle']) }} DH</td></tr>
                    @empty
                        <tr><td colspan="4" class="p-4 text-center text-sm text-muted">{{ __('Le contrat n\'a pas encore commencé.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    @include('livewire.souk.partials.history', ['history' => $history])

    <x-card :title="__('Documents')" :padding="false">
        @if ($this->allowed('souk.modifier'))<x-slot:actions><x-btn variant="secondary" size="sm" icon="upload" wire:click="openDocument">{{ __('Téléverser') }}</x-btn> </x-slot:actions> @endif
        <ul class="divide-y divide-line text-sm">
            @forelse ($documents as $doc)
                <li class="flex items-center gap-2 px-4 py-2.5" wire:key="doc-{{ $doc->id }}">
                    <x-svg-icon name="file" class="h-4 w-4 text-muted" />
                    <a href="{{ route('souk.document', $doc) }}" class="min-w-0 flex-1 truncate font-semibold text-ink hover:underline">{{ $doc->file_name }}</a>
                    <span class="text-xs text-muted">{{ \App\Livewire\Souk\Fiche::DOCUMENT_TYPES[$doc->type] ?? $doc->type }} · {{ $doc->created_at?->format('d/m/Y') }}</span>
                    @if ($this->allowed('souk.modifier'))
                        <x-btn variant="ghost" size="sm" icon="trash-2" wire:click="deleteDocument({{ $doc->id }})" wire:confirm="Supprimer ce document ?" :aria-label="__('Supprimer le document')" />
                    @endif
                </li>
            @empty
                <li class="px-4 py-3 text-xs text-muted">{{ __('Aucun document (contrat, cahier des charges, PV d\'adjudication…).') }}</li>
            @endforelse
        </ul>
    </x-card>

    @if ($dialog)
        <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" x-data x-on:keydown.escape.window="$wire.set('dialog', null)">
            <div class="absolute inset-0 bg-navy/40 backdrop-blur-[2px]" wire:click="$set('dialog', null)"></div>
            <div class="relative w-full max-w-md rounded-t-[24px] bg-white p-5 sm:rounded-[24px]" style="box-shadow: var(--shadow-lg)">
                <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-line sm:hidden" aria-hidden="true"></div>
                @if ($dialog === 'renew')
                    <h2 class="mb-3 text-[17px] font-semibold">{{ __('Prolonger le contrat') }}</h2>
                    <form wire:submit="renew" class="space-y-3">
                        <p class="text-sm text-muted">{{ __('Nouvelle période') }} : <strong class="text-ink">{{ \Illuminate\Support\Carbon::parse($renewal['date_debut'])->format('d/m/Y') }} → {{ \Illuminate\Support\Carbon::parse($renewal['date_fin'])->format('d/m/Y') }}</strong></p>
                        <p class="text-sm text-muted">{{ __('Redevance actuelle') }} : <strong class="tabular-nums text-ink">{{ number_format($renewal['actuelle'], 2, ',', ' ') }} {{ __('DH/mois') }}</strong></p>
                        <div class="grid grid-cols-2 gap-3">
                            <x-field :label="__('Augmentation (%)')" for="r-rate" error="renewal.taux"><x-input id="r-rate" type="number" step="0.01" wire:model.live.debounce.300ms="renewal.taux" /></x-field>
                            <x-field :label="__('Nouvelle redevance (DH/mois)')" for="r-fee" error="renewal.redevance"><x-input id="r-fee" type="number" step="0.01" min="0" wire:model="renewal.redevance" /></x-field>
                        </div>
                        <x-field :label="__('Observation')" for="r-notes" error="renewal.notes"><x-input id="r-notes" wire:model="renewal.notes" :placeholder="__('Accord du locataire, n° de décision…')" /></x-field>
                        <p class="text-xs text-muted">{{ __('L’augmentation proposée est celle du contrat ; vous pouvez la modifier ou saisir directement la nouvelle redevance.') }}</p>
                        <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('dialog', null)">{{ __('Fermer') }}</x-btn><x-btn type="submit" variant="gold" icon="refresh-cw">{{ __('Prolonger') }}</x-btn></div>
                    </form>
                @elseif ($dialog === 'terminate')
                    <h2 class="mb-3 text-[17px] font-semibold">{{ __('Résilier le contrat') }}</h2>
                    <form wire:submit="terminate" class="space-y-3">
                        <p class="text-sm text-muted">{{ __('Les mois après la date de résiliation ne seront plus dus. Les mois antérieurs impayés restent dus.') }}</p>
                        <x-field :label="__('Date de résiliation')" for="t-on" error="terminateOn"><x-input id="t-on" type="date" wire:model="terminateOn" /></x-field>
                        <x-field :label="__('Motif')" for="t-reason" error="terminateReason"><x-input id="t-reason" wire:model="terminateReason" /></x-field>
                        <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('dialog', null)">{{ __('Fermer') }}</x-btn><x-btn type="submit" variant="danger" icon="circle-x">{{ __('Résilier') }}</x-btn></div>
                    </form>
                @else
                    <h2 class="mb-3 text-[17px] font-semibold">{{ __('Téléverser un document') }}</h2>
                    <form wire:submit="saveDocument" class="space-y-3">
                        <x-field :label="__('Fichier (PDF, image, Word, Excel — 10 Mo max.)')" for="d-file" error="upload">
                            <input id="d-file" type="file" wire:model="upload" class="block w-full text-sm file:me-3 file:rounded-lg file:border-0 file:bg-bg file:px-3 file:py-2 file:text-sm file:font-semibold">
                        </x-field>
                        <x-field :label="__('Type')" for="d-type" error="documentType">
                            <x-select id="d-type" wire:model="documentType">@foreach (\App\Livewire\Souk\Fiche::DOCUMENT_TYPES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</x-select>
                        </x-field>
                        <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('dialog', null)">{{ __('Fermer') }}</x-btn><x-btn type="submit" icon="upload">{{ __('Téléverser') }}</x-btn></div>
                    </form>
                @endif
            </div>
        </div>
    @endif
</div>
