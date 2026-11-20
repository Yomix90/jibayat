@php use App\Domain\Import\ExcelImportService; $canWrite = $this->allowed('importation.ajouter'); @endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()">
        <x-slot:actions><x-btn variant="secondary" icon="history" :href="route('parametres.imports')">{{ __('Journal des imports') }}</x-btn></x-slot:actions>
    </x-page-header>

    <div class="flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="{{ __('Type de données') }}">
        @foreach (ExcelImportService::MODULES as $code => $m)
            <button type="button" role="tab" aria-selected="{{ $module === $code ? 'true' : 'false' }}" wire:click="$set('module', '{{ $code }}')"
                    class="pressable shrink-0 rounded-2xl border px-4 py-2 text-start {{ $module === $code ? 'border-gold bg-navy text-white' : 'border-line bg-white' }}">
                <span class="block text-sm font-semibold">{{ tr($m['nom']) }}</span>
                <span class="block text-xs {{ $module === $code ? 'text-white/70' : 'text-muted' }}">{{ $stats[$code] ?? 0 }} en base</span>
            </button>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2" :title="__('1. Préparer le fichier')">
            <p class="text-sm text-muted">{{ __('Téléchargez le modèle') }} <strong>{{ tr($cfg['nom']) }}</strong> {{ __(': il contient les colonnes attendues, des exemples et des listes déroulantes.') }}
                @if ($cfg['periodicite'] !== 'aucune') Indiquez la période de début d'imposition ({{ $cfg['periodicite'] === 'annuelle' ? 'année' : 'trimestre et année' }}) : les impayés seront calculés à partir d'elle. @endif
                {{ __('Les contribuables sont retrouvés par CIN, ICE ou nom + prénom, sinon créés.') }}</p>
            <x-btn variant="secondary" icon="file-down" class="mt-3" :href="route('parametres.importation-modele', $module)">{{ __('Télécharger le modèle') }}</x-btn>
            <div class="mt-4 overflow-x-auto"><table class="w-full text-xs">
                <thead class="bg-bg font-bold uppercase tracking-[.06em] text-muted"><tr><th class="px-3 py-1.5 text-start">{{ __('Colonne') }}</th><th class="px-3 py-1.5 text-start">{{ __('Description') }}</th></tr></thead>
                <tbody class="divide-y divide-line">@foreach ($cfg['colonnes'] as [$f, $label, $req, $desc])<tr><td class="px-3 py-1.5 font-semibold {{ $req ? 'text-ink' : 'text-muted' }}">{{ tr($label) }}</td><td class="px-3 py-1.5 text-muted">{{ $desc }}</td></tr>@endforeach</tbody>
            </table></div>
        </x-card>
        <x-card :title="'Tarifs de l’arrêté '.($order->number ?? '—')">
            <ul class="space-y-1.5 text-sm">
                @forelse ($tariffs as $t)
                    <li class="flex justify-between gap-2"><span>{{ $t->label }}</span><span class="font-semibold tabular-nums">{{ rtrim(rtrim(number_format((float) $t->value, 4, ',', ' '), '0'), ',') }} {{ $t->unit }}</span></li>
                @empty
                    <li class="text-xs text-muted">{{ $module === 'CONTRIBUABLES' ? 'Pas de tarif pour les contribuables.' : 'Aucun tarif de ce module dans l’arrêté en vigueur.' }}</li>
                @endforelse
            </ul>
        </x-card>
    </div>

    @if ($canWrite)
        <x-card :title="__('2. Charger et vérifier')">
            <form wire:submit="analyse" class="grid items-end gap-3 sm:grid-cols-[1fr_auto]">
                <x-field :label="__('Fichier Excel (.xlsx)')" for="imp-file" error="file">
                    <input id="imp-file" type="file" wire:model="file" accept=".xlsx,.xlsm" class="block w-full text-sm file:me-3 file:rounded-full file:border-0 file:bg-navy file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white">
                </x-field>
                <x-btn type="submit" variant="secondary" icon="search-check" wire:loading.attr="disabled" wire:target="file,analyse">{{ __('Aperçu') }}</x-btn>
            </form>
        </x-card>
    @endif

    @if ($preview)
        <x-card :padding="false">
            <x-slot:title>3. Aperçu : {{ $preview['total'] }} ligne(s), {{ $preview['valides'] }} valide(s)</x-slot:title>
            <x-slot:actions>
                <x-btn variant="gold" size="sm" icon="upload" wire:click="import" wire:loading.attr="disabled"
                       wire:confirm="Importer {{ $preview['valides'] }} ligne(s) valide(s) ? Les lignes en erreur seront ignorées et notées au journal.">{{ __('Importer') }}</x-btn>
            </x-slot:actions>
            <ul class="divide-y divide-line text-sm">
                @foreach ($preview['rows'] as $r)
                    <li class="flex flex-wrap items-start gap-3 px-4 py-2.5">
                        <span class="w-14 text-xs text-muted">Ligne {{ $r['_ligne_excel'] }}</span>
                        <span class="min-w-0 flex-1"><span class="font-semibold">{{ trim(($r['nom'] ?? '').' '.($r['prenom'] ?? '')) ?: '—' }}</span>
                            <span class="block text-xs text-muted">{{ $r['_ctb_statut'] }}@if ($module !== 'CONTRIBUABLES') · tarif : {{ $r['_tarif_applicable'] }}@endif</span>
                            @foreach ($r['_erreurs'] as $e)<span class="block text-xs font-semibold text-red-600">{{ $e }}</span>@endforeach</span>
                        <x-badge :color="$r['_est_valide'] ? 'green' : 'red'">{{ $r['_est_valide'] ? 'Valide' : 'Erreur' }}</x-badge>
                    </li>
                @endforeach
            </ul>
            @if ($preview['total'] > 50)<p class="border-t border-line px-4 py-2 text-xs text-muted">{{ __('Seules les 50 premières lignes sont affichées.') }}</p>@endif
        </x-card>
    @endif

    @if ($result)
        <x-card :title="__('Résultat de l\'import')">
            <p class="text-sm"><strong>{{ $result['success'] }}</strong> / {{ $result['total'] }} ligne(s) importée(s) · <strong>{{ $result['created'] }}</strong> {{ __('contribuable(s) créé(s) ·') }} <strong>{{ count($result['errors']) }}</strong> {{ __('erreur(s)') }}</p>
            @foreach (array_slice($result['errors'], 0, 20) as $e)<p class="mt-1 text-xs text-red-600">{{ $e }}</p>@endforeach
        </x-card>
    @endif
</div>
