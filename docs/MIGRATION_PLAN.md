# Jibayat — Plan de migration Flask/SQLite ➜ Laravel/MySQL

> **Phase 1 — Analyse (révision 2).** Aucun code Laravel n'a été écrit. Le projet Flask
> (`jibayat_app-main/`) n'a pas été modifié.
> Date de l'analyse : 19/11/2026 — version Flask analysée : **3.0.2** (CHANGELOG).

### Décisions de youssef (19/11/2026)

- **La référence de la migration est le code Flask `jibayat_app-main/`** (code Python **et**
  interfaces). L'arborescence de la section 5 du CLAUDE.md n'est plus la cible : la
  navigation Laravel reprend les modules et écrans réels (voir §3 bis).
- **Hors périmètre** : Abattoir, procès-verbaux (arrêté de caisse, vérification, récolement,
  remise de service), scanner / archive des scans, WhatsApp.
- Le **design** du CLAUDE.md (§4 : palette, zellige, composants, navigation pilotée par
  `config/navigation.php`) reste valable et s'applique aux espaces réels.

---

## 0. Constat initial : écart entre CLAUDE.md et le code Flask (pour mémoire)

Le CLAUDE.md décrit une application de **régie des recettes** (« version 9.0 ») organisée
en 5 espaces : États & documents, Locations, Transport, TNB, Paramètres (+ Souk, Abattoir).
Le code présent dans `jibayat_app-main/` est **JIBAYAT v3.0.2**, un système de **gestion
fiscale communale** organisé autrement, avec un workflow **déclaration ➜ bulletin de
versement ➜ validation par le régisseur (n° de quittance)**. Le tableau ci-dessous
explique l'écart ; il est tranché par les décisions ci-dessus.

| Espace CLAUDE.md | Équivalent dans le code Flask | Couverture |
|---|---|---|
| Locations (17 pages) | `modules/location.py` (baux, secteurs, tarifs, boutiques) | ~40 % : pas de WhatsApp, contentieux, suivi des relances, révision de loyer dédiée, RAR par année |
| Transport (14 pages) | `modules/stationnement.py` (véhicules : droit de stationnement + taxe TPV, trimestriel) | ~50 % : pas de tableau croisé, WhatsApp ; import Excel existe (module Importation) |
| TNB (17 pages) | `modules/tnb.py` (dossiers, terrains, permis, copropriétaires, recensement) | ~60 % : pas d'« évolution du RAR sur 18 mois », journal, tableau croisé |
| Souk | `modules/souks.py` (affermage mensuel avec revalorisation) | présent |
| **Abattoir** | **absent** du code | 0 % |
| États & documents (25 pages) | `regie.py` (carnets, versements, budget, relevés TGR), `emission/` (bordereaux d'émission), `registre.py`, `avis.py` | ~25 % : **aucun** Modèle 10 / 6 / 7, PV (arrêté de caisse, vérification, récolement, remise de service), scanner, visas TGR, boîtes d'archive, centre de contrôle |
| Paramètres (11 pages) | `config.py` (rubriques, arrêtés fiscaux, tarifs, paramètres de calcul), `users.py`, `system.py` | ~60 % : pas de bouchers / tarifs abattoir, rapports e-mail, notifications |
| *(absent du CLAUDE.md)* | **Débits de boissons (TDB)**, **Occupation du domaine public (ODP)**, **Fourrière** (+ enchères), **Contribuables** (référentiel transversal), **Avis & lettres de relance**, **Paiements / validation régisseur**, **Importation Excel**, **Licence / télémétrie / mise à jour OTA** | à arbitrer |

➜ **Tranché** : on migre ce qui existe dans Flask ; les pages du CLAUDE.md sans équivalent
Flask (Modèles 6/7/10, centre de contrôle, contentieux, tableaux croisés, RAR sur 18 mois…)
ne sont pas créées pour l'instant ; elles pourront être ajoutées
plus tard comme simples états calculés.

Autres écarts :
- `docs/design/` (captures de référence) **n'existe pas** dans le dossier.
- Aucune base `.db` dans le projet. La base réelle est
  `%APPDATA%\JIBAYAT\fiscalite.db` (≈ 632 Ko) et elle est **chiffrée SQLCipher AES-256**,
  avec une clé scellée par Windows DPAPI (`.dbkey.bin`). Je ne l'ai pas ouverte.
  Conséquence : les **volumes par table** ne sont pas mesurables pour l'instant, et la
  commande `jibayat:import-sqlite` ne pourra pas lire ce fichier tel quel (voir §8).

---

## 1. Architecture Flask actuelle

| Élément | Détail |
|---|---|
| Point d'entrée | `app.py` (orchestration, CSRF, en-têtes sécurité, accueil `/`, `/module/<m>/dashboard`) |
| Base | `database.py` : SQLite/SQLCipher, schéma versionné (`schema_meta`, v1→v6) + migrations « ALTER si absent » |
| Blueprints | 22 fichiers dans `modules/` (≈ 300 routes) |
| Templates | Jinja2 + Bootstrap, ~110 fichiers ; impressions HTML (`*_pdf.html`, `official_print.css`) + ReportLab pour les bordereaux d'émission |
| Desktop | `launcher.py` (systray, mutex), build Nuitka, installeur, mises à jour OTA GitHub, licence par empreinte matérielle, télémétrie |
| Config | `config.json` (commune, modules actifs, auto-logout, sauvegardes, licence) + `.env` |

### Workflow financier commun (cœur métier)

```
Agent d'assiette                        Régisseur
──────────────────                      ─────────────────────────────
1. Sélectionne périodes impayées
2. Calcul → INSERT declarations          
   (statut 'emis' ou 'sous_seuil')
3. Saisit N° BV (bulletin de versement)
   → INSERT bulletins 'en_attente'  ──►  4. /paiements : valide en saisissant
                                            N° QUITTANCE + date (manuel)
                                            → bulletins 'paye', declarations 'paye'
                                            (cascade sur tous les bulletins
                                             partageant le même N° BV)
                                         ou rejette (motif) → déclaration 'emis'
```

Statuts `declarations` : `emis`, `sous_seuil`, `paye`, `annule` (rarement utilisé : l'annulation **supprime** la ligne).
Statuts `bulletins` : `en_attente`, `paye`, `rejete`, `encaisse` (souks), `valide`.

---

## 2. Inventaire des tables SQLite

Volumes : **non disponibles** (base chiffrée, cf. §0). Les colonnes `REAL` monétaires
deviendront `DECIMAL(14,2)` ; les dates `TEXT` (ISO `YYYY-MM-DD`) deviendront `DATE`/`DATETIME`.

### 2.1 Sécurité, utilisateurs, système

| Table | Colonnes clés | Modèle Laravel proposé |
|---|---|---|
| `utilisateurs` | username, nom, prenom, email, mot_de_passe (hash), role_id, actif, avatar, telephone, derniere_connexion, doit_changer_mdp, custom_permissions, modules_autorises (CSV), u_peut_* (10 drapeaux) | `User` |
| `roles` | nom, peut_ajouter/modifier/supprimer/voir/valider_paiement/config/creer_bulletin/exporter/voir_dashboard/outils | rôles spatie + permissions |
| `app_modules` | code, libelle, icone, actif, ordre | config `navigation.php` + table `modules` |
| `role_module_permissions` / `user_module_permissions` | (role\|user, module_code) → voir/ajouter/modifier/supprimer/exporter | permissions spatie `{module}.{action}` |
| `user_logs` | user_id, action, description, ip, user_agent, date | activitylog |
| `remember_tokens` (créée par `session_security`) | jetons « mémoriser » par poste | sessions Laravel |
| `login_attempts` | anti-bruteforce | throttle Laravel |
| `communes` | nom/nom_ar, président fr/ar, région, province (fr/ar), logo, code | `Commune` (1 ligne) |
| `schema_meta` | version du schéma | non migrée |

### 2.2 Référentiel & paramétrage fiscal

| Table | Colonnes clés | Modèle |
|---|---|---|
| `contribuables` | numero (unique), type_personne, nom/prenom (+_ar), raison_sociale (+_ar), cin, ice, rc, adresse (+_ar), telephone, email, actif | `Taxpayer` |
| `ctb_documents` | pièces jointes contribuable | `TaxpayerDocument` |
| `rubriques` | code budgétaire, libelle (+_ar), **module** (unique) | `Rubric` |
| `arretes_fiscaux` | numero, titre, date_effet, date_fin, statut | `TaxOrder` |
| `tarifs` | rubrique_id, arrete_id, code_tarif, libelle, **valeur**, unite, date_debut, date_fin, actif, surface_min, surface_max | `Tariff` |
| `parametres_calcul` | (module, code) → valeur (ex. `PENALITE_RETARD`, `AMENDE_NON_DECLARATION`, `DATE_LIMITE`, `ANNEES_DEBUT`) | `CalcParameter` |

### 2.3 Taxes et redevances (tables actives)

| Module | Tables | Colonnes clés |
|---|---|---|
| **TNB** | `dossiers_tnb` (1 dossier/contribuable, numero_dossier séquentiel), `terrains`, `permis` (exonérations), `terrain_coproprietaires` (part_indivision), `transferts_terrain`, `tnb_documents` | terrains : numero_terrain, superficie, **zone**, titre_foncier, num_parcelle, lotissement, statut, date_acquisition, annee_debut_imposition, archive, recensement |
| **Débits de boissons** | `etablissements_boissons`, `declarations_annuelles_tdb` | type_etablissement, trimestre/annee_debut_imposition ; déclaration annuelle base_t1..t4 |
| **Stationnement / TPV** (= « Transport ») | `vehicules` | immatriculation, type_vehicule, num_autorisation, nombre_sieges, trimestre/annee_debut_imposition |
| **ODP** | `occupations` | type_occupation, localisation, superficie, date_debut/fin, trimestre/annee_debut_imposition |
| **Fourrière** | `dossiers_fourriere`, `fou_parametres`, `fou_types_vehicule`, `fou_groupes_enchere`, `fou_vehicules_enchere`, `fou_ventes` | date_mise_fourriere, tarif_journalier, frais_remorquage, deposant, statut, prix_adjudication |
| **Location** | `baux`, `loc_secteurs`, `loc_tarifs` (→ `tarif_fiscal_id`), `loc_boutiques` | bail : loyer_mensuel, date_debut/fin, secteur/boutique/tarif ; tarif : type `fixe` ou `m2` |
| **Souks** | `affermages`, `affermage_docs` | redevance_mensuelle/annuelle, date_debut/fin, duree_contrat (ans), **taux_augmentation** (%) |

### 2.4 Pièces financières

| Table | Rôle | Remarque migration |
|---|---|---|
| `declarations` | liquidation d'une période (année / trimestre / mois), tous modules | `module` + `reference_id` polymorphe ; **les mois Location/Souks sont stockés en texte dans `notes`** (« 2025-01, 2025-02 \| note ») ➜ à normaliser en table `declaration_periods` |
| `bulletins` | bulletin de versement + quittance (numero_bulletin, numero_quittance, date_quittance, motif_rejet, numero_versement, date_encaissement) | plusieurs bulletins peuvent partager le même N° BV |
| `avis_non_paiement` | avis par déclaration, lot_id, lettre_id | |
| `lettres_notification`, `lettres_details` | lettres de relance groupées (brouillon ➜ approuvée ➜ annulée) | |
| `imports_historique` | journal des imports Excel | |

### 2.5 Régie, émission, budget

| Table | Rôle |
|---|---|
| `regie_valeurs` | valeurs inactives (timbres) : valeur_unitaire, nb_unites_carnet |
| `regie_services`, `regie_employes` | services et employés détenteurs de carnets |
| `regie_paquets` ➜ `regie_carnets` | réception d'un paquet, ouverture = 10 carnets, affectation, versement employé (écart), bordereau percepteur |
| `regie_bordereaux` | bordereau de versement au percepteur |
| `bordereaux_versement`, `lignes_recettes` | import mensuel du bordereau GIR/TGR (Excel) |
| `bordereaux_emission` | bordereaux d'émission générés par rubrique (n° séquentiel annuel, report antérieur) |
| `emission_config` | 1ère/2ème partie (auto/manuel/vide) par année |
| `regie_budget` | prévisions/réalisations budgétaires par chapitre (nomenclature TGR/GIR embarquée) |

### 2.6 Tables héritées (non migrées, à confirmer)

`tnb_terrains`, `tnb_declarations`, `tdb_etablissements`, `tdb_declarations`, `sta_vehicules`,
`sta_declarations`, `odp_occupations`, `odp_declarations`, `fou_dossiers`, `fou_declarations`,
`loc_locaux`, `loc_paiements`, `sou_contrats`, `sou_paiements`, `paiements_bulletins`,
`avis_imposition`. Aucune n'est écrite par le code actuel (seules quelques lectures de comptage
dans `app.py`). Le rapport d'import indiquera leur nombre de lignes.

---

## 3. Routes ➜ pages Laravel (correspondance)

| Module Flask | Routes principales | Pages Laravel cibles |
|---|---|---|
| `contribuables` | `/contribuables`, `/<id>/detail`, ajout/modif/suppr, ajout rapide, docs, PDF, export Excel, API recherche | Contribuables : liste, fiche, documents |
| `tnb` | `/tnb`, `/tnb/<id>`, `/paiement`, `/multi_declarations`, permis, transfert, copropriétaires, avis (individuel / lot / multiple), docs, `/tnb/recensement` | TNB : Encaisser, Parcelles, Vue d'ensemble, Récupération (recensement), Propriétaire, État RAR, Relances |
| `tdb` | `/debits-boissons`, `/paiement`, `/declarer`, déclaration annuelle, cessation, PDF déclarations (création / CA / cessation / annuelle) | nouvel espace « Débits de boissons » |
| `stationnement` | `/stationnement`, `/<id>`, `/paiement`, `/payer`, avis | Transport : Encaisser, Véhicules, Fiche véhicule, Vue d'ensemble |
| `occupation` | `/occupation-domaine`, `/paiement`, `/declarer`, `/renouveler`, avis | nouvel espace « Domaine public » |
| `fourriere` | `/fourriere`, `/paiement`, `/payer`, `/sortie`, `/encheres` (groupes, vente, impression), `/parametres`, `/dashboard` (+ export) | nouvel espace « Fourrière » |
| `location` | `/location-locaux`, `/<id>`, `/paiement`, `/payer`, `/secteurs` (secteurs, tarifs, boutiques), sync arrêté fiscal | Locations : Encaisser, Locataires, Fiche locataire, Vue d'ensemble, (zones = secteurs) |
| `souks` | `/souks`, `/<id>`, `/paiement`, `/payer`, `/renouveler`, `/terminer`, docs, avis, encaisser | Souk |
| `bulletins` | `/paiements`, valider, valider-masse, rejeter, modifier, supprimer, annuler déclaration | **Caisse du régisseur** (nouvelle section transversale) |
| `avis` | `/avis`, générer (lot / individuel), lettres (générer, imprimer, approuver, annuler), export Excel | Recouvrement : Relances, Lettres, Suivi des relances |
| `registre` | `/registre`, paiements et déclarations par module / global, exports | États : Registre des quittances, Situation globale |
| `regie` | paquets, carnets, versements, bordereau (impression), config (services, employés, valeurs), `/tgr` (relevés), `/budget` (import, synchro, export, PDF) | États & documents : Bordereau de versement, Rapprochement (TGR), Bilan annuel (budget) |
| `emission` | `/emission/`, upload bordereau GIR, vue mois, config année, recalcul, PDF (global / rubrique), édition de montants | États & documents : Bordereau d'émission par rubrique |
| `config` | `/rubriques`, `/arretes-fiscaux` (+ détail, charger tarifs par défaut), `/tarifs`, `/parametres`, `/api/calcul` | Paramètres : Rubriques, Tarifs & configuration |
| `users` | `/utilisateurs`, rôles, permissions par rôle et par utilisateur, `/profil` | Paramètres : Utilisateurs (à ajouter à l'arborescence) |
| `system` | `/parametres-systeme`, commune, modules actifs, sauvegardes (local / Google Drive), export/import DB, mot de passe DB, feedback, session, webhook, `/guide`, `/a-propos`, `/setup` | Paramètres : État du système, Quoi de neuf, Signaler un problème |
| `importation` | `/importation` (modèle, aperçu, exécution) pour TDB, TNB, Stationnement, ODP (+ autres) | Import Excel par espace |
| `auth` | `/login`, `/logout`, `/api/session/ping` | Breeze + auto-logout |
| `licensing`, `updater`, `telemetry`, `security_vault`, `leak_cleanup`, `network_announcer`, `bundle_loader` | spécifiques au produit desktop Windows | **non migrés** (à confirmer, Q14) |

---

## 3 bis. Interfaces Flask et navigation cible

### 3 bis.1 Ce que montre l'interface Flask actuelle

- **Coque** (`base.html`, `shell.css`) : barre latérale repliable (état mémorisé), barre du
  haut avec puce « Essai / Licence », pastille d'état de l'extension Chrome **GIR TGR**,
  menu utilisateur (profil, utilisateurs, paramètres système, licence, à propos, déconnexion),
  modale « fiche contribuable » ouvrable depuis n'importe quel écran, modale de mise à jour.
  Polices Cairo / Changa / Inter / Plus Jakarta Sans (chargées depuis Google Fonts).
- **Menu latéral** (sections réelles) :
  Tableau de bord · **Gestion** (Contribuables, Bulletins de paiement avec badge du nombre en
  attente, Avis de non-paiement) · **Modules fiscaux** (TNB, Débits de boissons, Stationnement
  TPV, Fourrière, Domaine public, Location locaux, Souks — chacun : tableau de bord + liste ;
  Location : + Secteurs & tarifs) · **Régie des recettes** (Gestion du budget ; Timbres &
  tickets : tableau de bord, paquets, carnets, versements & BV, configuration ; Relevés GIR ;
  Bordereaux d'émission) · **Registres & archives** (hub, grand livre des recettes, registre
  des déclarations, registre par taxe ×7) · **Outils** (Importation Excel) · **Configuration**
  (Rubriques, Arrêtés fiscaux, Paramètres de calcul, Utilisateurs & rôles) · **Système**
  (Paramètres système) · **Aide** (Guide d'utilisation, À propos).
- **Accueil** (`index.html`) : 5 KPI (total émis, total recouvré + taux, en attente régisseur,
  reste à recouvrer + nb d'avis, nb de contribuables / dossiers), courbe mensuelle émis vs
  encaissé de l'année, camembert des recettes par module.
- **Tableau de bord d'un module** (`module_dashboard.html`, commun à 6 modules ; la fourrière a
  le sien) : 6 KPI (dossiers, déclarations de l'année, recouvré, impayé, taux, avis), courbe
  mensuelle, répartition des statuts, historique par année, déclarations récentes.
- **Gabarit commun à chaque taxe** (les 7 modules suivent le même modèle) :
  1. *Liste* : 4–5 KPI (dossiers, dossiers avec impayés, en attente régisseur, à jour…),
     filtres (recherche, statut, zone/lotissement…), tableau avec « situation financière »,
     modale d'ajout/modification, options d'impression du répertoire (FR/AR).
  2. *Fiche détail* en onglets (TNB : infos, déclarations, permis, documents, transferts ;
     contribuable : identité, modules actifs, historique paiements, documents).
  3. *Paiement* : périodes impayées cochables (années / trimestres / mois) avec calcul en
     direct, saisie du **N° de bulletin**, bulletins en attente, historique avec quittance.
  4. *Avis de non-paiement* imprimable.
- **Caisse du régisseur** (`paiements.html`) : 4 KPI (en attente, encaissé, sans bulletin,
  rejetés), bulletins groupés par N° BV, actions *Solder & quittancer*, *Validation groupée*,
  *Rejeter*, *Corriger & réémettre*, *Supprimer*.
- **Bilingue** : libellés FR, noms et en-têtes officiels FR/AR (`format_nom_bilingue`), certaines
  impressions entièrement en arabe (ex. répertoire des locaux).

### 3 bis.2 Navigation Laravel proposée (`config/navigation.php`)

Les espaces reprennent le menu Flask, mis en forme selon le design du CLAUDE.md
(carrousel d'espaces, sections, tuiles). Couleurs : celles du CLAUDE.md quand l'espace existait,
**propositions** marquées *(prop.)* pour les nouveaux.

| Espace | Couleur | Sections ➜ pages (source Flask) |
|---|---|---|
| **Caisse & recouvrement** | navy/or *(prop.)* `#2B3A52 → #1E2A3A` | **Caisse** : Bulletins en attente (`/paiements`), Validation groupée, Bulletins rejetés · **Recouvrement** : Avis de non-paiement (`/avis`), Lettres de relance (générer, approuver, imprimer) · **Contribuables** : Répertoire, Fiche contribuable, Ajout rapide |
| **TNB** | bronze | **Gestion foncière** : Dossiers & terrains (liste), Fiche terrain (onglets), Encaisser (paiement + multi-déclarations), Recensement · **Recouvrement** : Avis individuel / en lot / multiple · **Suivi** : Tableau de bord, Registre TNB |
| **Transport** (Stationnement & TPV) | bleu ardoise | **Gestion** : Véhicules, Fiche véhicule, Encaisser (trimestres) · **Suivi** : Tableau de bord, Registre, Avis de non-paiement |
| **Locations** | orange brûlé | **Gestion** : Baux, Fiche bail, Encaisser (mois) · **Configuration** : Secteurs, groupes de tarifs, boutiques, synchronisation arrêté · **Suivi** : Tableau de bord, Registre |
| **Souk** | jaune doré | **Gestion** : Contrats d'affermage, Fiche contrat (documents, renouvellement, résiliation), Encaisser (mois) · **Suivi** : Tableau de bord, Registre, Avis |
| **Débits de boissons** | bordeaux *(prop.)* `#8B2E3C → #3A1E22` | **Gestion** : Établissements, Fiche, Encaisser (trimestres, CA), Déclaration annuelle, Cessation · **Imprimés** : déclarations création / CA / cessation / annuelle · **Suivi** : Tableau de bord, Registre, Avis |
| **Domaine public** (ODP) | vert *(prop.)* `#2F7D6B → #1E3A33` | **Gestion** : Occupations, Fiche, Encaisser, Renouveler · **Suivi** : Tableau de bord, Registre, Avis |
| **Fourrière** | anthracite *(prop.)* `#4B5563 → #1F2937` | **Gestion** : Dossiers, Fiche, Paiement, Sortie · **Enchères** : Véhicules +365 j, Groupes, Vente · **Suivi** : Tableau de bord (+ export), Registre · **Paramètres** fourrière |
| **États & documents** | olive/or | **Régie des timbres** : Tableau de bord, Paquets, Carnets, Versements & bordereaux · **Trésor** : Relevés GIR (import, export, PDF), Bordereaux d'émission (par mois, par rubrique, configuration annuelle) · **Budget** : Gestion du budget (import, synchronisation, PDF FR/AR) · **Registres** : Grand livre des recettes, Registre des déclarations, Hub des registres · **Contrôle** : Pièces annulées *(nouvelle, découle de la règle d'immuabilité)* |
| **Paramètres** | bleu acier | **Configuration** : Rubriques, Arrêtés fiscaux & tarifs, Paramètres de calcul, Configuration régie (services, employés, valeurs) · **Accès** : Utilisateurs & rôles, Permissions · **Données** : Importation Excel, Journal des imports · **Système** : Commune, Modules actifs, Sauvegardes, Sessions (auto-déconnexion), Journal d'activité · **Aide** : Guide, À propos, Signaler un problème |

Retirés : Abattoir, procès-verbaux, scanner / archive des scans, WhatsApp (y compris le
lien « Support WhatsApp » de la page À propos).

---

## 4. Règles de calcul (à porter à l'identique, avec tests de parité)

### 4.1 Arrondi « majoré au centime supérieur » — `helpers.majore_centimes`
```
majore(v) = 0                               si v ≤ 0
          = ceil(round(round(v,6)·100, 4)) / 100   sinon        (945.721 → 945.73)
```
⚠️ Utilisé en TNB et dans `calculer_penalites` ; les autres modules utilisent `round(x, 2)` (arrondi bancaire Python !). Voir Q4.

### 4.2 Pénalité + majoration de retard — `helpers.calculer_penalites(montant, échéance, date_paiement, module)`
```
si date_paiement ≤ échéance : (0, 0)
pen%  = param(module,'PENALITE_RETARD', 10)
maj1% = param(module,'MAJORATION_1ER_MOIS', 5)
majS% = param(module,'MAJORATION_MOIS_SUP', 0.5)

pénalité   = majore(montant × pen%)
diff_mois  = (aP − aE)·12 + (mP − mE)
             +1 si jourP > jourE et diff_mois > 0 et jourE < 28
mois       = max(1, diff_mois)
majoration = majore(montant × maj1% + montant × majS% × (mois − 1))
```
Aucun paramètre `PENALITE_RETARD` / `MAJORATION_*` n'est créé par défaut ➜ les valeurs par défaut (10 / 5 / 0,5) s'appliquent.

### 4.3 TNB — `tnb._compute_tarifs_annee`, `_calcul_annees_redevables`
- **Années redevables** : de `annee_debut_imposition` (sinon `max(2020, année d'acquisition)`, sinon 2020) à l'année en cours, moins les années payées et les années **exonérées** (permis de construire dont `annee_debut_exoneration ≤ année ≤ annee_fin_exoneration`).
- **Tarif** : 1ʳᵉ passe = tarif en vigueur sur l'année (`date_debut ≤ 31/12/N` et `date_fin` vide ou `≥ 01/01/N`) dont le libellé contient « ZONE X » ou le code « -X », avec `surface_min ≤ superficie ≤ surface_max` ; 2ᵉ passe = même chose sans filtre de zone. Ordre : `date_debut DESC`.
- **Principal** = `majore(valeur)` si unité = « DH » (forfait), sinon `majore(superficie × valeur)`.
- **Échéance** = **28 février N** (codée en dur).
- Si `principal > 0` et date de calcul > échéance :
  - **amende** = `max(majore(principal × AMENDE_NON_DECLARATION%), 500)` (param TNB, défaut **15 %**)
  - pénalité, majoration = §4.2 (module `TNB`)
- **Total** = `majore(principal + pénalité + majoration + amende)`.
- **Seuil** : total < **200 DH** ➜ déclaration `sous_seuil` (pas de bulletin).
- Numéro de dossier TNB = `MAX(numero_dossier)+1` (1 dossier par contribuable).

### 4.4 Débits de boissons (TDB) — `tdb.tdb_declarer`
- Périodes trimestrielles depuis (annee_debut, trimestre_debut) (défaut 2022 T1), proposées seulement une fois échues.
- **Échéances** : T1 → 30/04, T2 → 31/07, T3 → 31/10, T4 → 31/01 de N+1.
- **Taux** : selon le type d'établissement (mots-clés café / restaurant / hôtel / bar ➜ tarif dont le libellé contient le même mot-clé), sinon 1er tarif, sinon 10 %. Tarifs par défaut : café 6 %, bar 10 %, restaurant 5 %, hôtel-bar 8 %.
- **Principal** = `round(CA HT saisi × taux / 100, 2)`.
- Si date de déclaration > échéance : pénalité + majoration §4.2 (module `DEBITS_BOISSONS`).
- **Amende** (T4 uniquement) si date de déclaration ≥ 01/04/N+1 : `max(round(principal × 10 %, 2), 500)`.
- Total = `round(principal + pen + maj + amende, 2)` ; seuil 200 DH.
- ⚠️ L'échéance enregistrée pour T4 est `date(annee, 1, 31)` (même année) alors que la liste utilise N+1 — voir Q6.

### 4.5 Stationnement + TPV (« Transport ») — `stationnement.sta_payer`
- Trimestriel, mêmes échéances que TDB (T4 au 31/01 N+1), début par défaut 2020 T1.
- `tarif_sta` = tarif STATIONNEMENT dont le libellé = type du véhicule (sinon 1er tarif) ; `tarif_tpv` = idem rubrique TRANSPORT_VOYAGEURS. Les tarifs sont considérés **par trimestre** (le libellé par défaut dit « DH/an » — voir Q7).
- **Pénalités uniquement sur la part TPV** (§4.2, module `TRANSPORT_VOYAGEURS`) ; **aucune** sur le droit de stationnement ; pas d'amende.
- Principal = `round(sta + tpv, 2)` ; total = `round(principal + pen + maj, 2)` ; seuil 200 DH.
- Stockage détourné : `base_calcul` = TPV, `taux` = STA.
- Aucune ligne `tarifs` n'est créée par défaut pour TRANSPORT_VOYAGEURS.

### 4.6 Occupation du domaine public — `occupation._calculer_bases_tarif`
- Base trimestrielle selon l'unité du tarif : `trimestre` ➜ sup×val ; `an` ➜ sup×val/4 ; `mois` ➜ sup×val×3 ; `jour` ➜ sup×val×90 (annuel = sup×val×365) ; autre ➜ sup×val.
- Échéances = **fin du trimestre** (31/03, 30/06, 30/09, 31/12). Trimestre 0 = année entière (échéance 31/12).
- **Aucune pénalité ni majoration.** Seuil **100 DH** (et non 200).
- ⚠️ Bug Flask : `_get_odp_tarif` retourne `None` dès que le 1er tarif ne correspond pas (voir Q8).

### 4.7 Location des locaux — `location._calculer_loyer`, `_calculer_mois_non_payes`, `loc_payer`
- Loyer mensuel, priorité : tarif de l'arrêté fiscal lié au `loc_tarif` (si `type_tarif='m2'` ➜ × superficie) ➜ valeur du `loc_tarif` ➜ tarif du secteur ➜ `loyer_mensuel` du bail.
- Mois impayés : du mois de `date_debut` jusqu'à `min(date_fin, aujourd'hui)`, moins les mois payés ; tarif **par mois** pris dans l'historique des tarifs de l'arrêté.
- **Encaissement** : montant = `round(loyer_courant × nb_mois, 2)` (⚠️ le loyer **courant**, pas le tarif historique de chaque mois — voir Q9). Pas de pénalité.
- Le N° de bulletin est saisi ou généré `BUL{année}{00001}`.

### 4.8 Souks (affermage) — `souks._calculer_periodes_renouvellement`
- Périodes de `duree_contrat` années depuis `date_debut` ; à chaque renouvellement `redevance = round(redevance × (1 + taux_augmentation%), 2)` (défaut 5 %).
- Mois impayés = mois depuis `date_debut` jusqu'à `min(date_fin, aujourd'hui)` ; montant = somme des redevances de la période de chaque mois.
- **Paiement obligatoirement chronologique** (les mois choisis doivent être les plus anciens impayés).
- Pas de pénalité. ⚠️ « Renouveler » réécrit `date_debut` du contrat (voir Q10).

### 4.9 Fourrière — `fourriere._enrichir_dossier`
- `nb_jours = max(1, aujourd'hui − date_mise_fourriere + 1)`.
- Montant dû = `round(nb_jours × tarif_journalier + frais_remorquage, 2)` (tarifs par type : voiture 25, moto 15, utilitaire 35, camion 50, engin 60 DH/jour ; remorquage 150 DH).
- Alerte aliénation à 265 jours ; enchères à 365 jours ; le montant payé peut être modifié à la saisie.
- La validation du bulletin fait passer le dossier en `en_attente_sortie` ; la sortie exige un bulletin `paye/valide/encaisse`.

### 4.10 Régie des timbres — `regie.py`
- Ouverture d'un paquet = **10 carnets** : `pas = (dernier − premier + 1) // 10`, le dernier carnet prend le reste.
- Versement employé : `écart = montant_versé − valeur_unitaire × nb_unités_carnet`.
- Bordereau percepteur = somme des `montant_verse` des carnets sélectionnés ; numéro saisi ou auto.

### 4.11 Bordereaux d'émission — `emission/generator.py`
- Par ligne du bordereau GIR importé : `report = Σ montant_present` des mois antérieurs de l'année pour la même rubrique ; `total = montant + report`.
- Numéro de bordereau séquentiel **dans l'année** (repart du max des mois antérieurs) ; régénération d'un mois = suppression puis recréation.
- 1ʳᵉ partie = report global (auto) ou valeur manuelle ; 2ᵉ partie = total du mois (auto) / manuel / vide.

### 4.12 Budget — `regie_budget`
- Écart = réalisé − proposé ; taux = réalisé / proposé × 100 (1 décimale).
- Synchronisation « réalisé » = Σ `bulletins.montant` (statut paye/valide/encaisse) par module et par année de `date_paiement`, + Σ ventes aux enchères.

### 4.13 Numérotation
| Pièce | Format | Source |
|---|---|---|
| Déclaration (TNB, ODP…) | `DCL{AAAA}{00001}` via `MAX` | `gen_num` |
| Déclaration TDB / STA / ODP | `DCL-TDB{AAAA}{n}` où n = **COUNT(*) de toutes les déclarations** + 1 | non verrouillé |
| Location / Souks / Fourrière | `LOC-DECL…`, `SOU-DECL…`, `FOU-DECL…` | `gen_num` |
| Bulletin | saisi par l'agent (registre papier) ou `BUL{AAAA}{00001}` | |
| **Quittance** | **saisie manuellement** par le régisseur à la validation | aucune numérotation automatique |
| Avis | `AVS{AAAA}{00001}` ; lot `LOT{horodatage}` | |

---

## 5. Rôles et permissions existants

Rôles par défaut : `super_admin`, `admin` (tous droits), `agent_assiette` (voir, ajouter, modifier), `regisseur` (voir, valider paiement, créer bulletin), `consultant` (lecture).

Actions globales : `voir`, `ajouter`, `modifier`, `supprimer`, `creer_bulletin`, `valider_paiement`, `config`, `exporter`, `voir_dashboard`, `outils`.
Surcharge par module (rôle ou utilisateur) : voir / ajouter / modifier / supprimer / exporter.
Un utilisateur peut avoir des permissions « sur mesure » (`custom_permissions=1`) qui ignorent son rôle.

➜ Proposition spatie : permissions `{module}.{voir|ajouter|modifier|supprimer|exporter}` + permissions globales `bulletins.creer`, `paiements.valider`, `config.gerer`, `dashboard.voir`, `outils.utiliser` ; rôles cibles du CLAUDE.md : **Régisseur**, **Régisseur suppléant**, **Collecteur** (≈ agent_assiette), **Consultation/Contrôle** (≈ consultant), **Administrateur**. Voir Q13.

Sécurité de session : déconnexion après **10 min** d'inactivité (paramétrable, `config.json › auto_logout_minutes`), changement de mot de passe forcé, politique de mot de passe, CSRF, anti-bruteforce.

---

## 6. Documents imprimés

| Document | Template / générateur |
|---|---|
| Liste des contribuables | `contribuables/contribuables_pdf.html` |
| Avis de non-paiement (contribuable, TNB, souk, stationnement, ODP, TDB) | `*_avis*.html` |
| Avis TNB en lot | `tnb/tnb_avis_lot.html` |
| Déclaration TNB annuelle | `tnb/tnb_declaration_pdf.html` |
| Déclarations TDB : création, CA, cessation, annuelle | `tdb/tdb_declaration_*_pdf.html` |
| États par module (listes) | `tnb_pdf`, `tdb_pdf`, `sta_pdf`, `odp_pdf`, `loc_pdf`, `sou_pdf`, `fou_pdf` |
| Lettre de notification / relance | `admin/lettre_notification.html` |
| Groupe d'enchères fourrière | `fourriere/fou_groupe_impression.html` |
| Bordereau de versement régie | `regie/bordereau_print.html` |
| Budget (FR/AR) | `regie/budget_pdf.html` |
| Relevé TGR | `regie/tgr_pdf.html` |
| Bordereau d'émission par rubrique | `emission/pdf_exporter.py` (ReportLab) |
| En-tête officiel bilingue | `components/official_header.html` + `static/css/official_print.css` |

**Il n'existe pas** de quittance imprimée par l'application (la quittance est celle du registre papier), ni de Modèle 6/7/10, ni de PV (hors périmètre).

---

## 7. Anomalies relevées dans le code Flask (impact parité)

1. **Annulation = suppression physique** (`DELETE` sur déclarations et bulletins) — contraire à la règle « pièces immuables » du CLAUDE.md.
2. Arrondis incohérents : `majore_centimes` (TNB, pénalités) vs `round()` Python (arrondi bancaire : `round(2.675, 2) = 2.67`).
3. Échéance TNB codée au 28/02 alors que le paramètre `DATE_LIMITE` TNB vaut 31/03 (et la loi 47-06 citée en commentaire dit 31 mars).
4. Amende TNB appliquée à **chaque** année en retard, y compris l'année en cours dès le 1er mars, même si aucune déclaration n'était due.
5. TDB : `date_echeance` T4 enregistrée au 31/01/N au lieu de N+1 ➜ pénalités T4 surévaluées d'un an.
6. ODP : `_get_odp_tarif` sort de la boucle au 1er tarif non correspondant ➜ `None` ➜ montant 0 ou plantage à la déclaration.
7. Location : encaissement au loyer courant alors que l'écran affiche le tarif historique de chaque mois.
8. Souks : le renouvellement écrase `date_debut` ➜ les mois impayés antérieurs disparaissent du calcul.
9. Numérotation TDB/STA/ODP basée sur `COUNT(*)` ➜ collisions possibles après suppression.
10. Mois payés (Location/Souks) stockés dans un champ texte `notes`.
11. `commune_id` forcé à 1 partout (mono-commune).
12. Validation en masse : ne cascade pas sur les bulletins partageant le même N° BV (contrairement à la validation unitaire).

---

## 8. Stratégie de migration des données (Phase 3, pour information)

- `php artisan jibayat:import-sqlite {chemin}` lira une base **SQLite en clair**. Deux options pour l'obtenir : (a) l'export de base existant dans *Paramètres système* (`/api/systeme/export-db`) s'il produit un fichier déchiffré, ou (b) un petit script Python lancé une fois sur le poste, qui utilise la clé du coffre pour faire `sqlcipher_export` vers un fichier clair. Voir Q2 (§9).
- Import idempotent par clé naturelle (`numero`, `numero_bulletin`+`declaration_id`, etc.) avec conservation des `id` source dans une colonne `legacy_id`.
- Normalisations : texte « None » ➜ NULL, dates `TEXT` ➜ `DATE`, `notes` Location/Souks ➜ `declaration_periods`, `module` + `reference_id` ➜ relation polymorphe.
- Rapport : lignes source / importées / rejetées par table + contrôle des sommes (Σ montant_total, Σ bulletins payés par module et par année).
- Mots de passe : à vérifier (README dit SHA-256 ; si hash non compatible bcrypt ➜ réinitialisation forcée à la 1ère connexion ou re-hash à la volée).

---

## 9. Questions (à trancher avant la Phase 2)

**Tranché le 19/11/2026** : référence = code Flask `jibayat_app-main/` ; Abattoir, PV, scanner et
WhatsApp hors périmètre ; les modules Débits de boissons, Domaine public, Fourrière,
Contribuables, Caisse, Avis & lettres, Régie des timbres, Budget, Relevés GIR et Émission sont
migrés comme espaces à part entière (§3 bis.2).

**Périmètre et données**
1. **Couleurs des nouveaux espaces** : je propose navy/or (Caisse & recouvrement), bordeaux
   (Débits de boissons), vert (Domaine public) et anthracite (Fourrière). D'accord ?
2. **Données réelles** : la base `%APPDATA%\JIBAYATiscalite.db` est chiffrée. Puis-je préparer
   un script d'export en clair à lancer sur ce poste, ou fournirez-vous un export (et où le placer) ?
3. **Extension Chrome GIR TGR** (`gir_bridge.js`, transmission vers le site GIR) : à conserver
   dans la version Laravel ?

**Règles de calcul** (reproduire le comportement Flask, ou le corriger ?)
4. **Arrondis** : conserver exactement Flask (centime supérieur en TNB, arrondi bancaire
   ailleurs) ou harmoniser sur « centime supérieur » partout ?
5. **Échéance TNB** : 28 février (code) ou 31 mars (paramètre et loi 47-06) ? L'amende de 15 %
   (min. 500 DH) doit-elle frapper chaque année, y compris l'année en cours dès le 1er mars ?
6. **Débits de boissons T4** : corriger l'échéance enregistrée (31/01/N+1) ou reproduire l'existant ?
7. **Tarifs stationnement/TPV** : par trimestre (comme le calcule le code) ou par an (comme
   l'indiquent les libellés par défaut « DH/an ») ?
8. **Domaine public** : corriger la recherche de tarif (bug §7.6) ? Le seuil de 100 DH (contre
   200 DH ailleurs) est-il voulu ?
9. **Location** : encaisser au tarif historique de chaque mois (ce qu'affiche l'écran) ou au
   loyer courant (ce qu'encaisse le code) ?
10. **Souks** : le renouvellement doit-il conserver la date de début d'origine (pour garder les
    impayés antérieurs) ?

**Pièces et workflow**
11. **Quittances** : le N° de quittance est aujourd'hui **saisi** par le régisseur (carnet papier).
    Garder la saisie manuelle avec contrôle d'unicité (ma recommandation, c'est l'usage réel), ou
    passer à une numérotation automatique ?
12. **Annulation** : remplacer toutes les suppressions par une annulation motivée (auteur, date,
    motif) visible dans « Pièces annulées » ?
13. **Rôles** : super_admin/admin ➜ Administrateur, regisseur ➜ Régisseur, *(nouveau)* Régisseur
    suppléant = droits du régisseur, agent_assiette ➜ Collecteur, consultant ➜ Consultation/Contrôle.
    D'accord ?
14. **Fonctions desktop** (licence par empreinte matérielle, essai 90 jours, télémétrie, mises à
    jour OTA GitHub, sauvegarde Google Drive, systray) : à abandonner pour la version serveur ?
15. **Multi-commune** : une instance par commune (comme aujourd'hui) ?

**Design**
16. ~~Captures `docs/design/`~~ — **tranché** : ce sont seulement des exemples de style
    (couleurs, boutons, polices), pas des maquettes à reproduire écran par écran. Le dossier est
    encore absent du poste ; s'il est ajouté, je m'en servirai comme référence de style, sinon
    je suis la description du CLAUDE.md §4.

---

## 10. Ordre de la suite

Phase 2 (socle Laravel, design du CLAUDE.md §4, `config/navigation.php` avec les espaces du
§3 bis.2, accueil complet et pages vides) ➜ Phase 3 (schéma MySQL + `jibayat:import-sqlite`) ➜
Phase 4 (Paramètres : rubriques, arrêtés, tarifs, paramètres de calcul, utilisateurs, régie) ➜
Phase 5, module par module : Contribuables + Caisse ➜ Locations ➜ TNB ➜ Transport ➜ Souk ➜
Débits de boissons ➜ Domaine public ➜ Fourrière ➜ États & documents ➜ Phase 6 (sans WhatsApp).

Les tests de parité seront générés à partir de jeux de données figés (date du jour injectée),
en exécutant les fonctions Python d'origine pour produire les résultats attendus (fichiers JSON
de référence), puis en les comparant aux services Laravel.
