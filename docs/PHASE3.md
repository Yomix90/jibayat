# Phase 3 — Base de données & import : résumé

> Livré le 19/11/2026. En attente de validation avant la Phase 4 (Paramètres).

## Résultat de l'import réel

Source : `data/jibayat_plain.db` (export en clair de la base Flask, fait par youssef).
**Import terminé sans écart**, rejoué une seconde fois sans doublon (toutes les lignes en « mises à jour »).

| Table Flask | Table Laravel | Lignes |
|---|---|---|
| communes | municipalities | 1 |
| app_modules | app_modules | 11 |
| utilisateurs | users | 1 (rattaché au compte `admin` existant) |
| rubriques | rubrics | 8 |
| arretes_fiscaux | tax_orders | 1 |
| tarifs | tariffs | 17 (Σ valeurs 1 458,00 = 1 458,00) |
| parametres_calcul | calc_parameters | 10 |
| fou_parametres | impound_settings | 14 (dont 5 doublons « capacite/CAPACITE_MAX » de Flask, conservés) |
| regie_valeurs | stamp_values | 2 |
| regie_services | services | 2 |
| regie_budget | budget_lines | 96 (Σ réalisé N-1 18 237 591,23 et Σ proposé 19 504 315,00 identiques) |
| user_logs | activity_log | 1 |

La base Flask actuelle ne contient **encore aucun contribuable, terrain, déclaration ni bulletin** :
seule la configuration est importée. La commande est prête pour une base remplie (testée sur une base
d'essai couvrant tous les modules).

Non migrées (listées avec leur nombre de lignes dans chaque rapport) : tables techniques
(`schema_meta`, `_sys_sec_vault`, `login_attempts`, `remember_tokens`), `roles` et permissions Flask
(remplacées par les rôles spatie, correspondance appliquée aux comptes), et 16 tables héritées que
Flask v3 n'utilise plus (toutes vides).

## Schéma MySQL

- 47 tables métier en 5 migrations (`database/migrations/2026_11_20_*`), noms anglais, commentaires
  donnant la colonne Flask d'origine.
- Montants `DECIMAL(14,2)`, taux et tarifs unitaires `DECIMAL(14,4)`, dates `DATE`/`DATETIME`.
- Clés étrangères partout, index sur CIN, ICE, RC, n° parcelle, titre foncier, immatriculation,
  n° de bulletin, n° de quittance, année, trimestre, module.
- `legacy_id` (id Flask) sur chaque table : import rejouable.
- Pièces officielles (déclarations, bulletins/quittances, avis, lettres, bordereaux) : colonnes
  `cancelled_at`, `cancelled_by`, `cancellation_reason` ; le trait `Cancellable` **interdit la
  suppression** et impose un motif ; chaque annulation est journalisée.
- Les mois réglés Location/Souk, stockés en texte dans Flask (`notes`), deviennent des lignes
  `tax_declaration_periods` (AAAA-MM).
- Une déclaration pointe vers son objet taxé (terrain, établissement, véhicule, occupation, dossier
  fourrière, bail, contrat souk) par une relation polymorphe.

## Modèles

48 modèles Eloquent : référentiel dans `app/Models`, puis un dossier par espace
`app/Domain/{Tnb,Boissons,Transport,DomainePublic,Fourriere,Locations,Souk,Caisse,Documents}/Models`.

## Commande d'import

```powershell
php artisan jibayat:import-sqlite ..\data\jibayat_plain.db --dry-run   # simulation, n'écrit rien
php artisan jibayat:import-sqlite ..\data\jibayat_plain.db             # import (rejouable)
```

Le rapport (console + `storage/app/import-reports/*.json`) donne pour chaque table : lignes source,
insérées, mises à jour, rejetées (avec motif), écart, avertissements (dates illisibles, références
orphelines, objets taxés introuvables…) et contrôles de sommes. Code de sortie 1 au moindre écart.

## Points à connaître

- **Mots de passe** : Flask utilise werkzeug « scrypt », impossible à vérifier en PHP. Les comptes
  importés reçoivent un mot de passe aléatoire et l'obligation de le changer ; l'administrateur les
  réinitialisera (écran Utilisateurs, Phase 4). Un compte qui existe déjà côté Laravel (ex. `admin`)
  garde son mot de passe.
- **E-mails en double** dans Flask : le second compte est importé sans e-mail (signalé).
- **Données en clair** : `data/jibayat_plain.db` contient la base Flask déchiffrée. À supprimer dès
  que l'import définitif sera fait.

## Tests

`php artisan test` : **58 tests, 810 assertions, tous verts**, dont 12 sur l'import (base Flask
d'essai au schéma réel : comptes, sommes, idempotence, simulation, conversions, arabe, périodes,
annulations, orphelins, rôles, journal) et 3 sur l'annulation des pièces.
