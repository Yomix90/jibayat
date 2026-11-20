@php
    use App\Support\Format;
    use App\Domain\Documents\Services\RegisterService;
    $modName = fn ($m) => RegisterService::MODULES[$m][app()->isLocale('ar') ? 'ar' : 'fr'] ?? $m;
    $statusBadge = ['paye' => ['Payé', 'green'], 'emis' => ['Émis', 'gold'], 'en_attente' => ['En attente', 'gold'], 'valide' => ['Validé', 'green'],
        'encaisse' => ['Encaissé', 'green'], 'annule' => ['Annulé', 'red'], 'rejete' => ['Rejeté', 'red'], 'sous_seuil' => ['Sous seuil', null]];
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        @if ($t)
            <x-slot:actions>
                <x-btn variant="secondary" icon="mail-warning" :href="route('caisse.contribuable-avis', $t)" target="_blank">{{ __('Avis de non-paiement') }}</x-btn>
                @if ($this->allowed('contribuables.modifier'))<x-btn icon="pencil" wire:click="edit">{{ __('Modifier') }}</x-btn>@endif
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="relative max-w-xl">
        <x-input type="search" wire:model.live.debounce.300ms="search" :placeholder="__('Chercher un autre contribuable : nom, CIN, n°…')" :aria-label="__('Chercher un contribuable')" />
        @if ($results->count())
            <ul class="surface absolute inset-x-0 top-full z-20 mt-1 max-h-80 divide-y divide-line overflow-y-auto">
                @foreach ($results as $r)
                    <li><button type="button" wire:click="select({{ $r->id }})" class="w-full px-4 py-2 text-start hover:bg-bg">
                        <span class="font-semibold">{{ $r->displayName() }}</span> <span class="text-xs text-muted">{{ $r->number }} @if ($r->cin)· {{ $r->cin }}@endif</span></button></li>
                @endforeach
            </ul>
        @endif
    </div>

    @if (! $t)
        <x-card><x-empty-state icon="id-card" :title="__('Choisissez un contribuable')">{{ __('Utilisez la recherche ci-dessus ou ouvrez une fiche depuis le répertoire.') }}</x-empty-state></x-card>
    @else
        <section class="surface relative overflow-hidden p-5" aria-label="{{ __('Identité') }}">
            <div class="flex flex-wrap items-start gap-4">
                <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-navy text-lg font-bold text-gold">{{ mb_strtoupper(mb_substr($t->displayName(), 0, 1)) }}</span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-xl font-bold">{{ $t->displayName() }} @if (! $t->is_active)<x-badge color="red">{{ __('Retiré du répertoire') }}</x-badge>@endif</h2>
                    @if ($t->displayNameAr())<p class="text-lg text-muted" dir="rtl">{{ $t->displayNameAr() }}</p>@endif
                    <p class="mt-1 text-sm text-muted"><span class="font-mono">{{ $t->number }}</span> · {{ $t->person_type === 'morale' ? 'Personne morale' : 'Personne physique' }}</p>
                </div>
                <div class="text-end"><p class="text-xs font-semibold text-muted">{{ __('Reste à payer') }}</p>
                    <p class="text-2xl font-bold tabular-nums {{ $total_unpaid > 0 ? 'text-red-600' : 'text-online' }}">{{ Format::money($total_unpaid) }} DH</p>
                    <p class="text-xs text-muted">Encaissé (30 derniers bulletins) : {{ Format::money($total_collected) }} DH</p></div>
            </div>
            <dl class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                @foreach (['CIN' => $t->cin, 'ICE' => $t->ice, 'RC' => $t->rc, 'Téléphone' => $t->phone, 'E-mail' => $t->email,
                           'Adresse' => trim(($t->address ?? '').' '.($t->postal_code ?? '').' '.($t->city ?? '')), 'العنوان' => $t->address_ar] as $l => $v)
                    @if ($v)<div><dt class="text-xs text-muted">{{ tr($l) }}</dt><dd class="font-semibold" @if ($l === 'العنوان') dir="rtl" @endif>{{ $v }}</dd></div>@endif
                @endforeach
            </dl>
        </section>

        <div class="grid gap-6 lg:grid-cols-3">
            <x-card :title="__('Taxes et dossiers')">
                <ul class="space-y-2 text-sm">
                    @forelse ($modules as $m)
                        <li class="flex items-center justify-between gap-2"><span>{{ $modName($m['code']) }}</span>
                            <span class="flex items-center gap-2"><x-badge>{{ $m['nb'] }} dossier(s)</x-badge>
                                @isset($unpaid[$m['code']])<span class="font-semibold tabular-nums text-red-600">{{ Format::money($unpaid[$m['code']]['montant']) }}</span>@endisset</span></li>
                    @empty
                        <li class="text-xs text-muted">{{ __('Aucun dossier dans les taxes.') }}</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card :padding="false" class="lg:col-span-2">
                <x-slot:title>Documents ({{ $documents->count() }})</x-slot:title>
                @if ($this->allowed('contribuables.modifier'))<x-slot:actions><x-btn variant="secondary" size="sm" icon="upload" wire:click="addDocument">{{ __('Ajouter') }}</x-btn></x-slot:actions> @endif
                <ul class="divide-y divide-line text-sm">
                    @forelse ($documents as $d)
                        <li class="flex items-center gap-3 px-4 py-2.5" wire:key="doc-{{ $d->id }}">
                            <x-svg-icon name="file-text" class="h-4 w-4 text-muted" />
                            <a href="{{ route('caisse.contribuable-document', $d) }}" class="min-w-0 flex-1 truncate font-semibold hover:underline">{{ $d->file_name }}</a>
                            <x-badge>{{ \App\Livewire\Caisse\FicheContribuable::DOCUMENT_TYPES[$d->type] ?? $d->type }}</x-badge>
                            <span class="text-xs text-muted">{{ $d->created_at?->format('d/m/Y') }}</span>
                            @if ($this->allowed('contribuables.supprimer'))<x-btn variant="ghost" size="sm" icon="trash-2" wire:click="deleteDocument({{ $d->id }})" wire:confirm="Supprimer le document {{ $d->file_name }} ?" aria-label="Supprimer {{ $d->file_name }}" />@endif
                        </li>
                    @empty
                        <li class="px-4 py-3 text-xs text-muted">{{ __('Aucun document.') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>

        <x-card :title="__('Déclarations (50 dernières)')" :padding="false">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-bg text-[11px] font-bold uppercase tracking-[.06em] text-muted">
                        <tr><th class="px-4 py-2 text-start">{{ __('Déclaration') }}</th><th class="px-4 py-2 text-start">{{ __('Taxe') }}</th><th class="px-4 py-2 text-start">{{ __('Période') }}</th>
                            <th class="px-4 py-2 text-end">{{ __('Montant') }}</th><th class="px-4 py-2 text-start">{{ __('Bulletin') }}</th><th class="px-4 py-2 text-start">{{ __('Statut') }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($declarations as $d)
                            @php([$sl, $sc] = $statusBadge[$d->cancelled_at ? 'annule' : $d->status] ?? [$d->status, null])
                            <tr><td class="px-4 py-2 font-mono text-xs font-semibold">{{ $d->number }}</td><td class="px-4 py-2 text-xs">{{ $modName($d->module) }}</td>
                                <td class="px-4 py-2 text-xs">{{ $d->year }}{{ $d->quarter ? ' · T'.$d->quarter : '' }}</td>
                                <td class="px-4 py-2 text-end font-semibold tabular-nums">{{ Format::money($d->total) }}</td>
                                <td class="px-4 py-2 text-xs">{{ $d->slip_number ?? '—' }}</td><td class="px-4 py-2"><x-badge :color="$sc">{{ tr($sl) }}</x-badge></td></tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-3 text-xs text-muted">{{ __('Aucune déclaration.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card :title="__('Bulletins (30 derniers)')" :padding="false">
            <ul class="divide-y divide-line text-sm">
                @forelse ($slips as $s)
                    @php([$sl, $sc] = $statusBadge[$s->cancelled_at ? 'annule' : $s->status] ?? [$s->status, null])
                    <li class="flex flex-wrap items-center gap-3 px-4 py-2.5"><span class="font-mono text-xs font-bold">{{ $s->slip_number }}</span>
                        <span class="flex-1 text-xs text-muted">{{ $modName($s->module) }} {{ $s->year }} · {{ $s->paid_on ? \Illuminate\Support\Carbon::parse($s->paid_on)->format('d/m/Y') : '' }}@if ($s->receipt_number) · quittance {{ $s->receipt_number }}@endif</span>
                        <span class="font-semibold tabular-nums">{{ Format::money($s->amount) }}</span><x-badge :color="$sc">{{ tr($sl) }}</x-badge></li>
                @empty
                    <li class="px-4 py-3 text-xs text-muted">{{ __('Aucun bulletin.') }}</li>
                @endforelse
            </ul>
        </x-card>
    @endif

    <x-panel show="showEdit" :title="__('Modifier le contribuable')" max-width="max-w-3xl">
        <form wire:submit="update">
            @include('livewire.caisse.partials.taxpayer-form')
            <div class="mt-5 flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('showEdit', false)">{{ __('Annuler') }}</x-btn><x-btn type="submit" variant="gold" icon="check">{{ __('Enregistrer') }}</x-btn></div>
        </form>
    </x-panel>

    <x-panel show="showDocument" :title="__('Ajouter un document')">
        <form wire:submit="saveDocument" class="space-y-3">
            <x-field :label="__('Type')" for="d-type" error="documentType"><x-select id="d-type" wire:model="documentType">@foreach (\App\Livewire\Caisse\FicheContribuable::DOCUMENT_TYPES as $k => $l)<option value="{{ $k }}">{{ tr($l) }}</option>@endforeach</x-select></x-field>
            <x-field :label="__('Fichier (PDF, image, Word, Excel — 10 Mo max.)')" for="d-file" error="upload">
                <input id="d-file" type="file" wire:model="upload" class="block w-full text-sm file:me-3 file:rounded-full file:border-0 file:bg-navy file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
            </x-field>
            <div class="flex justify-end gap-2"><x-btn variant="secondary" wire:click="$set('showDocument', false)">{{ __('Annuler') }}</x-btn><x-btn type="submit" variant="gold" icon="upload" wire:loading.attr="disabled" wire:target="upload,saveDocument">{{ __('Ajouter') }}</x-btn></div>
        </form>
    </x-panel>
</div>
