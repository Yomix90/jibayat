@php use App\Support\Format; use App\Domain\Tnb\Services\TnbCalculator; $t = $parcel->taxpayer; @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" :title="'Terrain n° '.$parcel->number.($file ? ' — dossier '.$file : '')" :subtitle="$t?->displayName()">
        <x-slot:actions>
            @if ($this->allowed('bulletins.creer') && count($situation['not_declared']) && $parcel->is_active && ! $parcel->is_census)
                <x-btn variant="gold" icon="hand-coins" :href="route('tnb.encaisser', ['terrain' => $parcel->id])">{{ __('Encaisser') }}</x-btn>
            @endif
            <x-btn variant="secondary" icon="mail-warning" :href="route('tnb.avis-imprimer', $parcel)" target="_blank">{{ __('Avis de non-paiement') }}</x-btn>
            @if ($this->allowed('tnb.modifier'))<x-btn variant="secondary" icon="pencil" wire:click="editParcel({{ $parcel->id }})">{{ __('Modifier') }}</x-btn>@endif
            <x-btn variant="ghost" icon="chevron-left" wire:click="$set('parcelId', null)">{{ __('Autre terrain') }}</x-btn>
        </x-slot:actions>
    </x-page-header>

    @if ($alert)
        <div class="flex items-center gap-2 rounded-2xl border border-accent/40 bg-accent/10 px-4 py-3 text-sm" role="alert">
            <x-svg-icon name="triangle-alert" class="h-4 w-4 text-accent" />
            L'exonération (permis de construire) se termine fin {{ $alert->exemption_end_year }} : le terrain redeviendra imposable.
        </div>
    @endif
    @if ($parcel->is_archived || ! $parcel->is_active || $parcel->is_census)
        <div class="rounded-2xl border border-line bg-bg px-4 py-3 text-sm text-muted">
            {{ ! $parcel->is_active ? 'Terrain supprimé de la liste.' : ($parcel->is_archived ? 'Terrain archivé.' : 'Terrain au recensement : pas encore de dossier officiel.') }}
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card :title="__('Redevable')">
            <dl class="space-y-1.5 text-sm">
                <div><dt class="text-xs text-muted">{{ __('Nom') }}</dt><dd class="font-semibold text-ink">{{ $t?->displayName() }}</dd></div>
                @if ($t?->displayNameAr())<div><dd class="font-ar text-ink" lang="ar" dir="rtl">{{ $t->displayNameAr() }}</dd></div>@endif
                <div class="grid grid-cols-2 gap-2">
                    <div><dt class="text-xs text-muted">{{ __('CIN / RC') }}</dt><dd>{{ $t?->cin ?: ($t?->rc ?: '—') }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('Téléphone') }}</dt><dd>{{ $t?->phone ?: '—' }}</dd></div>
                </div>
                <div><dt class="text-xs text-muted">{{ __('Adresse') }}</dt><dd>{{ $t?->address ?: '—' }}</dd></div>
            </dl>
        </x-card>

        <x-card :title="__('Terrain')">
            <dl class="space-y-1.5 text-sm">
                <div><dt class="text-xs text-muted">{{ __('Situation') }}</dt><dd class="font-semibold text-ink">{{ $parcel->locationLabel() }}</dd></div>
                @if ($parcel->address_ar)<div><dd class="font-ar" lang="ar" dir="rtl">{{ $parcel->address_ar }}</dd></div>@endif
                <div class="grid grid-cols-3 gap-2">
                    <div><dt class="text-xs text-muted">{{ __('Superficie') }}</dt><dd class="font-bold">{{ Format::money($parcel->area) }} m²</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('Zone') }}</dt><dd>{{ $parcel->zone }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('Statut') }}</dt><dd>{{ $statuses[$parcel->status] ?? $parcel->status }}</dd></div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div><dt class="text-xs text-muted">{{ __('Acquisition') }}</dt><dd>{{ $parcel->acquired_on?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-muted">{{ __('Imposable depuis') }}</dt><dd>{{ TnbCalculator::startYear($parcel) }}</dd></div>
                </div>
            </dl>
        </x-card>

        <x-card :title="__('Situation')">
            @if (! $years)
                <x-empty-state icon="check" :title="__('Terrain à jour')" />
            @else
                <p class="text-2xl font-bold tabular-nums text-red-600">{{ Format::money($situation['total_due']) }} DH</p>
                <p class="text-sm text-ink">{{ TnbCalculator::periodLabel($years) }}</p>
                <p class="font-ar text-sm text-muted" lang="ar" dir="rtl">{{ TnbCalculator::periodLabel($years, true) }}</p>
                <div class="mt-2 flex flex-wrap gap-1">
                    @if (count($situation['not_declared']))<x-badge color="red">{{ count($situation['not_declared']) }} an(s) à déclarer · {{ Format::money($situation['total_not_declared']) }} DH</x-badge>@endif
                    @if (count($situation['pending']))<x-badge color="gold">{{ count($situation['pending']) }} an(s) émis</x-badge>@endif
                </div>
            @endif
            @if ($exempt)<p class="mt-2 text-xs text-muted">Années exonérées : {{ implode(', ', $exempt) }}</p>@endif
        </x-card>
    </div>

    @include('livewire.tnb.partials.declarations', ['history' => $history, 'cancellable' => true])

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Permis (exonération)')" :padding="false">
            @if ($this->allowed('tnb.modifier'))<x-slot:actions><x-btn variant="secondary" size="sm" icon="plus" wire:click="addPermit">{{ __('Permis') }}</x-btn></x-slot:actions> @endif
            <ul class="divide-y divide-line text-sm">
                @forelse ($permits as $p)
                    <li class="px-4 py-2.5">
                        <p class="font-semibold">{{ ['construction' => 'Permis de construire', 'habiter' => "Permis d'habiter", 'demolition' => 'Permis de démolir'][$p->type] ?? ucfirst((string) $p->type) }}
                            {{ $p->number ? 'n° '.$p->number : '' }}</p>
                        <p class="text-xs text-muted">Autorisé le {{ $p->authorized_on?->format('d/m/Y') ?? '—' }}
                            @if ($p->exemption_end_year) · exonération {{ $p->exemption_start_year }}–{{ $p->exemption_end_year }}@endif
                            {{ $p->description ? '· '.$p->description : '' }}</p>
                    </li>
                @empty
                    <li class="px-4 py-3 text-xs text-muted">{{ __('Aucun permis. Un permis de construire exonère 3 ans à partir de l\'année d\'autorisation.') }}</li>
                @endforelse
            </ul>
        </x-card>

        <x-card :title="__('Copropriétaires (indivision)')" :padding="false">
            @if ($this->allowed('tnb.modifier'))<x-slot:actions><x-btn variant="secondary" size="sm" icon="plus" wire:click="editCoOwner">{{ __('Copropriétaire') }}</x-btn></x-slot:actions> @endif
            <ul class="divide-y divide-line text-sm">
                @forelse ($coOwners as $c)
                    <li class="flex items-center gap-2 px-4 py-2.5" wire:key="co-{{ $c->id }}">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $c->taxpayer?->displayName() }} <span class="text-xs font-normal text-muted">{{ $c->taxpayer?->cin }}</span></p>
                            <p class="text-xs text-muted">{{ ucfirst($c->title_type) }}{{ $c->joined_on ? ' · depuis le '.$c->joined_on->format('d/m/Y') : '' }}{{ $c->notarial_deed ? ' · acte '.$c->notarial_deed : '' }}</p>
                        </div>
                        <x-badge color="blue">{{ rtrim(rtrim(number_format((float) $c->share_percent, 2, ',', ''), '0'), ',') }} %</x-badge>
                        @if ($this->allowed('tnb.modifier'))
                            <x-btn variant="ghost" size="sm" icon="pencil" wire:click="editCoOwner({{ $c->id }})" :aria-label="__('Modifier la part')" />
                            <x-btn variant="ghost" size="sm" icon="trash-2" wire:click="removeCoOwner({{ $c->id }})" wire:confirm="Retirer ce copropriétaire ?" :aria-label="__('Retirer')" />
                        @endif
                    </li>
                @empty
                    <li class="px-4 py-3 text-xs text-muted">{{ __('Aucun copropriétaire.') }}</li>
                @endforelse
            </ul>
        </x-card>

        <x-card :title="__('Transferts de propriété')" :padding="false">
            @if ($this->allowed('tnb.modifier') && $parcel->is_active)<x-slot:actions><x-btn variant="secondary" size="sm" icon="arrow-right-left" wire:click="startTransfer">{{ __('Transférer') }}</x-btn></x-slot:actions> @endif
            <ul class="divide-y divide-line text-sm">
                @forelse ($transfers as $tr)
                    <li class="px-4 py-2.5">
                        <p><span class="text-muted">{{ $tr->fromTaxpayer?->displayName() ?? '—' }}</span> → <strong>{{ $tr->toTaxpayer?->displayName() ?? '—' }}</strong></p>
                        <p class="text-xs text-muted">{{ $tr->transferred_on?->format('d/m/Y') }}{{ $tr->reason ? ' · '.$tr->reason : '' }}{{ $tr->notarial_deed ? ' · acte '.$tr->notarial_deed : '' }}</p>
                    </li>
                @empty
                    <li class="px-4 py-3 text-xs text-muted">{{ __('Aucun transfert.') }}</li>
                @endforelse
            </ul>
        </x-card>

        <x-card :title="__('Documents')" :padding="false">
            @if ($this->allowed('tnb.modifier'))<x-slot:actions><x-btn variant="secondary" size="sm" icon="upload" wire:click="addDocument">{{ __('Téléverser') }}</x-btn></x-slot:actions> @endif
            <ul class="divide-y divide-line text-sm">
                @forelse ($documents as $doc)
                    <li class="flex items-center gap-2 px-4 py-2.5" wire:key="doc-{{ $doc->id }}">
                        <x-svg-icon name="file" class="h-4 w-4 text-muted" />
                        <a href="{{ route('tnb.document', $doc) }}" class="min-w-0 flex-1 truncate font-semibold text-ink hover:underline">{{ $doc->file_name }}</a>
                        <span class="text-xs text-muted">{{ \App\Livewire\Tnb\Fiche::DOCUMENT_TYPES[$doc->type] ?? $doc->type }} · {{ $doc->created_at?->format('d/m/Y') }}</span>
                        @if ($this->allowed('tnb.modifier'))
                            <x-btn variant="ghost" size="sm" icon="trash-2" wire:click="deleteDocument({{ $doc->id }})" wire:confirm="Supprimer ce document ?" :aria-label="__('Supprimer le document')" />
                        @endif
                    </li>
                @empty
                    <li class="px-4 py-3 text-xs text-muted">{{ __('Aucun document.') }}</li>
                @endforelse
            </ul>
        </x-card>
    </div>

    @if ($others->count())
        <x-card :title="__('Autres terrains du dossier')" :padding="false">
            <ul class="divide-y divide-line text-sm">
                @foreach ($others as $o)
                    <li><a href="{{ route('tnb.fiche', ['terrain' => $o->id]) }}" wire:navigate class="flex items-center gap-3 px-4 py-2.5 hover:bg-bg">
                        <span class="font-mono text-xs font-bold">T {{ $o->number }}</span><span class="flex-1 truncate">{{ $o->locationLabel() }}</span>
                        <span class="text-xs text-muted">{{ Format::money($o->area) }} m² · zone {{ $o->zone }}</span></a></li>
                @endforeach
            </ul>
        </x-card>
    @endif

    @if ($this->allowed('tnb.supprimer') && $parcel->is_active)
        <div class="flex justify-end">
            <x-btn variant="ghost" size="sm" icon="archive" wire:click="archive" wire:confirm="{{ $parcel->is_archived ? 'Désarchiver ce terrain ?' : 'Archiver ce terrain ? Il quittera la liste TNB.' }}">
                {{ $parcel->is_archived ? 'Désarchiver' : 'Archiver le terrain' }}</x-btn>
        </div>
    @endif

    {{-- Dialogues --}}
    @include('livewire.tnb.partials.parcel-form')

    @if ($dialog)
        @php($titles = ['permit' => 'Nouveau permis', 'coowner' => 'Copropriétaire', 'transfer' => 'Transférer le terrain', 'document' => 'Téléverser un document', 'cancel' => 'Annuler la déclaration'])
        <div class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center" role="dialog" aria-modal="true" aria-label="{{ $titles[$dialog] }}" x-data x-on:keydown.escape.window="$wire.set('dialog', null)">
                <div class="absolute inset-0 bg-navy/50" wire:click="$set('dialog', null)"></div>
                <div class="relative max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-t-[24px] bg-white p-5 shadow-2xl sm:rounded-[24px]">
                    <div class="mb-4 flex items-center justify-between gap-2">
                        <h2 class="text-base font-bold text-ink">{{ $titles[$dialog] }}</h2>
                        <button type="button" class="round-btn !h-8 !w-8 text-muted hover:bg-bg" wire:click="$set('dialog', null)" aria-label="{{ __('Fermer') }}"><x-svg-icon name="x" class="h-4 w-4" /></button>
                    </div>
                    <div>
                        @if ($dialog === 'permit')
                            <form wire:submit="savePermit" class="space-y-3">
                                <x-field :label="__('Type')" for="pm-type" error="permit.type">
                                    <x-select id="pm-type" wire:model.live="permit.type">
                                        <option value="construction">{{ __('Permis de construire (exonère 3 ans)') }}</option><option value="habiter">{{ __('Permis d\'habiter (terrain bâti)') }}</option>
                                        <option value="demolition">{{ __('Permis de démolir') }}</option><option value="autre">{{ __('Autre') }}</option>
                                    </x-select>
                                </x-field>
                                <div class="grid grid-cols-2 gap-2">
                                    <x-field :label="__('N° du permis')" for="pm-num" error="permit.number"><x-input id="pm-num" wire:model="permit.number" /></x-field>
                                    <x-field :label="__('Date d\'autorisation')" for="pm-date" error="permit.authorized_on"><x-input id="pm-date" type="date" wire:model="permit.authorized_on" /></x-field>
                                </div>
                                <x-field :label="__('Description')" for="pm-desc" error="permit.description"><x-input id="pm-desc" wire:model="permit.description" /></x-field>
                                <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('dialog', null)">{{ __('Annuler') }}</x-btn><x-btn type="submit" icon="check">{{ __('Enregistrer') }}</x-btn></div>
                            </form>
                        @elseif ($dialog === 'coowner' || $dialog === 'transfer')
                            @php($key = $dialog === 'coowner' ? 'coOwner.taxpayer_id' : 'transfer.new_taxpayer_id')
                            <form wire:submit="{{ $dialog === 'coowner' ? 'saveCoOwner' : 'saveTransfer' }}" class="space-y-3">
                                <x-field :label="$dialog === 'coowner' ? 'Contribuable' : 'Nouveau propriétaire'" for="tp-sel" :error="$key">
                                    <div class="grid gap-2">
                                        <x-input type="search" wire:model.live.debounce.300ms="taxpayerSearch" :placeholder="__('Chercher nom, CIN…')" :aria-label="__('Chercher un contribuable')" />
                                        <x-select id="tp-sel" wire:model="{{ $key }}">
                                            <option value="">{{ __('— Choisir —') }}</option>
                                            @foreach ($taxpayers as $tp)<option value="{{ $tp->id }}">{{ $tp->displayName() }}{{ $tp->cin ? ' · '.$tp->cin : '' }}</option>@endforeach
                                        </x-select>
                                    </div>
                                </x-field>
                                @if ($dialog === 'coowner')
                                    <div class="grid grid-cols-2 gap-2">
                                        <x-field :label="__('Part (%)')" for="co-share" error="coOwner.share_percent"><x-input id="co-share" type="number" step="0.01" min="0" max="100" wire:model="coOwner.share_percent" /></x-field>
                                        <x-field :label="__('Type de titre')" for="co-type" error="coOwner.title_type">
                                            <x-select id="co-type" wire:model="coOwner.title_type">
                                                <option value="indivision">{{ __('Indivision') }}</option><option value="heritage">{{ __('Héritage') }}</option><option value="achat">{{ __('Achat') }}</option><option value="donation">{{ __('Donation') }}</option><option value="autre">{{ __('Autre') }}</option>
                                            </x-select>
                                        </x-field>
                                        <x-field :label="__('Date d\'entrée')" for="co-date" error="coOwner.joined_on"><x-input id="co-date" type="date" wire:model="coOwner.joined_on" /></x-field>
                                        <x-field :label="__('Acte notarié')" for="co-deed" error="coOwner.notarial_deed"><x-input id="co-deed" wire:model="coOwner.notarial_deed" /></x-field>
                                    </div>
                                    <x-field :label="__('Notes')" for="co-notes" error="coOwner.notes"><x-input id="co-notes" wire:model="coOwner.notes" /></x-field>
                                @else
                                    <div class="grid grid-cols-2 gap-2">
                                        <x-field :label="__('Date du transfert')" for="tr-date" error="transfer.transferred_on"><x-input id="tr-date" type="date" wire:model="transfer.transferred_on" /></x-field>
                                        <x-field :label="__('Acte notarié')" for="tr-deed" error="transfer.notarial_deed"><x-input id="tr-deed" wire:model="transfer.notarial_deed" /></x-field>
                                    </div>
                                    <x-field :label="__('Motif')" for="tr-reason" error="transfer.reason" :hint="__('Le terrain rejoint le dossier du nouveau propriétaire (créé s\'il n\'existe pas).')">
                                        <x-input id="tr-reason" wire:model="transfer.reason" :placeholder="__('Vente, héritage…')" />
                                    </x-field>
                                @endif
                                <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('dialog', null)">{{ __('Annuler') }}</x-btn><x-btn type="submit" icon="check">{{ __('Enregistrer') }}</x-btn></div>
                            </form>
                        @elseif ($dialog === 'document')
                            <form wire:submit="saveDocument" class="space-y-3">
                                <x-field :label="__('Fichier (PDF, image, Word, Excel — 10 Mo max.)')" for="doc-file" error="upload">
                                    <input id="doc-file" type="file" wire:model="upload" class="block w-full text-sm file:me-3 file:rounded-lg file:border-0 file:bg-bg file:px-3 file:py-2 file:text-sm file:font-semibold">
                                </x-field>
                                <x-field :label="__('Type')" for="doc-type" error="documentType">
                                    <x-select id="doc-type" wire:model="documentType">@foreach (\App\Livewire\Tnb\Fiche::DOCUMENT_TYPES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</x-select>
                                </x-field>
                                <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('dialog', null)">{{ __('Annuler') }}</x-btn><x-btn type="submit" icon="upload">{{ __('Téléverser') }}</x-btn></div>
                            </form>
                        @elseif ($dialog === 'cancel')
                            <form wire:submit="confirmCancel" class="space-y-3">
                                <p class="text-sm text-muted">{{ __('La déclaration et son bulletin non payé sont annulés (ils restent visibles dans « Pièces annulées »). L\'année redevient à déclarer.') }}</p>
                                <x-field :label="__('Motif de l\'annulation')" for="cn-reason" error="cancelReason"><x-input id="cn-reason" wire:model="cancelReason" /></x-field>
                                <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('dialog', null)">{{ __('Fermer') }}</x-btn><x-btn type="submit" variant="danger" icon="ban">{{ __('Annuler la déclaration') }}</x-btn></div>
                            </form>
                        @endif
                    </div>
                </div>
        </div>
    @endif
</div>
