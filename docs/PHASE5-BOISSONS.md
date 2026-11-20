# Phase 5 — Module Débits de boissons (TDB)

Statut : **terminé**. Corrections demandées le 19/11/2026 appliquées (T4, taux de l'arrêté fiscal). Reste à trancher : le décompte après cessation.

## Pages livrées

| Page | Route | Contenu |
|---|---|---|
| Établissements | `boissons.etablissements` | Liste, 4 indicateurs, filtres statut / situation / recherche, ajout (avec nouveau contribuable), modification, suppression (refusée si un trimestre est payé), impression PDF A4 paysage |
| Fiche établissement | `boissons.fiche` | Exploitant, établissement, taux, situation FR/AR, déclarations, cessation / reprise, liens vers les impressions |
| Encaisser | `boissons.encaisser` | Trimestres échus par année, CA saisi par trimestre (saisir un CA coche le trimestre), calcul en direct (principal, retard, amende), date de déclaration, n° de BV commun |
| Déclaration annuelle | `boissons.declaration-annuelle` | Récapitulatif T1–T4 d'une année (N-1 par défaut), impression |
| Cessation | `boissons.cessation` | Établissements en activité / en cessation, changement de statut, formulaire de cessation |
| Formulaires | `boissons.declarations` | Formulaires de création, de CA (modèle 6) et de cessation, vierges ou pré-remplis |
| Avis de non-paiement | `boissons.avis` | Établissements avec trimestres non déclarés, mise en demeure en arabe (PDF) |
| Tableau de bord, Registre | `boissons.tableau-de-bord`, `boissons.registre` | Écrans génériques (module `DEBITS_BOISSONS`) |

## Règles de calcul (identiques à Flask)

- Trimestres dus : du trimestre/année de début d'imposition (défaut T1 2022) jusqu'aux trimestres dont l'échéance est passée, sans les trimestres déjà déclarés (payés ou émis).
- Échéance légale : dernier jour du mois qui suit le trimestre (30/04, 31/07, 31/10, 31/01 N+1).
- Taxe = CA × taux (arrondi Python). Déclaré après l'échéance : pénalité 10 % + majoration 5 % puis 0,5 % par mois.
- T4 déclaré à partir du 1er avril N+1 : amende de non-déclaration max(10 %, 500 DH).
- Total < 200 DH : « sous seuil », sans bulletin.
- Liste : estimation = dernier CA déclaré × taux × nombre de trimestres.

**Parité** : `tools/parity/tdb_parity.py` appelle la **vraie route Flask `tdb_declarer`** sur 7 scénarios (15 trimestres), ainsi que les fonctions des trimestres dus, des taux et des estimations de la liste. `tests/Feature/Boissons/BeverageParityTest.php` compare : tout est identique.

## Écarts avec Flask

1. **Échéance du T4 (confirmé)** : échéance au 31 janvier N+1, pénalités à partir de février N+1 (Flask prenait le 31 janvier de la même année). Le réglage `JIBAYAT_TDB_T4_NEXT_YEAR=false` ne sert qu'au test de parité.
2. **Taux = arrêté fiscal (confirmé)** : la catégorie (tarif) est choisie parmi les tarifs de l'arrêté actif et liée à l'établissement (migration `2026_11_22_000002`). À chaque déclaration, le taux est celui du tarif de même libellé **en vigueur au 1er jour du trimestre** : un nouvel arrêté s'applique aux trimestres qui suivent, les anciens trimestres gardent l'ancien taux. Flask prenait en réalité le taux le plus bas pour tous (tarifs sans code). Sans tarif lié (données importées), la règle de Flask s'applique.
3. **N° de BV vide** : un numéro est généré, comme validé pour la TNB.
4. **Cessation** : comme dans Flask, un établissement en cessation continue d'accumuler des trimestres dus. À confirmer : faut-il arrêter le décompte à la date de cessation ?

## Fichiers

- `app/Domain/Boissons/Services/BeverageCalculator.php`, `app/Domain/Boissons/Actions/DeclareBeverageQuarters.php`
- `app/Livewire/Boissons/*`, `resources/views/livewire/boissons/*`, `app/Http/Controllers/Boissons/PrintController.php`, `resources/views/print/boissons/*`
- Tests : `tests/Feature/Boissons/{BeverageParityTest,BeverageScreensTest}.php` ; 132 tests verts au total.
