<?php

/*
|--------------------------------------------------------------------------
| Registre de navigation Jibayat
|--------------------------------------------------------------------------
| Espace ➜ sections ➜ pages. L'accueil, le carrousel des espaces, les compteurs,
| la recherche, les puces « Récents » et le menu bureau sont générés depuis ce fichier.
|
| Page : clé = segment d'URL ; route = "{espace}.{page}" ; URL = "/{espace}/{page}".
|   title, subtitle (FR ou AR), icon (Lucide), keywords (FR/AR pour la recherche),
|   permission (spatie), flask (écran de référence dans jibayat_app-main).
|
| Arborescence calquée sur le menu de l'application Flask (voir docs/MIGRATION_PLAN.md §3 bis).
| Hors périmètre : Abattoir, WhatsApp. Procès-verbaux et scanner ajoutés le 20/11/2026 (espace « regie »).
*/

return [

    'caisse' => [
        'group' => 'regie',
        'title' => 'Caisse & recouvrement',
        'title_ar' => 'الصندوق والتحصيل',
        'description' => 'bulletins, quittances, relances',
        'icon' => 'wallet',
        'colors' => ['from' => '#2B3A52', 'to' => '#1E2A3A', 'tile' => '#C9A13B'],
        'sections' => [
            'caisse' => [
                'title' => 'Caisse du régisseur',
                'pages' => [
                    'bulletins' => ['title' => 'Bulletins en attente', 'subtitle' => 'à quittancer', 'icon' => 'receipt', 'keywords' => ['bulletin', 'BV', 'quittance', 'régisseur', 'valider', 'وصل'], 'permission' => 'caisse.voir', 'flask' => '/paiements'],
                    'validation-groupee' => ['title' => 'Validation groupée', 'subtitle' => 'plusieurs bulletins', 'icon' => 'list-checks', 'keywords' => ['masse', 'valider', 'quittance'], 'permission' => 'paiements.valider', 'flask' => '/bulletins/valider-masse'],
                    'rejetes' => ['title' => 'Bulletins rejetés', 'subtitle' => 'à corriger', 'icon' => 'file-x', 'keywords' => ['rejet', 'motif', 'réémettre'], 'permission' => 'caisse.voir', 'flask' => '/paiements'],
                    'encaissements' => ['title' => 'Encaissements', 'subtitle' => 'quittances délivrées', 'icon' => 'banknote', 'keywords' => ['encaissé', 'payé', 'quittance', 'recette'], 'permission' => 'caisse.voir', 'flask' => '/paiements'],
                ],
            ],
            'recouvrement' => [
                'title' => 'Recouvrement',
                'pages' => [
                    'avis' => ['title' => 'Avis de non-paiement', 'subtitle' => 'إشعار بعدم الأداء', 'icon' => 'mail-warning', 'keywords' => ['avis', 'impayé', 'relance', 'lot', 'إشعار'], 'permission' => 'avis.voir', 'flask' => '/avis'],
                    'lettres' => ['title' => 'Lettres de relance', 'subtitle' => 'générer, approuver', 'icon' => 'send', 'keywords' => ['lettre', 'notification', 'relance', 'رسالة'], 'permission' => 'avis.voir', 'flask' => '/avis/lettre'],
                ],
            ],
            'contribuables' => [
                'title' => 'Contribuables',
                'pages' => [
                    'contribuables' => ['title' => 'Contribuables', 'subtitle' => 'répertoire des redevables', 'icon' => 'users', 'keywords' => ['redevable', 'CIN', 'ICE', 'RC', 'الملزمين'], 'permission' => 'contribuables.voir', 'flask' => '/contribuables'],
                    'fiche-contribuable' => ['title' => 'Fiche contribuable', 'subtitle' => 'situation détaillée', 'icon' => 'id-card', 'keywords' => ['dossier', 'historique', 'documents'], 'permission' => 'contribuables.voir', 'flask' => '/contribuables/<id>/detail'],
                    'nouveau-contribuable' => ['title' => 'Nouveau contribuable', 'subtitle' => 'ajout rapide', 'icon' => 'user-plus', 'keywords' => ['ajouter', 'créer', 'contribuable'], 'permission' => 'contribuables.ajouter', 'flask' => '/contribuables/ajouter'],
                ],
            ],
        ],
    ],

    'regie' => [
        'group' => 'regie',
        'title' => 'Régie des recettes',
        'title_ar' => 'وكالة المداخيل',
        'description' => 'saisie, livres, PV, clôture, stock',
        'icon' => 'landmark',
        'colors' => ['from' => '#7A5C1E', 'to' => '#2E2616', 'tile' => '#A67C2E'],
        'sections' => [
            'saisie' => [
                'title' => 'Saisie des recettes',
                'pages' => [
                    'tableau-de-bord' => ['title' => 'Tableau de bord', 'subtitle' => 'encaisse, alertes', 'icon' => 'chart-column', 'keywords' => ['régie', 'encaisse', 'solde', 'وكالة'], 'permission' => 'recettes.voir', 'flask' => null],
                    'saisie' => ['title' => 'Saisie des recettes', 'subtitle' => 'encaissements du jour', 'icon' => 'pen-line', 'keywords' => ['saisie', 'recette', 'encaissement', 'rubrique', 'mode', 'تحصيل'], 'permission' => 'recettes.ajouter', 'flask' => null],
                    'brouillons' => ['title' => 'Brouillons & validation', 'subtitle' => 'à valider', 'icon' => 'list-checks', 'keywords' => ['brouillon', 'valider', 'validation'], 'permission' => 'recettes.voir', 'flask' => null],
                    'recettes' => ['title' => 'Journal des recettes', 'subtitle' => 'saisies validées', 'icon' => 'book-open-check', 'keywords' => ['journal', 'recettes', 'annuler'], 'permission' => 'recettes.voir', 'flask' => null],
                ],
            ],
            'registres' => [
                'title' => 'Registres & modèles',
                'pages' => [
                    'livre-caisse' => ['title' => 'Livre de caisse', 'subtitle' => 'Modèle 6', 'icon' => 'book-open', 'keywords' => ['livre', 'caisse', 'modèle 6', 'report', 'solde', 'سجل الصندوق'], 'permission' => 'recettes.voir', 'flask' => null],
                    'etat-mensuel' => ['title' => 'État mensuel', 'subtitle' => 'Modèle 10', 'icon' => 'calendar-range', 'keywords' => ['modèle 10', 'état mensuel', 'recettes du mois'], 'permission' => 'recettes.voir', 'flask' => null],
                    'operations-tresor' => ['title' => 'Opérations Trésor', 'subtitle' => 'Modèle 7', 'icon' => 'landmark', 'keywords' => ['modèle 7', 'trésor', 'versements'], 'permission' => 'recettes.voir', 'flask' => null],
                    'situation' => ['title' => 'Situation journalière', 'subtitle' => 'la journée', 'icon' => 'calendar-days', 'keywords' => ['situation', 'jour', 'journalière'], 'permission' => 'recettes.voir', 'flask' => null],
                ],
            ],
            'bordereaux' => [
                'title' => 'Bordereaux',
                'pages' => [
                    'versements' => ['title' => 'Bordereaux de versement', 'subtitle' => 'au comptable', 'icon' => 'coins', 'keywords' => ['versement', 'comptable', 'percepteur', 'accusé'], 'permission' => 'recettes.voir', 'flask' => null],
                    'envois' => ['title' => 'Envois au Trésor', 'subtitle' => 'bordereaux d’envoi', 'icon' => 'send', 'keywords' => ['envoi', 'trésor', 'receveur', 'accusé de réception'], 'permission' => 'recettes.voir', 'flask' => null],
                    'taxe-speciale' => ['title' => 'Taxe spéciale', 'subtitle' => 'abattage', 'icon' => 'beef', 'keywords' => ['abattage', 'taxe spéciale', 'viandes', 'bordereau'], 'permission' => 'recettes.voir', 'flask' => null],
                    'virements' => ['title' => 'Autorisations de virement', 'subtitle' => 'Ministère', 'icon' => 'arrow-right-left', 'keywords' => ['virement', 'ministère', 'autorisation'], 'permission' => 'recettes.voir', 'flask' => null],
                ],
            ],
            'pv' => [
                'title' => 'Procès-verbaux',
                'pages' => [
                    'pv-arrete' => ['title' => 'Arrêté de caisse', 'subtitle' => 'chaque mois', 'icon' => 'clipboard-check', 'keywords' => ['PV', 'arrêté', 'billetage', 'محضر'], 'permission' => 'recettes.voir', 'flask' => null],
                    'pv-verification' => ['title' => 'Vérification de caisse', 'subtitle' => 'chaque trimestre', 'icon' => 'shield-check', 'keywords' => ['PV', 'vérification', 'contrôle'], 'permission' => 'recettes.voir', 'flask' => null],
                    'pv-recolement' => ['title' => 'Récolement', 'subtitle' => 'tickets et vignettes', 'icon' => 'clipboard-list', 'keywords' => ['PV', 'récolement', 'inventaire', 'tickets', 'vignettes'], 'permission' => 'recettes.voir', 'flask' => null],
                    'pv-remise' => ['title' => 'Remise de service', 'subtitle' => 'passation', 'icon' => 'handshake', 'keywords' => ['PV', 'remise', 'passation', 'régisseur'], 'permission' => 'recettes.voir', 'flask' => null],
                ],
            ],
            'controle' => [
                'title' => 'Contrôle & clôture',
                'pages' => [
                    'cloture' => ['title' => 'Clôture mensuelle', 'subtitle' => 'verrou du mois', 'icon' => 'lock', 'keywords' => ['clôture', 'verrou', 'mois', 'fermer'], 'permission' => 'recettes.voir', 'flask' => null],
                    'centre-controle' => ['title' => 'Centre de contrôle', 'subtitle' => 'ce qui cloche', 'icon' => 'siren', 'keywords' => ['contrôle', 'anomalies', 'à corriger'], 'permission' => 'recettes.voir', 'flask' => null],
                    'rapprochement' => ['title' => 'Contrôle croisé', 'subtitle' => 'saisies du mois', 'icon' => 'git-compare', 'keywords' => ['rapprochement', 'contrôle croisé', 'doublons'], 'permission' => 'recettes.voir', 'flask' => null],
                    'etat-rapprochement' => ['title' => 'État de rapprochement', 'subtitle' => 'régie / comptable', 'icon' => 'scale', 'keywords' => ['rapprochement', 'TGR', 'écart', 'comptable'], 'permission' => 'recettes.voir', 'flask' => null],
                    'recap' => ['title' => 'Récapitulatif comptable', 'subtitle' => 'famille, rubrique, mode', 'icon' => 'sigma', 'keywords' => ['récap', 'famille', 'rubrique', 'mode de paiement'], 'permission' => 'recettes.voir', 'flask' => null],
                ],
            ],
            'archives' => [
                'title' => 'Documents imprimés',
                'pages' => [
                    'documents' => ['title' => 'Registre des documents', 'subtitle' => 'pièces imprimées, QR', 'icon' => 'qr-code', 'keywords' => ['registre', 'documents', 'QR', 'archive'], 'permission' => 'archives.voir', 'flask' => null],
                    'controle-registre' => ['title' => 'Contrôle du registre', 'subtitle' => 'suivi des scans', 'icon' => 'scan-search', 'keywords' => ['contrôle', 'scan', 'registre'], 'permission' => 'archives.voir', 'flask' => null],
                    'scanner' => ['title' => 'Scanner', 'subtitle' => 'une feuille fait-elle foi ?', 'icon' => 'scan-line', 'keywords' => ['scanner', 'QR', 'authentique', 'vérifier'], 'permission' => 'archives.voir', 'flask' => null],
                    'boites' => ['title' => 'Boîtes d’archive', 'subtitle' => 'rangement', 'icon' => 'archive', 'keywords' => ['boîte', 'archive', 'rangement'], 'permission' => 'archives.voir', 'flask' => null],
                ],
            ],
            'stock' => [
                'title' => 'Stock tickets & vignettes',
                'pages' => [
                    'stock' => ['title' => 'Stock GIR', 'subtitle' => 'réserve TGR et régie', 'icon' => 'warehouse', 'keywords' => ['stock', 'GIR', 'TGR', 'réserve', 'tickets', 'vignettes'], 'permission' => 'regie.voir', 'flask' => null],
                    'saisie-tickets' => ['title' => 'Décharges de tickets', 'subtitle' => 'sorties, retours', 'icon' => 'ticket', 'keywords' => ['tickets', 'décharge', 'souk', 'collecteur', 'retour'], 'permission' => 'regie.voir', 'flask' => null],
                    'decharges-vignettes' => ['title' => 'Décharges de vignettes', 'subtitle' => 'carnets remis', 'icon' => 'notebook-pen', 'keywords' => ['vignettes', '2 DH', 'carnets', 'décharge', 'fonctionnaires'], 'permission' => 'regie.voir', 'flask' => null],
                    'etat-stock' => ['title' => 'État de stock', 'subtitle' => 'à imprimer', 'icon' => 'clipboard-list', 'keywords' => ['état', 'stock', 'inventaire'], 'permission' => 'regie.voir', 'flask' => null],
                    'intelligence' => ['title' => 'Intelligence du stock', 'subtitle' => 'consommation, commande', 'icon' => 'brain', 'keywords' => ['prévision', 'consommation', 'commande', 'excel'], 'permission' => 'regie.voir', 'flask' => null],
                    'config-stock' => ['title' => 'Configuration du stock', 'subtitle' => 'dotation TGR, seuils', 'icon' => 'settings-2', 'keywords' => ['configuration', 'dotation', 'seuil', 'rubrique'], 'permission' => 'regie.voir', 'flask' => null],
                ],
            ],
            'rapports' => [
                'title' => 'Rapports',
                'pages' => [
                    'rapports' => ['title' => 'Rapports détaillés', 'subtitle' => 'période au choix', 'icon' => 'file-bar-chart', 'keywords' => ['rapport', 'période', 'détail'], 'permission' => 'recettes.voir', 'flask' => null],
                    'tableau-recettes' => ['title' => 'Tableau des recettes', 'subtitle' => 'rubriques × mois', 'icon' => 'table', 'keywords' => ['tableau', 'mois', 'rubriques', 'bilan'], 'permission' => 'recettes.voir', 'flask' => null],
                    'rapport-annuel' => ['title' => 'Rapport annuel', 'subtitle' => 'l’exercice', 'icon' => 'book-marked', 'keywords' => ['annuel', 'exercice', 'bilan', 'budget'], 'permission' => 'recettes.voir', 'flask' => null],
                ],
            ],
        ],
    ],

    'tnb' => [
        'group' => 'modules',
        'title' => 'TNB',
        'title_ar' => 'الرسم على الأراضي غير المبنية',
        'description' => 'terrains non bâtis',
        'icon' => 'map',
        'colors' => ['from' => '#8B5A2B', 'to' => '#3A2A1E', 'tile' => '#8B5A2B'],
        'sections' => [
            'gestion' => [
                'title' => 'Gestion foncière',
                'pages' => [
                    'encaisser' => ['title' => 'Encaisser', 'subtitle' => 'أداء الرسم', 'icon' => 'hand-coins', 'keywords' => ['paiement', 'déclaration', 'bulletin', 'années', 'pénalité', 'amende'], 'permission' => 'bulletins.creer', 'flask' => '/tnb/<id>/paiement'],
                    'terrains' => ['title' => 'Dossiers & terrains', 'subtitle' => 'بقع', 'icon' => 'map-pin', 'keywords' => ['parcelle', 'lot', 'titre foncier', 'zone', 'lotissement'], 'permission' => 'tnb.voir', 'flask' => '/tnb'],
                    'fiche' => ['title' => 'Fiche terrain', 'subtitle' => 'permis, transferts', 'icon' => 'file-text', 'keywords' => ['permis', 'exonération', 'copropriétaire', 'indivision', 'transfert'], 'permission' => 'tnb.voir', 'flask' => '/tnb/<id>'],
                    'recensement' => ['title' => 'Recensement', 'subtitle' => 'استرجاع', 'icon' => 'clipboard-list', 'keywords' => ['récupération', 'terrain recensé', 'sans dossier'], 'permission' => 'tnb.voir', 'flask' => '/tnb/recensement'],
                ],
            ],
            'recouvrement' => [
                'title' => 'Recouvrement',
                'pages' => [
                    'avis' => ['title' => 'Avis de non-paiement', 'subtitle' => 'par terrain', 'icon' => 'mail-warning', 'keywords' => ['débiteurs', 'relance', 'impayé'], 'permission' => 'tnb.voir', 'flask' => '/tnb/<id>/avis_non_paiement'],
                    'avis-lot' => ['title' => 'Avis en lot', 'subtitle' => 'plusieurs terrains', 'icon' => 'send', 'keywords' => ['lot', 'multiple', 'débiteurs'], 'permission' => 'tnb.voir', 'flask' => '/tnb/avis_multiple'],
                ],
            ],
            'suivi' => [
                'title' => 'Suivi',
                'pages' => [
                    'tableau-de-bord' => ['title' => 'Tableau de bord', 'subtitle' => 'recouvrement TNB', 'icon' => 'chart-column', 'keywords' => ['statistiques', 'taux', 'RAR'], 'permission' => 'tnb.voir', 'flask' => '/module/tnb/dashboard'],
                    'registre' => ['title' => 'Registre TNB', 'subtitle' => 'encaissements', 'icon' => 'book-marked', 'keywords' => ['registre', 'quittances', 'journal'], 'permission' => 'registres.voir', 'flask' => '/registre/paiements/TNB'],
                ],
            ],
        ],
    ],

    'transport' => [
        'group' => 'modules',
        'title' => 'Transport',
        'title_ar' => 'النقل العمومي للمسافرين',
        'description' => 'stationnement & TPV',
        'icon' => 'bus',
        'colors' => ['from' => '#3E5C7E', 'to' => '#1F2E40', 'tile' => '#5B7590'],
        'sections' => [
            'gestion' => [
                'title' => 'Gestion',
                'pages' => [
                    'encaisser' => ['title' => 'Encaisser', 'subtitle' => 'un trimestre', 'icon' => 'hand-coins', 'keywords' => ['trimestre', 'stationnement', 'TPV', 'taxi', 'paiement'], 'permission' => 'bulletins.creer', 'flask' => '/stationnement/<id>/paiement'],
                    'vehicules' => ['title' => 'Véhicules', 'subtitle' => 'parc, agréments', 'icon' => 'bus', 'keywords' => ['immatriculation', 'taxi', 'autocar', 'agrément', 'المركبات'], 'permission' => 'transport.voir', 'flask' => '/stationnement'],
                    'fiche' => ['title' => 'Fiche véhicule', 'subtitle' => 'situation détaillée', 'icon' => 'file-text', 'keywords' => ['véhicule', 'propriétaire', 'historique'], 'permission' => 'transport.voir', 'flask' => '/stationnement/<id>'],
                ],
            ],
            'suivi' => [
                'title' => 'États & suivi',
                'pages' => [
                    'tableau-de-bord' => ['title' => 'Tableau de bord', 'subtitle' => 'recouvrement', 'icon' => 'chart-column', 'keywords' => ['statistiques', 'taux', 'T1', 'T4'], 'permission' => 'transport.voir', 'flask' => '/module/sta/dashboard'],
                    'registre' => ['title' => 'Registre', 'subtitle' => 'les quittances', 'icon' => 'book-marked', 'keywords' => ['registre', 'quittances'], 'permission' => 'registres.voir', 'flask' => '/registre/paiements/STATIONNEMENT'],
                    'avis' => ['title' => 'Avis de non-paiement', 'subtitle' => 'trimestres impayés', 'icon' => 'mail-warning', 'keywords' => ['relance', 'impayé'], 'permission' => 'transport.voir', 'flask' => '/stationnement/<id>/avis-non-paiement'],
                ],
            ],
        ],
    ],

    'locations' => [
        'group' => 'modules',
        'title' => 'Locations',
        'title_ar' => 'كراء المحلات',
        'description' => 'boutiques, locaux',
        'icon' => 'store',
        'colors' => ['from' => '#C65A1A', 'to' => '#4A2A1A', 'tile' => '#C8601E'],
        'sections' => [
            'gestion' => [
                'title' => 'Gestion',
                'pages' => [
                    'encaisser' => ['title' => 'Encaisser', 'subtitle' => 'saisie des loyers', 'icon' => 'hand-coins', 'keywords' => ['loyer', 'mois', 'paiement', 'bulletin'], 'permission' => 'bulletins.creer', 'flask' => '/location-locaux/<id>/paiement'],
                    'locataires' => ['title' => 'Locataires', 'subtitle' => 'baux, boutiques', 'icon' => 'users', 'keywords' => ['bail', 'contrat', 'local', 'المكترين'], 'permission' => 'locations.voir', 'flask' => '/location-locaux'],
                    'fiche' => ['title' => 'Fiche locataire', 'subtitle' => 'situation détaillée', 'icon' => 'file-text', 'keywords' => ['bail', 'historique', 'mois impayés'], 'permission' => 'locations.voir', 'flask' => '/location-locaux/<id>'],
                ],
            ],
            'configuration' => [
                'title' => 'Configuration',
                'pages' => [
                    'secteurs' => ['title' => 'Secteurs & tarifs', 'subtitle' => 'zones, groupes', 'icon' => 'grid-2x2', 'keywords' => ['secteur', 'zone', 'tarif', 'm²'], 'permission' => 'config.gerer', 'flask' => '/location-locaux/secteurs'],
                    'boutiques' => ['title' => 'Boutiques', 'subtitle' => 'disponibles', 'icon' => 'warehouse', 'keywords' => ['boutique', 'local', 'disponible'], 'permission' => 'locations.voir', 'flask' => '/location-locaux/secteurs'],
                    'synchronisation' => ['title' => 'Synchronisation', 'subtitle' => 'arrêté fiscal', 'icon' => 'refresh-cw', 'keywords' => ['arrêté', 'tarif fiscal', 'sync'], 'permission' => 'config.gerer', 'flask' => '/location-locaux/tarifs/sync-fiscal'],
                ],
            ],
            'suivi' => [
                'title' => 'États',
                'pages' => [
                    'tableau-de-bord' => ['title' => 'Tableau de bord', 'subtitle' => 'recouvrement des loyers', 'icon' => 'chart-column', 'keywords' => ['statistiques', 'taux'], 'permission' => 'locations.voir', 'flask' => '/module/loc/dashboard'],
                    'registre' => ['title' => 'Registre des loyers', 'subtitle' => 'quittances', 'icon' => 'book-marked', 'keywords' => ['registre', 'quittances'], 'permission' => 'registres.voir', 'flask' => '/registre/paiements/LOCATION_LOCAUX'],
                    'avis' => ['title' => 'Avis de non-paiement', 'subtitle' => 'mois impayés', 'icon' => 'mail-warning', 'keywords' => ['relance', 'impayé'], 'permission' => 'locations.voir', 'flask' => '/avis?module=LOCATION_LOCAUX'],
                ],
            ],
        ],
    ],

    'souk' => [
        'group' => 'modules',
        'title' => 'Souk',
        'title_ar' => 'الأسواق الجماعية',
        'description' => 'affermage des souks',
        'icon' => 'shopping-basket',
        'colors' => ['from' => '#D4A52E', 'to' => '#6B4F12', 'tile' => '#B8891F'],
        'sections' => [
            'gestion' => [
                'title' => 'Gestion',
                'pages' => [
                    'encaisser' => ['title' => 'Encaisser', 'subtitle' => 'redevances mensuelles', 'icon' => 'hand-coins', 'keywords' => ['redevance', 'mois', 'affermage', 'paiement'], 'permission' => 'bulletins.creer', 'flask' => '/souks/<id>/paiement'],
                    'contrats' => ['title' => "Contrats d'affermage", 'subtitle' => 'كراء الأسواق', 'icon' => 'scroll', 'keywords' => ['contrat', 'affermage', 'emplacement'], 'permission' => 'souk.voir', 'flask' => '/souks'],
                    'fiche' => ['title' => 'Fiche contrat', 'subtitle' => 'renouvellement, documents', 'icon' => 'file-text', 'keywords' => ['renouveler', 'résilier', 'documents'], 'permission' => 'souk.voir', 'flask' => '/souks/<id>'],
                ],
            ],
            'suivi' => [
                'title' => 'Suivi',
                'pages' => [
                    'tableau-de-bord' => ['title' => 'Tableau de bord', 'subtitle' => 'recouvrement', 'icon' => 'chart-column', 'keywords' => ['statistiques'], 'permission' => 'souk.voir', 'flask' => '/module/sou/dashboard'],
                    'registre' => ['title' => 'Registre souks', 'subtitle' => 'quittances', 'icon' => 'book-marked', 'keywords' => ['registre'], 'permission' => 'registres.voir', 'flask' => '/registre/paiements/AFFERMAGE_SOUKS'],
                    'avis' => ['title' => 'Avis de non-paiement', 'subtitle' => 'mois impayés', 'icon' => 'mail-warning', 'keywords' => ['relance', 'impayé'], 'permission' => 'souk.voir', 'flask' => '/souks/<id>/avis-non-paiement'],
                ],
            ],
        ],
    ],

    'boissons' => [
        'group' => 'modules',
        'title' => 'Débits de boissons',
        'title_ar' => 'محلات بيع المشروبات',
        'description' => 'cafés, restaurants',
        'icon' => 'coffee',
        'colors' => ['from' => '#8B2E3C', 'to' => '#3A1E22', 'tile' => '#8B2E3C'],
        'sections' => [
            'gestion' => [
                'title' => 'Gestion',
                'pages' => [
                    'encaisser' => ['title' => 'Encaisser', 'subtitle' => "trimestres, chiffre d'affaires", 'icon' => 'hand-coins', 'keywords' => ['CA', 'trimestre', 'taux', 'déclaration'], 'permission' => 'bulletins.creer', 'flask' => '/debits-boissons/<id>/paiement'],
                    'etablissements' => ['title' => 'Établissements', 'subtitle' => 'cafés, restaurants, hôtels', 'icon' => 'coffee', 'keywords' => ['café', 'restaurant', 'licence', 'المقاهي'], 'permission' => 'boissons.voir', 'flask' => '/debits-boissons'],
                    'fiche' => ['title' => 'Fiche établissement', 'subtitle' => 'situation détaillée', 'icon' => 'file-text', 'keywords' => ['établissement', 'historique'], 'permission' => 'boissons.voir', 'flask' => '/debits-boissons/<id>'],
                    'declaration-annuelle' => ['title' => 'Déclaration annuelle', 'subtitle' => 'récapitulatif N-1', 'icon' => 'calendar-range', 'keywords' => ['annuelle', 'récapitulatif', 'T1 T2 T3 T4'], 'permission' => 'boissons.voir', 'flask' => '/debits-boissons/<id>/declaration-annuelle'],
                    'cessation' => ['title' => 'Cessation', 'subtitle' => "fin d'activité", 'icon' => 'circle-x', 'keywords' => ['cessation', 'fermeture'], 'permission' => 'boissons.modifier', 'flask' => '/debits-boissons/<id>/cessation'],
                ],
            ],
            'suivi' => [
                'title' => 'Imprimés & suivi',
                'pages' => [
                    'declarations' => ['title' => 'Formulaires de déclaration', 'subtitle' => 'création, CA, cessation', 'icon' => 'printer', 'keywords' => ['formulaire', 'imprimé', 'déclaration'], 'permission' => 'boissons.voir', 'flask' => '/debits-boissons/pdf-declaration-<type>'],
                    'tableau-de-bord' => ['title' => 'Tableau de bord', 'subtitle' => 'recouvrement', 'icon' => 'chart-column', 'keywords' => ['statistiques'], 'permission' => 'boissons.voir', 'flask' => '/module/tdb/dashboard'],
                    'registre' => ['title' => 'Registre boissons', 'subtitle' => 'quittances', 'icon' => 'book-marked', 'keywords' => ['registre'], 'permission' => 'registres.voir', 'flask' => '/registre/paiements/DEBITS_BOISSONS'],
                    'avis' => ['title' => 'Avis de non-paiement', 'subtitle' => 'trimestres impayés', 'icon' => 'mail-warning', 'keywords' => ['relance', 'impayé'], 'permission' => 'boissons.voir', 'flask' => '/debits-boissons/<id>/avis-non-paiement'],
                ],
            ],
        ],
    ],

    'domaine-public' => [
        'group' => 'modules',
        'title' => 'Domaine public',
        'title_ar' => 'احتلال الملك العام',
        'description' => 'terrasses, kiosques',
        'icon' => 'tent',
        'colors' => ['from' => '#2F7D6B', 'to' => '#1E3A33', 'tile' => '#2F7D6B'],
        'sections' => [
            'gestion' => [
                'title' => 'Gestion',
                'pages' => [
                    'encaisser' => ['title' => 'Encaisser', 'subtitle' => 'trimestres ou année', 'icon' => 'hand-coins', 'keywords' => ['ODP', 'redevance', 'trimestre'], 'permission' => 'bulletins.creer', 'flask' => '/occupation-domaine/<id>/paiement'],
                    'occupations' => ['title' => 'Occupations', 'subtitle' => 'terrasses, étalages, kiosques', 'icon' => 'tent', 'keywords' => ['terrasse', 'kiosque', 'étalage', 'chantier', 'الملك العام'], 'permission' => 'domaine_public.voir', 'flask' => '/occupation-domaine'],
                    'fiche' => ['title' => 'Fiche occupation', 'subtitle' => 'situation détaillée', 'icon' => 'file-text', 'keywords' => ['autorisation', 'historique'], 'permission' => 'domaine_public.voir', 'flask' => '/occupation-domaine/<id>'],
                    'renouveler' => ['title' => 'Renouvellement', 'subtitle' => "prolonger l'autorisation", 'icon' => 'refresh-cw', 'keywords' => ['renouveler', 'prolonger', 'expiré'], 'permission' => 'domaine_public.modifier', 'flask' => '/occupation-domaine/<id>/renouveler'],
                ],
            ],
            'suivi' => [
                'title' => 'Suivi',
                'pages' => [
                    'tableau-de-bord' => ['title' => 'Tableau de bord', 'subtitle' => 'recouvrement', 'icon' => 'chart-column', 'keywords' => ['statistiques'], 'permission' => 'domaine_public.voir', 'flask' => '/module/odp/dashboard'],
                    'registre' => ['title' => 'Registre ODP', 'subtitle' => 'quittances', 'icon' => 'book-marked', 'keywords' => ['registre'], 'permission' => 'registres.voir', 'flask' => '/registre/paiements/OCCUPATION_DOMAINE'],
                    'avis' => ['title' => 'Avis de non-paiement', 'subtitle' => 'trimestres impayés', 'icon' => 'mail-warning', 'keywords' => ['relance', 'impayé'], 'permission' => 'domaine_public.voir', 'flask' => '/occupation-domaine/<id>/avis-non-paiement'],
                ],
            ],
        ],
    ],

    'fourriere' => [
        'group' => 'modules',
        'title' => 'Fourrière',
        'title_ar' => 'المحجز الجماعي',
        'description' => 'véhicules, enchères',
        'icon' => 'truck',
        'colors' => ['from' => '#4B5563', 'to' => '#1F2937', 'tile' => '#4B5563'],
        'sections' => [
            'gestion' => [
                'title' => 'Gestion',
                'pages' => [
                    'dossiers' => ['title' => 'Dossiers', 'subtitle' => 'véhicules en fourrière', 'icon' => 'car', 'keywords' => ['immatriculation', 'dépôt', 'remorquage', 'الحجز'], 'permission' => 'fourriere.voir', 'flask' => '/fourriere'],
                    'fiche' => ['title' => 'Fiche dossier', 'subtitle' => 'jours, montant dû', 'icon' => 'file-text', 'keywords' => ['dossier', 'propriétaire'], 'permission' => 'fourriere.voir', 'flask' => '/fourriere/<id>'],
                    'encaisser' => ['title' => 'Encaisser', 'subtitle' => 'frais de fourrière', 'icon' => 'hand-coins', 'keywords' => ['paiement', 'jours', 'tarif journalier'], 'permission' => 'bulletins.creer', 'flask' => '/fourriere/<id>/paiement'],
                    'sortie' => ['title' => 'Sortie du véhicule', 'subtitle' => 'restitution', 'icon' => 'key-round', 'keywords' => ['sortie', 'restitution', 'validée'], 'permission' => 'fourriere.modifier', 'flask' => '/fourriere/<id>/sortie'],
                ],
            ],
            'encheres' => [
                'title' => 'Enchères',
                'pages' => [
                    'encheres' => ['title' => 'Véhicules aliénables', 'subtitle' => 'plus de 365 jours', 'icon' => 'clock', 'keywords' => ['365', '265', 'aliénation', 'enchère'], 'permission' => 'fourriere.voir', 'flask' => '/fourriere/encheres'],
                    'groupes' => ['title' => "Groupes d'enchères", 'subtitle' => 'prix d’ouverture', 'icon' => 'gavel', 'keywords' => ['groupe', 'enchère', 'impression'], 'permission' => 'fourriere.voir', 'flask' => '/fourriere/encheres/groupe'],
                    'ventes' => ['title' => 'Ventes', 'subtitle' => 'adjudications', 'icon' => 'scale', 'keywords' => ['vente', 'acheteur', 'adjudication', 'PV de vente'], 'permission' => 'fourriere.voir', 'flask' => '/fourriere/encheres/groupe/<id>/vente'],
                ],
            ],
            'suivi' => [
                'title' => 'Suivi',
                'pages' => [
                    'tableau-de-bord' => ['title' => 'Tableau de bord', 'subtitle' => 'parc, durées', 'icon' => 'chart-column', 'keywords' => ['statistiques', 'capacité'], 'permission' => 'fourriere.voir', 'flask' => '/fourriere/dashboard'],
                    'registre' => ['title' => 'Registre fourrière', 'subtitle' => 'quittances', 'icon' => 'book-marked', 'keywords' => ['registre'], 'permission' => 'registres.voir', 'flask' => '/registre/paiements/FOURRIERE'],
                    'parametres' => ['title' => 'Paramètres fourrière', 'subtitle' => 'déposants, motifs, capacité', 'icon' => 'sliders-horizontal', 'keywords' => ['déposant', 'motif', 'capacité'], 'permission' => 'config.gerer', 'flask' => '/fourriere/parametres'],
                ],
            ],
        ],
    ],

    'declarations' => [
        'group' => 'regie',
        'title' => 'Déclarations d’assiette',
        'title_ar' => 'الإقرارات الخاصة بالوعاء',
        'description' => 'loi 47-06 modifiée par la loi 07-20',
        'icon' => 'file-signature',
        'colors' => ['from' => '#3E6B5A', 'to' => '#1F3A30', 'tile' => '#3E6B5A'],
        'sections' => [
            'depot' => [
                'title' => 'Dépôt',
                'pages' => [
                    'nouvelle' => ['title' => 'Nouvelle déclaration', 'subtitle' => 'إيداع إقرار', 'icon' => 'file-plus', 'keywords' => ['déclaration', 'cessation', 'arrêt', 'reprise', 'existence', 'cession', 'إقرار', 'إيقاف النشاط'], 'permission' => 'contribuables.voir', 'flask' => null],
                    'registre' => ['title' => 'Registre des déclarations d’assiette', 'subtitle' => 'déposées, hors délai', 'icon' => 'files', 'keywords' => ['déclaration', 'hors délai', 'signée', 'سجل الإقرارات'], 'permission' => 'contribuables.voir', 'flask' => null],
                ],
            ],
            'suivi' => [
                'title' => 'Suivi',
                'pages' => [
                    'echeances' => ['title' => 'Échéances annuelles', 'subtitle' => 'TNB 1er mars, boissons 1er avril', 'icon' => 'calendar-clock', 'keywords' => ['échéance', 'annuelle', 'manquante', 'الآجال'], 'permission' => 'contribuables.voir', 'flask' => null],
                    'formulaires' => ['title' => 'Formulaires officiels', 'subtitle' => 'à imprimer et faire signer', 'icon' => 'printer', 'keywords' => ['formulaire', 'imprimé', 'vierge', 'مطبوع'], 'permission' => 'contribuables.voir', 'flask' => null],
                ],
            ],
        ],
    ],

    'documents' => [
        'group' => 'regie',
        'title' => 'États & documents',
        'title_ar' => 'الوثائق والبيانات',
        'description' => 'régie, trésor, budget, registres',
        'icon' => 'file-text',
        'colors' => ['from' => '#9C7A2E', 'to' => '#3B3A2E', 'tile' => '#B08A3A'],
        'sections' => [
            'timbres' => [
                'title' => 'Régie des timbres',
                'pages' => [
                    'timbres' => ['title' => 'Timbres & tickets', 'subtitle' => 'tableau de bord', 'icon' => 'ticket', 'keywords' => ['timbre', 'ticket', 'vignette', 'régie'], 'permission' => 'regie.voir', 'flask' => '/regie/'],
                    'paquets' => ['title' => 'Paquets', 'subtitle' => 'réception, ouverture', 'icon' => 'package', 'keywords' => ['paquet', 'réception', 'numéros'], 'permission' => 'regie.voir', 'flask' => '/regie/paquets'],
                    'carnets' => ['title' => 'Carnets', 'subtitle' => 'affectation', 'icon' => 'notebook-tabs', 'keywords' => ['carnet', 'affecter', 'employé', 'service'], 'permission' => 'regie.voir', 'flask' => '/regie/carnets'],
                    'versements' => ['title' => 'Versements & bordereaux', 'subtitle' => 'au percepteur', 'icon' => 'coins', 'keywords' => ['versement', 'écart', 'bordereau', 'percepteur'], 'permission' => 'regie.voir', 'flask' => '/regie/versements'],
                ],
            ],
            'tresor' => [
                'title' => 'Trésor',
                'pages' => [
                    'releves-gir' => ['title' => 'Relevés GIR', 'subtitle' => 'TGR', 'icon' => 'landmark', 'keywords' => ['GIR', 'TGR', 'relevé', 'trésorerie'], 'permission' => 'tgr.voir', 'flask' => '/regie/tgr'],
                    'emission' => ['title' => "Bordereaux d'émission", 'subtitle' => 'par rubrique', 'icon' => 'stamp', 'keywords' => ['émission', 'bordereau', 'rubrique', 'report'], 'permission' => 'emission.voir', 'flask' => '/emission/'],
                ],
            ],
            'budget' => [
                'title' => 'Budget',
                'pages' => [
                    'budget' => ['title' => 'Gestion du budget', 'subtitle' => 'prévisions et réalisations', 'icon' => 'calculator', 'keywords' => ['budget', 'chapitre', 'réalisé', 'proposé', 'الميزانية'], 'permission' => 'budget.voir', 'flask' => '/regie/budget'],
                ],
            ],
            'registres' => [
                'title' => 'Registres',
                'pages' => [
                    'grand-livre' => ['title' => 'Grand livre des recettes', 'subtitle' => 'tous modules', 'icon' => 'book-open', 'keywords' => ['grand livre', 'recettes', 'quittances'], 'permission' => 'registres.voir', 'flask' => '/registre/global'],
                    'declarations' => ['title' => 'Registre des déclarations', 'subtitle' => 'émissions', 'icon' => 'file-check', 'keywords' => ['déclarations', 'émis'], 'permission' => 'registres.voir', 'flask' => '/registre/declarations_global'],
                    'registres' => ['title' => 'Hub des registres', 'subtitle' => 'par taxe', 'icon' => 'archive', 'keywords' => ['registre', 'archives'], 'permission' => 'registres.voir', 'flask' => '/registre/'],
                ],
            ],
            'controle' => [
                'title' => 'Contrôle',
                'pages' => [
                    'pieces-annulees' => ['title' => 'Pièces annulées', 'subtitle' => 'état', 'icon' => 'file-x', 'keywords' => ['annulation', 'motif', 'annulé'], 'permission' => 'registres.voir', 'flask' => null],
                ],
            ],
        ],
    ],

    'parametres' => [
        'group' => 'configuration',
        'title' => 'Paramètres',
        'title_ar' => 'الإعدادات',
        'description' => 'configuration, accès, système',
        'icon' => 'settings',
        'colors' => ['from' => '#46617F', 'to' => '#22303F', 'tile' => '#5B7590'],
        'sections' => [
            'configuration' => [
                'title' => 'Configuration',
                'pages' => [
                    'rubriques' => ['title' => 'Rubriques', 'subtitle' => 'nomenclature', 'icon' => 'list', 'keywords' => ['rubrique', 'code budgétaire', 'nomenclature'], 'permission' => 'parametres.voir', 'flask' => '/rubriques'],
                    'arretes' => ['title' => 'Arrêtés fiscaux & tarifs', 'subtitle' => 'la régie', 'icon' => 'scroll', 'keywords' => ['arrêté', 'tarif', 'zone', 'taux'], 'permission' => 'parametres.voir', 'flask' => '/arretes-fiscaux'],
                    'calcul' => ['title' => 'Paramètres de calcul', 'subtitle' => 'pénalités, échéances', 'icon' => 'sliders-horizontal', 'keywords' => ['pénalité', 'majoration', 'amende', 'date limite'], 'permission' => 'parametres.voir', 'flask' => '/parametres'],
                    'regie' => ['title' => 'Intervenants', 'subtitle' => 'services, employés, valeurs', 'icon' => 'building-2', 'keywords' => ['service', 'employé', 'valeur', 'timbre'], 'permission' => 'config.gerer', 'flask' => '/regie/config'],
                ],
            ],
            'acces' => [
                'title' => 'Accès',
                'pages' => [
                    'utilisateurs' => ['title' => 'Utilisateurs', 'subtitle' => 'comptes', 'icon' => 'users', 'keywords' => ['utilisateur', 'compte', 'mot de passe'], 'permission' => 'utilisateurs.voir', 'flask' => '/utilisateurs'],
                    'roles' => ['title' => 'Rôles & permissions', 'subtitle' => 'droits par module', 'icon' => 'shield-check', 'keywords' => ['rôle', 'permission', 'droits'], 'permission' => 'utilisateurs.voir', 'flask' => '/roles/permissions'],
                ],
            ],
            'donnees' => [
                'title' => 'Données',
                'pages' => [
                    'importation' => ['title' => 'Importation Excel', 'subtitle' => 'modèles, aperçu', 'icon' => 'file-spreadsheet', 'keywords' => ['import', 'Excel', 'CSV', 'modèle'], 'permission' => 'importation.voir', 'flask' => '/importation/'],
                    'imports' => ['title' => 'Journal des imports', 'subtitle' => 'historique', 'icon' => 'history', 'keywords' => ['import', 'historique', 'erreurs'], 'permission' => 'importation.voir', 'flask' => '/importation/'],
                ],
            ],
            'systeme' => [
                'title' => 'Système',
                'pages' => [
                    'commune' => ['title' => 'Commune', 'subtitle' => 'informations FR/AR', 'icon' => 'landmark', 'keywords' => ['commune', 'président', 'province', 'logo'], 'permission' => 'systeme.voir', 'flask' => '/parametres-systeme'],
                    'modules' => ['title' => 'Modules actifs', 'subtitle' => 'activer, masquer', 'icon' => 'grid-2x2', 'keywords' => ['module', 'activer'], 'permission' => 'systeme.voir', 'flask' => '/api/systeme/modules'],
                    'sauvegardes' => ['title' => 'Sauvegardes', 'subtitle' => 'base de données', 'icon' => 'database', 'keywords' => ['sauvegarde', 'backup', 'export'], 'permission' => 'systeme.voir', 'flask' => '/parametres-systeme'],
                    'rapports-email' => ['title' => 'Rapports par e-mail', 'subtitle' => 'envois automatiques, alertes', 'icon' => 'mail', 'keywords' => ['e-mail', 'rapport', 'alerte', 'notification', 'envoi'], 'permission' => 'systeme.voir', 'flask' => null],
                    'sessions' => ['title' => 'Sécurité des sessions', 'subtitle' => 'déconnexion automatique', 'icon' => 'shield-check', 'keywords' => ['session', 'inactivité', 'déconnexion'], 'permission' => 'systeme.voir', 'flask' => '/api/systeme/session-config'],
                    'journal' => ['title' => "Journal d'activité", 'subtitle' => 'audit', 'icon' => 'clipboard-list', 'keywords' => ['journal', 'audit', 'logs', 'connexion'], 'permission' => 'systeme.voir', 'flask' => 'user_logs'],
                    'etat' => ['title' => 'État du système', 'subtitle' => 'serveur, base', 'icon' => 'info', 'keywords' => ['serveur', 'version', 'base'], 'permission' => 'systeme.voir', 'flask' => '/parametres-systeme'],
                ],
            ],
            'aide' => [
                'title' => 'Aide',
                'pages' => [
                    'guide' => ['title' => "Guide d'utilisation", 'subtitle' => 'pas à pas', 'icon' => 'book-open', 'keywords' => ['aide', 'guide', 'mode d’emploi'], 'permission' => null, 'flask' => '/guide'],
                    'a-propos' => ['title' => 'À propos', 'subtitle' => 'version, licence', 'icon' => 'info', 'keywords' => ['version', 'à propos'], 'permission' => null, 'flask' => '/a-propos'],
                    'signaler' => ['title' => 'Signaler un problème', 'subtitle' => 'au support', 'icon' => 'triangle-alert', 'keywords' => ['bug', 'problème', 'support'], 'permission' => null, 'flask' => '/api/systeme/feedback'],
                ],
            ],
        ],
    ],
];
