# Phase 4 — Paramètres : résumé

> Livré le 19/11/2026. En attente de validation avant la Phase 5 (modules métier, en commençant par Locations).

## Écrans livrés (espace Paramètres)

| Écran | Ce qu'on peut faire | Référence Flask |
|---|---|---|
| Rubriques | créer, modifier (libellés FR/AR, code budgétaire), activer/désactiver ; le code module est fixé à la création | `/rubriques` |
| Arrêtés fiscaux & tarifs | liste ; nouvel arrêté (clôt l'arrêté actif, recopie les derniers tarifs par rubrique avec la nouvelle date d'effet, ou un tarif à 0 à compléter) | `/arretes-fiscaux` |
| Détail d'un arrêté | tarifs groupés par rubrique ; ajouter / modifier / supprimer un tarif (le tarif ouvert de même libellé est clôturé) ; ajouter une rubrique ; tarifs types ; retirer un module ; vider ; supprimer l'arrêté **avec motif obligatoire** ; historique des autres arrêtés | `/arretes-fiscaux/<id>` |
| Paramètres de calcul | modifier pénalités, majorations, amendes, échéances, seuils ; « Valeurs légales » (Loi 47-06) | `/parametres` |
| Intervenants | services, employés détenteurs de carnets, valeurs de timbres (valeur × unités = valeur d'un carnet) ; un élément utilisé ne se supprime pas, il se désactive | `/regie/config` |
| Utilisateurs | créer, modifier, rôle, mot de passe provisoire, désactiver / réactiver (jamais supprimer ; impossible sur son propre compte) | `/utilisateurs` |
| Rôles & permissions | matrice module × (voir, ajouter, modifier, supprimer, exporter) + droits transversaux ; l'administrateur garde tout | `/roles/permissions` |
| Journal d'activité | filtre par journal, recherche, dates ; détail avant → après de chaque modification | `user_logs` |
| Commune | identité FR/AR + aperçu de l'en-tête officiel bilingue | `/commune/modifier` |
| Modules actifs | activer / désactiver un module : il disparaît de l'accueil, de la recherche et du menu, et ses pages sont bloquées (données conservées) | `/api/systeme/modules` |
| Sécurité des sessions | délai de déconnexion automatique (1 à 240 min), sessions ouvertes | `/api/systeme/session-config` |
| État du système | versions, base, taille, espace disque, volumes, dernier import Flask | `/parametres-systeme` |

Restent « en préparation » dans Paramètres : Sauvegardes, Journal des imports, Importation Excel
(avec les modules métier en phase 5) et Guide / À propos / Signaler un problème (phase 6).

## Règles reprises de Flask

- **Mot de passe** : 10 caractères minimum, majuscule, minuscule, chiffre, et refus des mots de passe
  courants (liste Flask). Un mot de passe fixé par l'administrateur, ou un compte importé, **doit être
  changé** avant d'accéder à autre chose que son profil.
- **Arrêtés** : un seul actif ; numéro `AF-{année}-{n°}` ; code tarif = code rubrique + 3 premières
  lettres des 2 premiers mots du libellé (`30101014-ZONA`) ; un tarif dont la date de fin est passée
  devient inactif ; supprimer un tarif lié à un tarif de location détache ce lien (comme Flask).
- **Valeurs légales** : ne modifient que les paramètres existants (comme Flask).

## Améliorations par rapport à Flask

- Toute écriture exige le droit « Gérer la configuration » (vérifié côté serveur, même en appel direct).
- **Journalisation automatique** de chaque création / modification / suppression de rubrique, tarif,
  arrêté, paramètre, intervenant, utilisateur, commune, module et réglage (jamais de mot de passe dans le
  journal), plus les modifications de rôles et les suppressions d'arrêté avec leur motif.
- La suppression d'un arrêté demande un motif écrit (Flask : une question de calcul).
- Relancer le seeder ne réécrase plus les droits personnalisés des rôles.

## Tests

`php artisan test` : **83 tests, 932 assertions, tous verts** (25 nouveaux : 9 sur les règles des
arrêtés/tarifs, 16 sur les écrans, les droits, le changement de mot de passe obligatoire, les modules,
les sessions et le journal). Vérifié dans le navigateur sur les données importées (arrêté AF-2020-001 et
ses 17 tarifs), sur ordinateur et sur téléphone.

## Complément (19/11/2026) — accueil et navigation, à la demande de youssef

- **Espaces retirés** : plus de carrousel de cartes, de puces « Récents » ni de bandeau utilisateur sur
  l'accueil. La navigation se fait **uniquement par le menu latéral** (fixe sur ordinateur, tiroir
  avec bouton ☰ sur téléphone). Une barre du haut regroupe la recherche de page, l'état de session,
  l'empreinte et le menu du compte (profil, déconnexion).
- **Accueil = tableau de bord des statistiques**, même structure que Flask (`app.py::index`), au thème
  des captures : 5 indicateurs (total émis, recouvré + taux, en attente régisseur, reste à recouvrer +
  avis, redevables + dossiers), graphique mensuel émis / encaissé de l'année, répartition des
  encaissements par module, une carte par module (émis, recouvré, reste, barre de taux), dernières
  quittances, bulletins en attente, dernières déclarations. Mêmes formules que Flask, déclarations
  annulées exclues ; un module désactivé ou non autorisé n'apparaît pas. Sans le droit « voir le
  tableau de bord », l'utilisateur arrive sur sa première page autorisée (comme Flask).
- Graphiques : Chart.js installé en local (aucune dépendance Internet).
- Correction par rapport à Flask : le nombre de dossiers fourrière comptait une table héritée vide
  (`fou_dossiers`) ; il compte maintenant les vrais dossiers.
- Fichiers : `app/Domain/Dashboard/DashboardStats.php`, `resources/views/home.blade.php`,
  `resources/views/components/top-bar.blade.php`, `resources/js/charts.js`, `tests/Feature/DashboardTest.php`.
- Tests : **89 verts, 990 assertions**.

## Fichiers principaux

- `app/Livewire/Parametres/*` et `resources/views/livewire/parametres/*` (12 écrans)
- `app/Domain/Parametres/Services/TaxOrderService.php` (règles des arrêtés et tarifs)
- `app/Support/Concerns/Journaled.php`, `app/Models/Setting.php`, `app/Rules/NotCommonPassword.php`
- `app/Http/Middleware/{RequirePasswordChange,EnsureModuleActive}.php`
- composants d'écran : `resources/views/components/{page-header,card,btn,field,badge,panel,toast,empty-state,input,select,textarea}.blade.php`
- `tests/Feature/Parametres/*`
