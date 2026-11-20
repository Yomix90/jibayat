@php
    $steps = [
        ['hand-coins', 'Encaisser une taxe', [
            'Ouvrez l’espace de la taxe (TNB, Locations, Transport…) puis « Encaisser ».',
            'Cherchez le dossier (n°, nom, CIN, immatriculation…) : les périodes impayées s’affichent avec leurs montants (principal, majoration, pénalités).',
            'Cochez les périodes à régler, vérifiez le total puis validez : un bulletin de versement (BV) est créé, à imprimer pour le contribuable.',
        ]],
        ['stamp', 'Valider à la caisse et délivrer la quittance', [
            'Le régisseur ouvre « Caisse & recouvrement ➜ Bulletins » : les bulletins en attente y sont listés.',
            'Après encaissement, « Solder & quittancer » : la quittance est numérotée automatiquement et la déclaration passe « payée ».',
            'Un bulletin erroné se rejette avec un motif ; une quittance ne se supprime jamais : elle s’annule (motif, auteur, date) et apparaît dans « Pièces annulées ».',
        ]],
        ['mail-warning', 'Relancer les retardataires', [
            '« Avis de non-paiement » liste les impayés par taxe : générez les avis (par lot ou un par un).',
            'Cochez des avis pour créer une lettre de relance, imprimez-la, puis marquez-la « envoyée ».',
            'La fiche contribuable donne aussi un avis global, toutes taxes confondues.',
        ]],
        ['file-spreadsheet', 'Reprendre des données existantes', [
            '« Paramètres ➜ Importation Excel » : téléchargez le modèle de la taxe, remplissez-le, puis « Aperçu ».',
            'Les lignes en erreur sont signalées et ne sont pas importées ; les contribuables sont retrouvés par CIN, ICE ou nom + prénom, sinon créés.',
            'Chaque import est noté dans le « Journal des imports ».',
        ]],
        ['landmark', 'États mensuels et Trésor', [
            '« États & documents ➜ Bordereaux d’émission » : importez chaque mois le bordereau GIR ; numéros et reports se recalculent sur l’année.',
            '« Relevés GIR » lit le relevé « Activité mensuelle » de la TGR, avec le total en lettres.',
            '« Gestion du budget » se synchronise avec les encaissements de l’année.',
        ]],
        ['shield-check', 'Sécurité et sauvegardes', [
            'La session se ferme après 10 minutes sans activité. Enregistrez votre empreinte dans « Mon profil » pour vous reconnecter d’un geste.',
            'La base est sauvegardée chaque jour ; « Paramètres ➜ Sauvegardes » permet une sauvegarde immédiate et une copie vers un disque externe.',
            'Un souci ? « Aide ➜ Signaler un problème » : l’administrateur est prévenu et vous répond dans l’application.',
        ]],
    ];
@endphp
<div class="space-y-6">
    <x-page-header :route="$this->pageRoute()" />

    <section aria-label="{{ __('Démarches') }}">
        <x-section-title>{{ __('Démarches pas à pas') }}</x-section-title>
        <div class="mt-3 grid gap-4 md:grid-cols-2">
            @foreach ($steps as [$icon, $title, $items])
                <x-card>
                    <h3 class="flex items-center gap-2 font-semibold"><span class="grid h-8 w-8 place-items-center rounded-lg bg-navy text-gold"><x-svg-icon :name="$icon" class="h-4 w-4" /></span>{{ tr($title) }}</h3>
                    <ol class="mt-3 list-decimal space-y-1.5 ps-5 text-sm text-ink">@foreach ($items as $i)<li>{{ $i }}</li>@endforeach</ol>
                </x-card>
            @endforeach
        </div>
    </section>

    <section aria-label="{{ __('Plan de l’application') }}">
        <x-section-title :count="$pages->flatten(1)->count()">{{ __('Plan de l’application') }}</x-section-title>
        <div class="mt-3 space-y-2">
            @foreach ($pages as $space => $list)
                <details class="surface group p-4" @if ($loop->first) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between font-semibold">{{ $space }} <span class="text-xs font-normal text-muted">{{ $list->count() }} page(s)</span></summary>
                    <ul class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($list as $p)
                            <li><a href="{{ route($p['route']) }}" class="block rounded-xl px-3 py-2 hover:bg-bg"><span class="block text-sm font-semibold">{{ __($p['title']) }}</span>
                                <span class="block text-xs text-muted">{{ __($p['section_title']) }}{{ __($p['subtitle']) ? ' · '.__($p['subtitle']) : '' }}</span></a></li>
                        @endforeach
                    </ul>
                </details>
            @endforeach
        </div>
    </section>
</div>
