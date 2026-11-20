# CLAUDE.md — Migration Jibayat (Flask/SQLite ➜ Laravel/MySQL)

> Ce fichier est lu automatiquement par Claude Code à chaque session.
> Il décrit le projet, la cible technique, le design attendu et la méthode de travail.

---

## 1. Contexte

**Jibayat (جبايات)** est l'application de gestion de la **régie des recettes** de la
Commune Aït Amira (Province Chtouka Aït Baha, Maroc). Elle est utilisée par le
régisseur et ses suppléants pour encaisser, suivre et justifier les recettes
communales : locations (boutiques, locaux), TNB, taxe sur le transport de voyageurs,
souk, abattoir, documents comptables officiels, procès-verbaux, archives.

- **Version actuelle :** Python / Flask / SQLite (version 9.0)
- **Cible :** Laravel + MySQL, avec un thème moderne de **plateforme administrative marocaine**
- **Langues :** français (principal) + arabe (libellés, noms, documents). Prévoir le RTL.
- **Utilisateurs :** régisseur, régisseurs suppléants, collecteurs, consultation/contrôle.

## 2. Règles absolues

1. **Ne jamais modifier le projet Flask d'origine.** Il sert de référence. Le projet
   Laravel est créé dans un dossier séparé (`jibayat-laravel/`).
2. **La logique métier doit rester identique** : calculs de loyers, TNB (principal,
   majoration, pénalité, amende), trimestres transport, restes à recouvrer, numérotation
   des quittances, arrêtés de caisse. Chaque calcul porté doit avoir un test qui compare
   le résultat Laravel au résultat Flask sur les mêmes données.
3. **Aucune perte de données** : la migration SQLite ➜ MySQL se fait par une commande
   Artisan rejouable, avec rapport de comptage (lignes source / lignes importées / écarts).
4. **Montants en `DECIMAL(14,2)`**, jamais en `float`. Dates en `DATE`/`DATETIME`.
5. **Pièces officielles immuables** : une quittance, un bordereau ou un PV édité ne se
   modifie pas ; il s'annule (avec motif, auteur, date) et apparaît dans « Pièces annulées ».
6. Travailler **par phases** (section 7). À la fin de chaque phase : résumé, liste des
   fichiers, commandes de test, et **attendre ma validation** avant la phase suivante.
7. Me poser une question plutôt que deviner quand une règle métier n'est pas claire dans
   le code Flask.

## 3. Stack technique cible

| Besoin | Choix |
|---|---|
| Framework | Laravel (dernière version stable — vérifier avec `composer create-project`) |
| PHP | 8.3+ |
| Base | MySQL 8 (`utf8mb4_unicode_ci`) |
| Front | Blade + **Livewire 3** + Alpine.js + **Tailwind CSS** (Vite) |
| Auth | Laravel Breeze (Blade) adapté au thème, sessions |
| Rôles / permissions | `spatie/laravel-permission` |
| Journal d'activité / audit | `spatie/laravel-activitylog` |
| Excel / CSV (import & export) | `maatwebsite/excel` |
| PDF avec arabe | `mpdf` (via `carlos-meneses/laravel-mpdf`) — dompdf gère mal l'arabe |
| Empreinte / biométrie | WebAuthn (`laragear/webauthn`) |
| Notifications e-mail planifiées | Scheduler Laravel + Mailables (« Rapports par e-mail ») |
| Tests | Pest |

Déconnexion automatique après **10 min d'inactivité** (paramétrable), affichée dans
l'en-tête : « Empreinte activée · fermeture après 10 min sans activité ».

## 4. Design — « plateforme administrative marocaine »

Les captures de référence sont dans `docs/design/`. Les reproduire fidèlement,
en **mobile-first**, puis adapter au bureau (barre latérale à gauche, grille 4–6 colonnes).

### 4.1 Palette

```
--bg:            #F2F3F5   fond général gris très clair
--surface:       #FFFFFF   cartes / tuiles
--border:        #E6E8EC
--text:          #1F2933
--text-muted:    #6B7280
--navy:          #1E2A3A   en-tête (bandeau utilisateur)
--gold:          #C9A13B   badge rôle, bouton empreinte, bordure carte active
--orange:        #C8601E   accent principal (icône recherche, alertes, téléchargement)
--success:       #16A34A   point "en ligne"
```

**Couleur par espace (module)** — dégradé de la carte + couleur des icônes de tuiles
+ petit trait devant les titres de section :

| Espace | Dégradé carte | Icône tuile |
|---|---|---|
| États & documents | olive/or `#9C7A2E → #3B3A2E` | or `#B08A3A` |
| Locations | orange brûlé `#C65A1A → #4A2A1A` | orange `#C8601E` |
| Transport | bleu ardoise `#3E5C7E → #1F2E40` | bleu-gris `#5B7590` |
| TNB | bronze `#8B5A2B → #3A2A1E` | brun `#8B5A2B` |
| Paramètres | bleu acier `#46617F → #22303F` | bleu-gris `#5B7590` |
| Souk | jaune doré `#D4A52E` | |
| Abattoir | orange rouge `#C2410C` | |

### 4.2 Motif zellige

Un **motif géométrique marocain** (étoiles à 8 branches / entrelacs) en SVG, superposé
en faible opacité (6–12 %) sur l'en-tête et sur les cartes d'espaces. Le créer en SVG
original (`resources/svg/zellige.svg`) et l'utiliser en `background-image`.
L'emblème de chaque espace est une **étoile à 8 branches (octogramme)** contenant
une icône blanche.

### 4.3 Typographie

- Latin : **Roboto** ou **Inter** (400/500/700)
- Arabe : **Noto Kufi Arabic** ou **Cairo**
- Titres de section : MAJUSCULES, `letter-spacing: .08em`, 13px, gras
- Polices servies en local (pas de dépendance Internet obligatoire sur le serveur de la commune)

### 4.4 Composants (Blade components dans `resources/views/components/`)

1. **`<x-user-header>`** — carte navy arrondie (24px) avec motif zellige :
   avatar (emblème octogonal or + point vert « en ligne »), nom en gras,
   badge rôle doré en pilule (« Régisseur suppléant »), « · session ouverte »,
   bouton rond doré **empreinte**, bouton rond translucide **déconnexion**,
   sous-ligne d'état de session.
2. **`<x-page-search>`** — barre de recherche en pilule blanche, loupe orange,
   placeholder « Chercher une page : rubriques, souk, clôture… ». Recherche
   instantanée (Livewire) sur **toutes les pages de tous les espaces** (titre +
   sous-titre + mots-clés FR/AR).
3. **`<x-recent-chips>`** — « RÉCENTS » : puces horizontales défilantes
   (icône dans carré coloré + libellé), alimentées par l'historique de l'utilisateur.
4. **`<x-space-carousel>`** — « ESPACES » : grandes cartes (≈ 160×150) défilantes avec
   *scroll-snap*, dégradé du module, motif zellige, emblème octogonal, titre,
   « N pages · description ». La carte active a une **bordure dorée**. Le nombre de
   pages est calculé automatiquement depuis le registre de navigation.
5. **`<x-section-title>`** — trait coloré court + titre en majuscules + compteur à droite.
6. **`<x-tile>`** — tuile blanche (radius 16px, bordure fine, ombre très légère) :
   icône dans carré arrondi au dégradé du module, titre en gras centré,
   sous-titre gris (peut être en arabe, ex. « أداء الرسم »). Grille 3 colonnes mobile.
7. **`<x-footer-card>`** — « Application · version X — Vérifier les mises à jour »
   (icône téléchargement orange, bouton chevron rond) et « Signaler un problème »
   (icône alerte).

Icônes : jeu cohérent (Lucide ou Heroicons) en SVG inline. Animations discrètes
(hover/press, transition de carte). Accessibilité : contrastes AA, focus visibles.

### 4.5 Navigation pilotée par configuration

Toute l'arborescence vit dans **`config/navigation.php`** (espace ➜ sections ➜ pages :
`route`, `title`, `subtitle`, `icon`, `keywords`, `permission`). L'accueil, le carrousel,
les compteurs, la recherche et le menu bureau sont générés depuis ce fichier.
Une page n'apparaît que si l'utilisateur a la permission correspondante.

## 5. Arborescence fonctionnelle (référence des captures)

### États & documents — 25 pages
- **Documents comptables (7)** : État mensuel *(Modèle 10)* · Livre de caisse *(Modèle 6)* ·
  Bordereau d'émission *(par rubrique)* · Récapitulatif comptable *(le mois)* ·
  Opérations Trésor *(Modèle 7)* · Taxe spéciale *(bordereau)* · Autorisation virement *(Ministère)*
- **Contrôle (5)** : Centre de contrôle *(ce qui cloche)* · Rapprochement *(état)* ·
  Contrôle croisé *(recettes × livre)* · Bordereau de versement *(au Trésor)* · Pièces annulées *(état)*
- **Archive & scans (6)** : Scanner *(une feuille fait-elle foi ?)* · Visas en lot *(TGR)* ·
  Archive des documents *(pièces éditées)* · Envois au Trésor *(bordereaux d'envoi)* ·
  Boîtes d'archive *(rangement)* · Contrôle du registre *(suivi des scans)*
- **Rapports (3)** : Rapports détaillés *(période au choix)* · Bilan annuel *(tableau des recettes)* · Rapport annuel *(l'exercice)*
- **Procès-verbaux (4)** : Arrêté de caisse · Vérification de caisse · Récolement *(tickets et vignettes)* · Remise de service

### Locations — 17 pages
- **Gestion (7)** : Encaisser *(saisie des loyers)* · Locataires *(contrats, zones)* ·
  Fiche locataire *(situation détaillée)* · Vue d'ensemble · Historique *(d'un locataire)* ·
  Révision du loyer *(nouveau montant)* · Contentieux *(dossiers)*
- **Recouvrement (4)** : Relances *(à relancer)* · WhatsApp *(message prêt)* ·
  Suivi des relances *(historique)* · Lettres *(de relance)*
- **États (6)** : RAR par année · Suivi mensuel · Situation globale · Tableau de bord ·
  Loyers ↔ bordereaux *(rapprochement)* · Rapport des loyers

### Transport (taxe sur le transport de voyageurs) — 14 pages
- **Gestion (8)** : Encaisser *(un trimestre)* · Véhicules *(parc, agréments)* ·
  Registre *(les quittances)* · Fiche véhicule · Vue d'ensemble · Historique *(par propriétaire)* ·
  WhatsApp · Import Excel
- **États & rapports (6)** : État RAR · Suivi trimestriel *(T1 → T4)* · Situation *(par véhicule)* ·
  Tableau de bord · Tableau croisé *(véhicules × trimestres)* · Rapport *(synthèse)*

### TNB (terrains non bâtis) — 17 pages
- **Gestion foncière (6)** : Encaisser *(أداء الرسم)* · Parcelles *(lots / بقع)* · Vue d'ensemble ·
  Récupération *(استرجاع)* · Reprise historique *(années déjà réglées)* · Import Excel / CSV
- **Recouvrement (5)** : État RAR · Relances *(débiteurs)* · Contentieux · Évolution du RAR *(sur 18 mois)* · Rapport
- **Suivi (6)** : Tableau de bord · Situation *(par parcelle)* · Suivi *(encaissements)* ·
  Tableau croisé *(parcelles × années)* · Journal *(opérations)* · Propriétaire *(fiche)*

### Paramètres — 11 pages
- **Système (3)** : Notifications · Quoi de neuf ? · État du système *(serveur, base)*
- **Journaux (2)** : Journal d'activité · Audit logs *(journal, sécurité, équipe)*
- **Configuration (6)** : Rubriques *(nomenclature)* · Tarifs & configuration *(la régie)* ·
  Abattoir : tarifs *(tarifs et collecteurs)* · Bouchers · Intervenants *(collecteurs, services)* ·
  Rapports par e-mail *(envois automatiques)*

### Autres espaces présents dans l'application Flask
**Souk**, **Abattoir** (Registre de l'abattoir), et tout autre module trouvé dans le code
Flask : les inventorier en phase 1 et les ajouter avec le même modèle de page.

## 6. Conventions de code

- Modèles Eloquent en anglais (`Tenant`, `Lease`, `Payment`, `Parcel`, `Vehicle`,
  `Receipt`, `Rubric`…), libellés d'interface en français/arabe via `lang/fr` et `lang/ar`.
- Un dossier par espace : `app/Domain/{Locations,Transport,Tnb,Documents,Souk,Abattoir}`
  (Models, Services, Actions). Les **calculs** sont dans des classes `Services` pures et testées.
- Contrôleurs fins ; validation par `FormRequest` ; autorisations par Policies + permissions.
- Toute écriture financière dans une **transaction DB** ; numérotation des quittances
  verrouillée (`lockForUpdate`) pour éviter les doublons.
- Chaque action sensible journalisée (activitylog) : encaissement, annulation, révision,
  modification de tarif, connexion/déconnexion.
- Migrations avec clés étrangères, index sur les colonnes de recherche (CIN, n° parcelle,
  immatriculation, n° quittance, année, trimestre).
- Impressions (quittances, états modèles 6/7/10, PV, lettres) : vues Blade dédiées
  rendues en PDF A4 avec mPDF, entête commune bilingue.
- `.env.example` complet ; aucun secret dans le dépôt.

## 7. Plan de travail par phases

**Phase 1 — Analyse (pas de code Laravel)**
Lire tout le projet Flask (`app.py`/blueprints, modèles, schéma SQLite, templates,
scripts de calcul). Produire `docs/MIGRATION_PLAN.md` :
inventaire des tables (colonnes, types, volumes), routes ➜ pages, règles de calcul
(avec formules), rôles existants, documents imprimés, points ambigus (questions pour moi).

**Phase 2 — Socle**
Projet Laravel, MySQL, Tailwind/Livewire, auth + rôles, `config/navigation.php`,
layout et **tous les composants de design** (section 4) avec l'accueil complet et des
pages vides pour chaque entrée. ➜ Je valide le visuel sur mobile et bureau.

**Phase 3 — Base de données & import**
Migrations MySQL + `php artisan jibayat:import-sqlite {chemin}` (idempotent, rapport d'écarts).

**Phase 4 — Paramètres** (rubriques, tarifs, intervenants, utilisateurs, journaux).

**Phase 5 — Modules métier**, un par un, dans cet ordre : Locations ➜ TNB ➜ Transport ➜
Souk/Abattoir ➜ États & documents. Pour chaque module : modèles, services de calcul +
tests de parité avec Flask, pages, impressions, imports Excel, WhatsApp (lien `wa.me`
avec message pré-rempli).

**Phase 6 — Finitions**
Recherche globale, récents, notifications, rapports e-mail planifiés, WebAuthn,
mise à jour applicative, « Signaler un problème », performances, guide de déploiement
VPS (Nginx + PHP-FPM + MySQL + Supervisor pour les queues + cron du scheduler).

## 8. Commandes utiles

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan jibayat:import-sqlite ../jibayat-flask/instance/jibayat.db
npm run dev        # ou npm run build
php artisan serve
php artisan test
```

## 9. Définition de « terminé » pour une page

- Fidèle au design (couleurs du module, tuiles, titres de section, en-tête).
- Fonctionne sur téléphone (360px) et bureau (1440px), en FR et avec contenus arabes.
- Permissions appliquées, actions journalisées.
- Calculs couverts par des tests qui passent.
- Impression PDF correcte (arabe lisible, A4).
