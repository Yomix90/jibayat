-- Schéma SQLite réel de Jibayat Flask v3.0.2 (extrait de la base de production, sans données).

-- Sert à fabriquer des bases d essai pour les tests de jibayat:import-sqlite.



CREATE TABLE affermage_docs (id INTEGER PRIMARY KEY, affermage_id INTEGER, nom_fichier TEXT, chemin TEXT, type_doc TEXT, date_upload TEXT DEFAULT CURRENT_TIMESTAMP, agent_id INTEGER);

CREATE TABLE affermages (
        id INTEGER PRIMARY KEY, numero TEXT UNIQUE,
        contribuable_id INTEGER, commune_id INTEGER,
        nom_souk TEXT, num_emplacement TEXT, type_activite TEXT,
        redevance_annuelle REAL, date_debut TEXT,
        statut TEXT DEFAULT "actif", actif INTEGER DEFAULT 1,
        date_creation TEXT DEFAULT CURRENT_TIMESTAMP
    , date_fin TEXT, duree_contrat INTEGER DEFAULT 1, taux_augmentation REAL DEFAULT 5.0, redevance_mensuelle REAL DEFAULT 0);

CREATE TABLE app_modules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT UNIQUE NOT NULL,
        libelle TEXT,
        nom TEXT,
        description TEXT,
        icone TEXT DEFAULT '📦',
        actif INTEGER DEFAULT 1,
        ordre INTEGER DEFAULT 10
    );

CREATE TABLE arretes_fiscaux (
        id INTEGER PRIMARY KEY,
        numero TEXT UNIQUE,
        titre TEXT,
        date_effet TEXT NOT NULL,
        date_fin TEXT,
        statut TEXT DEFAULT 'actif',
        notes TEXT,
        agent_id INTEGER,
        date_creation TEXT DEFAULT CURRENT_TIMESTAMP
    );

CREATE TABLE avis_imposition (
        id INTEGER PRIMARY KEY, module TEXT, reference_id INTEGER,
        contribuable_id INTEGER, commune_id INTEGER,
        annee INTEGER, montant REAL, statut TEXT DEFAULT 'emis',
        date_emission TEXT, date_echeance TEXT, date_reglement TEXT,
        agent_id INTEGER
    );

CREATE TABLE avis_non_paiement (
        id INTEGER PRIMARY KEY, numero_avis TEXT UNIQUE,
        declaration_id INTEGER, contribuable_id INTEGER, commune_id INTEGER,
        montant_du REAL, date_emission TEXT, delai_jours INTEGER DEFAULT 30,
        lot_id TEXT, statut TEXT DEFAULT "emis"
    , lettre_id INTEGER);

CREATE TABLE baux (
        id INTEGER PRIMARY KEY, numero TEXT UNIQUE,
        contribuable_id INTEGER, commune_id INTEGER,
        ref_local TEXT, adresse TEXT, superficie REAL,
        loyer_mensuel REAL, date_debut TEXT, date_fin TEXT,
        statut TEXT DEFAULT "actif", actif INTEGER DEFAULT 1,
        date_creation TEXT DEFAULT CURRENT_TIMESTAMP
    , secteur_id INTEGER REFERENCES loc_secteurs(id), boutique_id INTEGER REFERENCES loc_boutiques(id), tarif_id INTEGER REFERENCES loc_tarifs(id));

CREATE TABLE bordereaux_emission (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bordereau_id INTEGER REFERENCES bordereaux_versement(id) ON DELETE CASCADE,
    numero_bordereau INTEGER,
    rubrique TEXT,
    code_budgetaire TEXT,
    intitule TEXT,
    montant_present REAL,
    report_anterieurs REAL,
    total REAL,
    chemin_pdf TEXT,
    chemin_xlsx TEXT,
    date_generation DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE bordereaux_versement (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    mois INTEGER NOT NULL,
    annee INTEGER NOT NULL,
    fichier_source TEXT,
    date_import DATETIME DEFAULT CURRENT_TIMESTAMP,
    total_general REAL,
    UNIQUE(mois, annee)
);

CREATE TABLE bulletins (
        id INTEGER PRIMARY KEY, numero_bulletin TEXT,
        declaration_id INTEGER, contribuable_id INTEGER, commune_id INTEGER,
        montant REAL, mode_paiement TEXT DEFAULT "especes",
        date_paiement TEXT, statut TEXT DEFAULT "en_attente",
        agent_id INTEGER, regisseur_id INTEGER, notes TEXT,
        date_creation TEXT DEFAULT CURRENT_TIMESTAMP
    , numero_quittance TEXT, date_quittance TEXT, motif_rejet TEXT, numero_versement TEXT, date_encaissement TEXT);

CREATE TABLE communes (
        id INTEGER PRIMARY KEY, nom TEXT, nom_ar TEXT,
        president_fr TEXT, president_ar TEXT,
        region TEXT, region_ar TEXT, province TEXT, province_ar TEXT, logo TEXT,
        code TEXT, actif INTEGER DEFAULT 1
    );

CREATE TABLE contribuables (
        id INTEGER PRIMARY KEY, numero TEXT UNIQUE,
        type_personne TEXT DEFAULT "physique",
        nom TEXT, prenom TEXT, nom_ar TEXT, prenom_ar TEXT,
        raison_sociale TEXT, raison_sociale_ar TEXT,
        cin TEXT, ice TEXT, rc TEXT,
        adresse TEXT, adresse_ar TEXT, ville TEXT, code_postal TEXT,
        telephone TEXT, email TEXT, commune_id INTEGER, actif INTEGER DEFAULT 1,
        date_creation TEXT DEFAULT CURRENT_TIMESTAMP
    );

CREATE TABLE ctb_documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    contribuable_id INTEGER NOT NULL,
    type_doc TEXT NOT NULL DEFAULT 'autre',
    nom_fichier TEXT,
    chemin TEXT,
    taille INTEGER,
    date_upload TEXT,
    agent_id INTEGER,
    notes TEXT,
    FOREIGN KEY(contribuable_id) REFERENCES contribuables(id)
);

CREATE TABLE declarations (
        id INTEGER PRIMARY KEY, numero TEXT UNIQUE,
        module TEXT, reference_id INTEGER,
        contribuable_id INTEGER, commune_id INTEGER,
        annee INTEGER, trimestre INTEGER DEFAULT 0,
        base_calcul REAL DEFAULT 0, taux REAL DEFAULT 0,
        montant_principal REAL DEFAULT 0,
        penalite_retard REAL DEFAULT 0, majoration REAL DEFAULT 0,
        amende_non_declaration REAL DEFAULT 0, montant_total REAL DEFAULT 0,
        statut TEXT DEFAULT "emis",
        date_declaration TEXT, date_echeance TEXT, date_paiement TEXT,
        agent_id INTEGER, notes TEXT,
        date_creation TEXT DEFAULT CURRENT_TIMESTAMP
    );

CREATE TABLE declarations_annuelles_tdb (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    numero TEXT UNIQUE,
    etablissement_id INTEGER,
    contribuable_id INTEGER,
    commune_id INTEGER DEFAULT 1,
    annee INTEGER,
    base_t1 REAL DEFAULT 0,
    base_t2 REAL DEFAULT 0,
    base_t3 REAL DEFAULT 0,
    base_t4 REAL DEFAULT 0,
    total_base REAL DEFAULT 0,
    taux REAL DEFAULT 10,
    montant_du REAL DEFAULT 0,
    date_declaration TEXT,
    agent_id INTEGER,
    statut TEXT DEFAULT 'soumise',
    date_creation TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE dossiers_fourriere (
        id INTEGER PRIMARY KEY, numero TEXT UNIQUE,
        contribuable_id INTEGER, commune_id INTEGER,
        immatriculation TEXT, type_vehicule TEXT,
        date_mise_fourriere TEXT, motif TEXT, nb_jours INTEGER DEFAULT 0,
        frais_remorquage REAL DEFAULT 0,
        statut TEXT DEFAULT "en_fourriere", date_restitution TEXT,
        actif INTEGER DEFAULT 1, date_creation TEXT DEFAULT CURRENT_TIMESTAMP
    , num_depot TEXT, numero_immat TEXT, deposant TEXT, nom_proprietaire TEXT, cin_proprietaire TEXT, telephone_prop TEXT, tarif_journalier REAL DEFAULT 0, numero_bulletin TEXT, date_paiement TEXT, agent_paiement_id INTEGER, date_sortie_validee TEXT, notes TEXT);

CREATE TABLE dossiers_tnb (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        numero_dossier INTEGER UNIQUE NOT NULL,
        contribuable_id INTEGER NOT NULL REFERENCES contribuables(id),
        archive INTEGER DEFAULT 0,
        date_creation TEXT DEFAULT CURRENT_TIMESTAMP
    );

CREATE TABLE emission_config (
    annee INTEGER PRIMARY KEY,
    premiere_partie_mode TEXT DEFAULT 'auto',
    premiere_partie_valeur REAL DEFAULT 0.0,
    deuxieme_partie_mode TEXT DEFAULT 'vide',
    deuxieme_partie_valeur REAL DEFAULT 0.0,
    notes TEXT,
    date_modification TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE etablissements_boissons (
        id INTEGER PRIMARY KEY, numero TEXT UNIQUE,
        contribuable_id INTEGER, commune_id INTEGER,
        nom_etablissement TEXT, type_etablissement TEXT,
        adresse TEXT, superficie REAL,
        numero_autorisation TEXT, date_autorisation TEXT,
        trimestre_debut_imposition INTEGER DEFAULT 1,
        annee_debut_imposition INTEGER,
        statut TEXT DEFAULT "actif", actif INTEGER DEFAULT 1,
        date_creation TEXT DEFAULT CURRENT_TIMESTAMP
    );

CREATE TABLE fou_declarations (
        id INTEGER PRIMARY KEY, dossier_id INTEGER,
        tarif_journalier REAL, frais_remorquage REAL DEFAULT 0,
        montant_calcule REAL, montant_paye REAL DEFAULT 0,
        statut TEXT DEFAULT 'emis', date_emission TEXT, date_paiement TEXT,
        agent_id INTEGER, commune_id INTEGER
    );

CREATE TABLE fou_dossiers (
        id INTEGER PRIMARY KEY, numero_pv TEXT UNIQUE,
        contribuable_id INTEGER, commune_id INTEGER,
        vehicule_immat TEXT, type_vehicule TEXT,
        date_mise_en_fourriere TEXT, date_sortie TEXT,
        nb_jours INTEGER, statut TEXT DEFAULT 'en_fourriere',
        date_creation TEXT DEFAULT CURRENT_TIMESTAMP
    );

CREATE TABLE fou_groupes_enchere (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    numero          TEXT UNIQUE NOT NULL,
    libelle         TEXT NOT NULL,
    date_creation   TEXT,
    date_enchere    TEXT,
    lieu            TEXT,
    prix_ouverture  REAL DEFAULT 0,
    type_vehicule   TEXT,   -- filtre par type
    statut          TEXT DEFAULT 'ouvert',  -- ouvert | vendu | annule
    notes           TEXT,
    agent_id        INTEGER
);

CREATE TABLE fou_parametres (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    categorie  TEXT NOT NULL,   -- 'deposant' | 'etat_vehicule' | 'motif'
    code       TEXT NOT NULL,
    libelle    TEXT NOT NULL,
    ordre      INTEGER DEFAULT 0,
    actif      INTEGER DEFAULT 1
);

CREATE TABLE fou_types_vehicule (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    code             TEXT UNIQUE NOT NULL,
    libelle          TEXT NOT NULL,
    tarif_journalier REAL DEFAULT 0,
    actif            INTEGER DEFAULT 1
);

CREATE TABLE fou_vehicules_enchere (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    groupe_id       INTEGER NOT NULL REFERENCES fou_groupes_enchere(id),
    dossier_id      INTEGER NOT NULL REFERENCES dossiers_fourriere(id),
    etat_vehicule   TEXT DEFAULT 'moyen',  -- bien | moyen | mauvais
    ordre           INTEGER DEFAULT 0,
    notes           TEXT
);

CREATE TABLE fou_ventes (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    groupe_id         INTEGER REFERENCES fou_groupes_enchere(id),
    dossier_id        INTEGER REFERENCES dossiers_fourriere(id),
    nom_acheteur      TEXT,
    cin_acheteur      TEXT,
    telephone_acheteur TEXT,
    prix_adjudication REAL DEFAULT 0,
    numero_quittance  TEXT,
    date_vente        TEXT,
    pv_chemin         TEXT,  -- upload PV de vente
    pv_nom            TEXT,
    agent_id          INTEGER,
    notes             TEXT
);

CREATE TABLE imports_historique (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    module TEXT NOT NULL,
    nom_fichier TEXT,
    nb_lignes_total INTEGER DEFAULT 0,
    nb_succes INTEGER DEFAULT 0,
    nb_erreurs INTEGER DEFAULT 0,
    details_json TEXT,
    agent_id INTEGER,
    date_import TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE lettres_details (
    id INTEGER PRIMARY KEY,
    lettre_id INTEGER,
    avis_id INTEGER,
    declaration_id INTEGER,
    contribuable_id INTEGER,
    montant_du REAL,
    statut_envoi TEXT DEFAULT "inclus"
);

CREATE TABLE lettres_notification (
    id INTEGER PRIMARY KEY,
    numero_lettre TEXT UNIQUE,
    lot_id TEXT,
    module TEXT,
    type_lettre TEXT DEFAULT "relance",
    statut TEXT DEFAULT "brouillon",
    date_generation TEXT,
    date_envoi TEXT,
    agent_id INTEGER,
    nb_redevables INTEGER DEFAULT 0,
    montant_total REAL DEFAULT 0,
    notes TEXT,
    date_creation TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE lignes_recettes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bordereau_id INTEGER REFERENCES bordereaux_versement(id) ON DELETE CASCADE,
    code_budgetaire TEXT,
    nature_recette TEXT,
    montant REAL,
    feuille_emission TEXT
);

CREATE TABLE loc_boutiques (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tarif_id INTEGER NOT NULL REFERENCES loc_tarifs(id),
    numero TEXT NOT NULL,
    libelle TEXT,
    superficie REAL DEFAULT 0,
    statut TEXT NOT NULL DEFAULT 'disponible',
    notes TEXT,
    date_creation TEXT DEFAULT (datetime('now'))
);

CREATE TABLE loc_locaux (
        id INTEGER PRIMARY KEY, numero_contrat TEXT UNIQUE,
        contribuable_id INTEGER, commune_id INTEGER,
        designation TEXT, adresse TEXT, superficie REAL,
        loyer_mensuel REAL, date_debut TEXT, date_fin TEXT, statut TEXT DEFAULT 'actif',
        date_creation TEXT DEFAULT CURRENT_TIMESTAMP
    );

CREATE TABLE loc_paiements (
        id INTEGER PRIMARY KEY, local_id INTEGER, mois TEXT,
        montant REAL, montant_paye REAL DEFAULT 0,
        statut TEXT DEFAULT 'en_attente', date_paiement TEXT,
        agent_id INTEGER, commune_id INTEGER
    );

CREATE TABLE loc_secteurs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    libelle TEXT NOT NULL,
    description TEXT,
    type_tarif TEXT NOT NULL DEFAULT 'fixe',  -- 'fixe' = DH/mois, 'm2' = DH/m²/mois
    tarif_mensuel REAL NOT NULL DEFAULT 0,
    unite TEXT NOT NULL DEFAULT 'DH/mois',
    actif INTEGER NOT NULL DEFAULT 1,
    date_creation TEXT DEFAULT (datetime('now')),
    ordre INTEGER DEFAULT 0
);

CREATE TABLE loc_tarifs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    secteur_id INTEGER NOT NULL REFERENCES loc_secteurs(id),
    libelle TEXT NOT NULL,
    type_tarif TEXT NOT NULL DEFAULT 'fixe',
    valeur REAL NOT NULL DEFAULT 0,
    unite TEXT NOT NULL DEFAULT 'DH/mois',
    actif INTEGER NOT NULL DEFAULT 1,
    date_creation TEXT DEFAULT (datetime('now'))
, tarif_fiscal_id INTEGER REFERENCES tarifs(id));

CREATE TABLE occupations (
        id INTEGER PRIMARY KEY, numero TEXT UNIQUE,
        contribuable_id INTEGER, commune_id INTEGER,
        type_occupation TEXT, localisation TEXT, superficie REAL,
        num_autorisation TEXT, date_debut TEXT, date_fin TEXT,
        trimestre_debut_imposition INTEGER DEFAULT 1,
        annee_debut_imposition INTEGER,
        statut TEXT DEFAULT "actif", actif INTEGER DEFAULT 1,
        date_creation TEXT DEFAULT CURRENT_TIMESTAMP
    );

CREATE TABLE odp_declarations (
        id INTEGER PRIMARY KEY, occupation_id INTEGER, annee INTEGER,
        superficie REAL, tarif REAL, montant_calcule REAL,
        montant_paye REAL DEFAULT 0, statut TEXT DEFAULT 'emis',
        date_emission TEXT, date_paiement TEXT, agent_id INTEGER, commune_id INTEGER
    );

CREATE TABLE odp_occupations (
        id INTEGER PRIMARY KEY, numero_autorisation TEXT UNIQUE,
        contribuable_id INTEGER, commune_id INTEGER,
        type_occupation TEXT, superficie REAL, emplacement TEXT,
        date_debut TEXT, date_fin TEXT, tarif REAL, statut TEXT DEFAULT 'actif',
        date_creation TEXT DEFAULT CURRENT_TIMESTAMP
    );

CREATE TABLE paiements_bulletins (
        id INTEGER PRIMARY KEY, module TEXT, reference_id INTEGER,
        commune_id INTEGER, contribuable_id INTEGER,
        montant REAL, date_paiement TEXT, mode_paiement TEXT DEFAULT 'espece',
        reference_paiement TEXT, statut TEXT DEFAULT 'en_attente',
        agent_id INTEGER, valideur_id INTEGER, date_validation TEXT,
        date_creation TEXT DEFAULT CURRENT_TIMESTAMP
    );

CREATE TABLE parametres_calcul (
        id INTEGER PRIMARY KEY, module TEXT, code TEXT,
        libelle TEXT, valeur TEXT, unite TEXT, description TEXT, commune_id INTEGER,
        UNIQUE(module, code)
    );

CREATE TABLE permis (
        id INTEGER PRIMARY KEY, terrain_id INTEGER,
        type_permis TEXT, numero_permis TEXT,
        date_depot TEXT, date_delivrance TEXT, statut TEXT DEFAULT "en_cours",
        description TEXT, date_creation TEXT DEFAULT CURRENT_TIMESTAMP
    , date_autorisation TEXT, annee_debut_exoneration INTEGER, annee_fin_exoneration INTEGER);

CREATE TABLE regie_bordereaux (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    numero TEXT UNIQUE,
    date_versement TEXT,
    montant_total REAL DEFAULT 0,
    agent_id INTEGER,
    date_creation TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE regie_budget (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            annee INTEGER NOT NULL,
            chapitre TEXT NOT NULL,
            code_gir TEXT,
            libelle TEXT NOT NULL,
            libelle_fr TEXT,
            libelle_ar TEXT,
            domaine TEXT NOT NULL,
            domaine_fr TEXT,
            domaine_ar TEXT,
            module_associe TEXT,
            montant_realise_n1 REAL DEFAULT 0.0,
            montant_propose REAL DEFAULT 0.0,
            montant_realise REAL DEFAULT 0.0,
            notes TEXT,
            ordre INTEGER DEFAULT 0,
            date_modification TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(annee, chapitre)
        );

CREATE TABLE regie_carnets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    paquet_id INTEGER REFERENCES regie_paquets(id),
    valeur_id INTEGER REFERENCES regie_valeurs(id),
    numero_carnet TEXT UNIQUE,
    num_premier INTEGER,
    num_dernier INTEGER,
    statut TEXT DEFAULT 'en_stock',
    service_id INTEGER REFERENCES regie_services(id),
    employe_id INTEGER REFERENCES regie_employes(id),
    date_affectation TEXT,
    date_versement_employe TEXT,
    date_versement_percepteur TEXT,
    bordereau_id INTEGER REFERENCES regie_bordereaux(id),
    montant_verse REAL DEFAULT 0,
    ecart REAL DEFAULT 0,
    observation TEXT
);

CREATE TABLE regie_employes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    matricule TEXT,
    nom TEXT NOT NULL,
    prenom TEXT,
    service_id INTEGER REFERENCES regie_services(id),
    actif INTEGER DEFAULT 1
);

CREATE TABLE regie_paquets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    valeur_id INTEGER REFERENCES regie_valeurs(id),
    numero_paquet TEXT UNIQUE,
    num_premier TEXT,
    num_dernier TEXT,
    quantite_vignettes INTEGER,
    date_reception TEXT,
    statut TEXT DEFAULT 'recu',
    agent_id INTEGER,
    date_creation TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE regie_services (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL,
    actif INTEGER DEFAULT 1
);

CREATE TABLE regie_valeurs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type_valeur TEXT DEFAULT 'timbre',
    designation TEXT NOT NULL,
    valeur_unitaire REAL NOT NULL,
    nb_unites_carnet INTEGER NOT NULL,
    actif INTEGER DEFAULT 1
);

CREATE TABLE role_module_permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        role_id INTEGER REFERENCES roles(id) ON DELETE CASCADE,
        module_code TEXT REFERENCES app_modules(code) ON DELETE CASCADE,
        peut_voir INTEGER DEFAULT 1,
        peut_ajouter INTEGER DEFAULT 0,
        peut_modifier INTEGER DEFAULT 0,
        peut_supprimer INTEGER DEFAULT 0,
        peut_exporter INTEGER DEFAULT 0,
        UNIQUE(role_id, module_code)
    );

CREATE TABLE roles (
        id INTEGER PRIMARY KEY, nom TEXT UNIQUE,
        peut_ajouter INTEGER DEFAULT 0, peut_modifier INTEGER DEFAULT 0,
        peut_supprimer INTEGER DEFAULT 0, peut_voir INTEGER DEFAULT 1,
        peut_valider_paiement INTEGER DEFAULT 0, peut_config INTEGER DEFAULT 0,
        peut_creer_bulletin INTEGER DEFAULT 0, peut_exporter INTEGER DEFAULT 0,
        peut_voir_dashboard INTEGER DEFAULT 1,
        peut_outils INTEGER DEFAULT 1
    );

CREATE TABLE rubriques (
        id INTEGER PRIMARY KEY, code TEXT, libelle TEXT, libelle_ar TEXT,
        module TEXT UNIQUE, commune_id INTEGER, actif INTEGER DEFAULT 1, description TEXT
    );

CREATE TABLE schema_meta (
        key TEXT PRIMARY KEY, val TEXT
    );

CREATE TABLE sou_contrats (
        id INTEGER PRIMARY KEY, numero_contrat TEXT UNIQUE,
        contribuable_id INTEGER, commune_id INTEGER,
        nom_souk TEXT, emplacement TEXT, superficie REAL,
        redevance_annuelle REAL, date_debut TEXT, date_fin TEXT, statut TEXT DEFAULT 'actif',
        date_creation TEXT DEFAULT CURRENT_TIMESTAMP
    );

CREATE TABLE sou_paiements (
        id INTEGER PRIMARY KEY, contrat_id INTEGER, annee INTEGER,
        montant REAL, montant_paye REAL DEFAULT 0,
        statut TEXT DEFAULT 'en_attente', date_paiement TEXT,
        agent_id INTEGER, commune_id INTEGER
    );

CREATE TABLE sta_declarations (
        id INTEGER PRIMARY KEY, vehicule_id INTEGER, annee INTEGER,
        tarif REAL, montant_calcule REAL, montant_paye REAL DEFAULT 0,
        statut TEXT DEFAULT 'emis', date_emission TEXT, date_paiement TEXT,
        agent_id INTEGER, commune_id INTEGER
    );

CREATE TABLE sta_vehicules (
        id INTEGER PRIMARY KEY, numero_immatriculation TEXT UNIQUE,
        contribuable_id INTEGER, commune_id INTEGER,
        type_vehicule TEXT, categorie TEXT,
        marque TEXT, capacite INTEGER, statut TEXT DEFAULT 'actif',
        date_creation TEXT DEFAULT CURRENT_TIMESTAMP
    );

CREATE TABLE tarifs (
        id INTEGER PRIMARY KEY,
        rubrique_id INTEGER NOT NULL,
        arrete_id INTEGER,
        code_tarif TEXT,
        libelle TEXT NOT NULL,
        valeur REAL NOT NULL,
        unite TEXT DEFAULT 'DH',
        date_debut TEXT NOT NULL,
        date_fin TEXT,
        actif INTEGER DEFAULT 1
    , surface_min REAL DEFAULT 0, surface_max REAL);

CREATE TABLE tdb_declarations (
        id INTEGER PRIMARY KEY, etablissement_id INTEGER, annee INTEGER,
        chiffre_affaires REAL, taux REAL, montant_calcule REAL,
        montant_paye REAL DEFAULT 0, statut TEXT DEFAULT 'emis',
        date_emission TEXT, date_paiement TEXT, agent_id INTEGER, commune_id INTEGER
    );

CREATE TABLE tdb_etablissements (
        id INTEGER PRIMARY KEY, numero_licence TEXT UNIQUE,
        contribuable_id INTEGER, commune_id INTEGER,
        nom_etablissement TEXT, nom_etablissement_ar TEXT,
        type_etablissement TEXT, categorie TEXT,
        adresse TEXT, date_ouverture TEXT, statut TEXT DEFAULT 'actif',
        date_creation TEXT DEFAULT CURRENT_TIMESTAMP
    );

CREATE TABLE terrain_coproprietaires (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        terrain_id INTEGER NOT NULL REFERENCES terrains(id),
        contribuable_id INTEGER NOT NULL REFERENCES contribuables(id),
        part_indivision REAL DEFAULT 0,
        date_ajout TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(terrain_id, contribuable_id)
    );

CREATE TABLE terrains (
        id INTEGER PRIMARY KEY, numero_terrain TEXT UNIQUE,
        contribuable_id INTEGER, commune_id INTEGER,
        adresse TEXT, adresse_ar TEXT, quartier TEXT, arrondissement TEXT,
        superficie REAL, zone TEXT DEFAULT "B",
        titre_foncier TEXT, num_parcelle TEXT,
        statut TEXT DEFAULT "non_bati", date_acquisition TEXT,
        annee_debut_imposition INTEGER,
        actif INTEGER DEFAULT 1, date_creation TEXT DEFAULT CURRENT_TIMESTAMP
    , lotissement TEXT DEFAULT "", archive INTEGER DEFAULT 0, recensement INTEGER DEFAULT 0);

CREATE TABLE tnb_declarations (
        id INTEGER PRIMARY KEY, terrain_id INTEGER, annee INTEGER,
        superficie_declaree REAL, zone TEXT, tarif REAL,
        montant_calcule REAL, montant_paye REAL DEFAULT 0,
        statut TEXT DEFAULT 'emis', date_emission TEXT, date_paiement TEXT,
        agent_id INTEGER, commune_id INTEGER
    );

CREATE TABLE tnb_documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        terrain_id INTEGER NOT NULL REFERENCES terrains(id),
        nom_fichier TEXT,
        chemin TEXT,
        type_doc TEXT DEFAULT "autre",
        taille INTEGER,
        date_upload TEXT DEFAULT CURRENT_TIMESTAMP,
        agent_id INTEGER
    );

CREATE TABLE tnb_terrains (
        id INTEGER PRIMARY KEY, numero_fiscal TEXT UNIQUE NOT NULL,
        contribuable_id INTEGER, commune_id INTEGER,
        superficie REAL, zone TEXT, adresse TEXT, statut TEXT DEFAULT 'non_bati',
        date_creation TEXT DEFAULT CURRENT_TIMESTAMP
    );

CREATE TABLE transferts_terrain (
        id INTEGER PRIMARY KEY, terrain_id INTEGER,
        ancien_contribuable_id INTEGER, nouveau_contribuable_id INTEGER,
        date_transfert TEXT, motif TEXT, acte_notarie TEXT, agent_id INTEGER
    );

CREATE TABLE user_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER REFERENCES utilisateurs(id) ON DELETE CASCADE,
        action TEXT NOT NULL,
        description TEXT,
        ip_adresse TEXT,
        user_agent TEXT,
        date_action TEXT DEFAULT CURRENT_TIMESTAMP
    );

CREATE TABLE user_module_permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER REFERENCES utilisateurs(id) ON DELETE CASCADE,
        module_code TEXT REFERENCES app_modules(code) ON DELETE CASCADE,
        peut_voir INTEGER DEFAULT 0,
        peut_ajouter INTEGER DEFAULT 0,
        peut_modifier INTEGER DEFAULT 0,
        peut_supprimer INTEGER DEFAULT 0,
        peut_exporter INTEGER DEFAULT 0,
        UNIQUE(user_id, module_code)
    );

CREATE TABLE utilisateurs (
        id INTEGER PRIMARY KEY, username TEXT UNIQUE, nom TEXT, prenom TEXT,
        email TEXT, mot_de_passe TEXT, role_id INTEGER,
        actif INTEGER DEFAULT 1, commune_id INTEGER, remember_token TEXT,
        avatar TEXT, telephone TEXT, date_creation TEXT DEFAULT CURRENT_TIMESTAMP,
        derniere_connexion TEXT,
        custom_permissions INTEGER DEFAULT 0,
        modules_autorises TEXT DEFAULT '',
        u_peut_voir INTEGER DEFAULT 1,
        u_peut_ajouter INTEGER DEFAULT 0,
        u_peut_modifier INTEGER DEFAULT 0,
        u_peut_supprimer INTEGER DEFAULT 0,
        u_peut_creer_bulletin INTEGER DEFAULT 0,
        u_peut_valider_paiement INTEGER DEFAULT 0,
        u_peut_config INTEGER DEFAULT 0,
        u_peut_exporter INTEGER DEFAULT 0,
        u_peut_voir_dashboard INTEGER DEFAULT 1,
        u_peut_outils INTEGER DEFAULT 1
    , doit_changer_mdp INTEGER DEFAULT 0);

CREATE TABLE vehicules (
        id INTEGER PRIMARY KEY, numero TEXT UNIQUE,
        contribuable_id INTEGER, commune_id INTEGER,
        immatriculation TEXT, type_vehicule TEXT,
        num_autorisation TEXT, date_autorisation TEXT,
        nombre_sieges INTEGER DEFAULT 0,
        trimestre_debut_imposition INTEGER DEFAULT 1,
        annee_debut_imposition INTEGER,
        statut TEXT DEFAULT "actif", actif INTEGER DEFAULT 1,
        date_creation TEXT DEFAULT CURRENT_TIMESTAMP
    );
