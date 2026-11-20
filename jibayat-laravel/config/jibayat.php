<?php

return [

    /*
    | Version affichée dans la carte « Application » du pied de page.
    */
    'version' => env('JIBAYAT_VERSION', '4.0.0-dev'),

    // Adresse JSON de la dernière version publiée (ex. https://api.github.com/repos/<organisation>/<depot>/releases/latest).
    'update_url' => env('JIBAYAT_UPDATE_URL'),

    /*
    | Déconnexion automatique après N minutes sans activité (Flask : auto_logout_minutes = 10).
    */
    'auto_logout_minutes' => (int) env('JIBAYAT_AUTO_LOGOUT_MINUTES', 10),

    /*
    | Nombre de puces affichées dans « RÉCENTS ».
    */
    'recent_pages_limit' => 8,

    /*
    | Modules soumis à permissions (permission spatie = "{module}.{action}").
    | Correspondance avec les codes Flask (app_modules / MODULE_ALIAS_MAP) indiquée en commentaire.
    */
    'permission_modules' => [
        'caisse' => 'Caisse du régisseur',            // /paiements (bulletins)
        'avis' => 'Avis & lettres de relance',        // avis.py
        'contribuables' => 'Contribuables',           // contribuables.py
        'tnb' => 'TNB',                               // TNB
        'transport' => 'Transport (stationnement & TPV)', // STATIONNEMENT
        'locations' => 'Locations',                   // LOCATION_LOCAUX
        'souk' => 'Souk',                             // AFFERMAGE_SOUKS
        'boissons' => 'Débits de boissons',           // DEBITS_BOISSONS
        'domaine_public' => 'Domaine public',         // OCCUPATION_DOMAINE
        'fourriere' => 'Fourrière',                   // FOURRIERE
        'regie' => 'Régie des timbres',               // REGIE
        'recettes' => 'Régie des recettes (saisie, livres, PV, clôture)',
        'archives' => 'Documents imprimés & archives',
        'tgr' => 'Relevés GIR / TGR',                 // TGR
        'emission' => "Bordereaux d'émission",        // EMISSION
        'budget' => 'Budget',                         // regie/budget
        'registres' => 'Registres',                   // REGISTRES
        'parametres' => 'Paramètres fiscaux',         // config.py
        'utilisateurs' => 'Utilisateurs & rôles',     // users.py
        'systeme' => 'Système',                       // system.py
        'importation' => 'Importation Excel',         // importation.py
    ],

    'permission_actions' => ['voir', 'ajouter', 'modifier', 'supprimer', 'exporter'],

    /*
    | Permissions transversales (Flask : drapeaux globaux du rôle).
    */
    'global_permissions' => [
        'dashboard.voir' => 'Voir le tableau de bord',     // peut_voir_dashboard
        'bulletins.creer' => 'Émettre des bulletins',      // peut_creer_bulletin
        'paiements.valider' => 'Valider les paiements (quittancer)', // peut_valider_paiement
        'config.gerer' => 'Gérer la configuration',        // peut_config
    ],

    /*
    | Rôles cibles (Flask ➜ Laravel). Droits détaillés dans RolesAndPermissionsSeeder.
    */
    'roles' => [
        'administrateur' => 'Administrateur',               // super_admin, admin
        'regisseur' => 'Régisseur',                         // regisseur
        'regisseur_suppleant' => 'Régisseur suppléant',     // nouveau (droits du régisseur)
        'collecteur' => 'Collecteur',                       // agent_assiette
        'consultation' => 'Consultation / contrôle',        // consultant
    ],

    /*
    | Modules activables (Paramètres ➜ Modules actifs ; Flask : app_modules / config.json « modules »).
    | Un module désactivé masque son espace ou ses pages et en bloque l'accès.
    */
    'space_modules' => [
        'tnb' => 'TNB',
        'boissons' => 'DEBITS_BOISSONS',
        'transport' => 'STATIONNEMENT',
        'domaine-public' => 'OCCUPATION_DOMAINE',
        'fourriere' => 'FOURRIERE',
        'locations' => 'LOCATION_LOCAUX',
        'souk' => 'AFFERMAGE_SOUKS',
    ],
    'page_modules' => [
        'documents.timbres' => 'REGIE', 'documents.paquets' => 'REGIE', 'documents.carnets' => 'REGIE',
        'documents.versements' => 'REGIE', 'documents.budget' => 'REGIE', 'parametres.regie' => 'REGIE',
        'documents.releves-gir' => 'TGR',
        'documents.emission' => 'EMISSION',
        'documents.grand-livre' => 'REGISTRES', 'documents.declarations' => 'REGISTRES', 'documents.registres' => 'REGISTRES',
        'tnb.registre' => 'REGISTRES', 'transport.registre' => 'REGISTRES', 'locations.registre' => 'REGISTRES',
        'souk.registre' => 'REGISTRES', 'boissons.registre' => 'REGISTRES', 'domaine-public.registre' => 'REGISTRES',
        'fourriere.registre' => 'REGISTRES',
    ],

    /*
    | Débits de boissons : échéance du 4e trimestre pour les pénalités. true = 31 janvier de l'année suivante, pénalités à partir
    | de février N+1 (règle confirmée par le régisseur le 19/11/2026) ; false = ancien calcul Flask (tests de parité uniquement).
    */
    'boissons' => [
        't4_deadline_next_year' => (bool) env('JIBAYAT_TDB_T4_NEXT_YEAR', true),
    ],

    /*
    | Sauvegardes : chemin de mysqldump (vide = export SQL écrit en PHP).
    */
    'backup' => [
        'mysqldump' => env('JIBAYAT_MYSQLDUMP', 'mysqldump'),
    ],
];
