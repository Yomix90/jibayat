# Phase 5 — Module TNB (terrains non bâtis)

Statut : **terminé, en attente de validation**.

## Ce qui est livré

| Page | Route | Contenu |
|---|---|---|
| Dossiers & terrains | `tnb.terrains` | Un dossier par redevable (n° = max + 1, créé automatiquement), terrains et situation de chacun, 5 indicateurs, filtres situation / zone / lotissement / recherche, alertes de fin d'exonération (≤ 60 jours), ajout / modification / suppression, impression PDF A4 paysage |
| Fiche terrain | `tnb.fiche` | Redevable, terrain, situation (FR/AR), déclarations avec **annulation motivée**, permis, copropriétaires, transferts, documents, autres terrains du dossier, archivage |
| Encaisser (أداء الرسم) | `tnb.encaisser` | Calcul par année à la date de déclaration (tarif, principal, pénalité, majoration, amende, total), sélection des années, un n° de bulletin commun, réémission des années au bulletin rejeté |
| Recensement (استرجاع) | `tnb.recensement` | Terrains repérés sans dossier ; « Transférer » vers la liste principale (crée le dossier) ; retrait |
| Avis de non-paiement | `tnb.avis` | Terrains débiteurs (filtre années / zone), avis individuel ou sélection |
| Avis en lot | `tnb.avis-lot` | Même liste, tous les débiteurs présélectionnés |
| Tableau de bord, Registre | `tnb.tableau-de-bord`, `tnb.registre` | Écrans génériques des modules (module `TNB`) |

Impressions : `tnb/imprimer/dossiers`, `tnb/imprimer/avis/{terrain}`, `tnb/imprimer/avis?ids=…` (avis en arabe, une page par terrain, tableau des années).

## Règles de calcul (identiques à Flask)

- Années dues : de la 1re année imposable (ou année d'acquisition, minimum 2020) à l'année en cours, sans les années payées ni exonérées.
- Exonération : permis de construire ➜ année d'autorisation à +2 (3 ans) ; le terrain passe « en construction ». Permis d'habiter ➜ « construit ».
- Tarif de l'année : zone + surface, sinon surface seule. Principal = valeur (unité DH) ou superficie × valeur, au centime supérieur.
- Échéance 28 février. Après : amende = max(principal × 15 %, 500 DH) + pénalité + majoration de retard (paramètres TNB).
- Total < 200 DH ➜ déclaration « sous seuil », sans bulletin.

**Parité** : `tools/parity/tnb_parity.py` exécute les vraies fonctions Python sur 8 terrains × 7 années × 4 dates (224 calculs) et 10 cas de pénalités ; `tests/Feature/Tnb/TnbParityTest.php` compare : tout est identique.

## Écarts volontaires avec Flask

1. **Annuler une déclaration** : Flask la supprimait ; ici elle est annulée avec motif, auteur et date (règle des pièces immuables). Les bulletins rejetés remplacés sont annulés, pas supprimés.
2. **N° de bulletin vide** : Flask ne créait pas de bulletin, la déclaration restait invisible pour le régisseur. Ici un n° est généré (`BUL…`).
3. **Tarifs** : la liste Flask n'utilisait que les tarifs actifs, la page de paiement tous les tarifs ; Laravel utilise partout la même règle (tous les tarifs de leur période), pour que la liste et l'encaissement affichent le même montant.
4. **Copropriétaires** : l'écran Flask saisissait type de titre, date d'entrée, acte et notes mais la table n'avait pas ces colonnes (l'ajout échouait). Colonnes ajoutées (migration `2026_11_22_000001`).
5. Les terrains au recensement n'apparaissent pas dans la liste des dossiers.
6. Documents stockés hors du dossier public (`storage/app/private/tnb/{terrain}`), téléchargement réservé aux utilisateurs TNB.

## Fichiers principaux

- `app/Domain/Tnb/Services/{TnbCalculator,ParcelService}.php`, `app/Domain/Tnb/Actions/DeclareTnbYears.php`, `app/Domain/Caisse/Services/Penalties.php`
- `app/Livewire/Tnb/*` (Terrains, Fiche, Encaisser, Recensement, Avis, AvisLot, TableauDeBord, Registre, Concerns/EditsParcels)
- `app/Http/Controllers/Tnb/PrintController.php`, `resources/views/livewire/tnb/*`, `resources/views/print/tnb/*`
- Tests : `tests/Feature/Tnb/{TnbParityTest,TnbDeclarationTest,TnbScreensTest}.php`

## Commandes

```powershell
$env:PHPRC='C:\wamp64\SGFC\tools\php'
php artisan migrate
php artisan test tests/Feature/Tnb
php artisan test
```

Résultat : 124 tests, tous verts (dont 18 TNB).
