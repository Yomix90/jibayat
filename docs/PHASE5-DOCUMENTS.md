# Phase 5 — États & documents

Statut : **terminé, en attente de validation**. Dernier module de la phase 5.

## Pages livrées

| Page | Route | Contenu |
|---|---|---|
| Registres | `documents.registres` | Synthèse par taxe : déclarations, émis, pénalités, quittances, recouvré, reste, taux |
| Grand livre des recettes | `documents.grand-livre` | Toutes les recettes, filtres par année, taxe et statut, export CSV, impression |
| Registre des déclarations | `documents.declarations` | Base, taux, principal, pénalités, majorations, amendes, export CSV |
| Pièces annulées | `documents.pieces-annulees` | Pièces annulées avec motif, auteur et date, impression |
| Timbres & tickets | `documents.timbres` | Valeurs, stock, indicateurs de la régie des timbres |
| Paquets | `documents.paquets` | Réception, ouverture en carnets |
| Carnets | `documents.carnets` | Affectation aux services et employés |
| Versements & bordereaux | `documents.versements` | Versement des employés (écart de caisse), bordereau au percepteur, impression |
| Relevés GIR | `documents.releves-gir` | Lecture du relevé « Activité mensuelle » (Excel GIR), filtres, totaux, montant en lettres, export Excel, impression |
| Bordereaux d'émission | `documents.emission` | Tableau des 12 mois, import du bordereau GIR, détail du mois (modifier, retirer, ajouter une rubrique), 1ère et 2ème partie, recalcul, impressions par bordereau et par mois |
| Gestion du budget | `documents.budget` | Nomenclature officielle (48 chapitres, 5 domaines), saisie N-1, proposé et réalisé, taux et écarts, synchronisation avec les encaissements, import Excel, ajout et retrait de chapitre, affichage FR ou AR, impression et export Excel |

## Règles (identiques à Flask)

- **Bordereaux d'émission** : un bordereau par ligne du relevé GIR, numérotés à la suite dans l'année. Report des antérieurs par rubrique, égal à la somme des mois précédents. 1ère partie : cumul des mois antérieurs (auto) ou montant saisi. 2ème partie : total du mois (auto), montant saisi ou vide. Toute modification recalcule toute l'année.
- **Relevé GIR** : mêmes colonnes, mêmes totaux et même montant en toutes lettres que Flask, y compris ses accords (« quatre-vingts », « deux cent »).
- **Budget** : taux = réalisé ÷ proposé (100 % si rien n'est proposé mais qu'il y a du réalisé). La synchronisation remplace le réalisé des chapitres liés à un module (TNB, loyers, souk, boissons, ODP, stationnement, fourrière, ventes aux enchères) par les bulletins payés de l'année. L'import normalise les codes de chapitre et applique les corrections officielles. Un réalisé vide à l'import conserve le montant existant. Les montants de reprise ne sont pré-remplis que pour l'exercice 2022.

**Parité** : les scripts `tools/parity/reg_parity.py`, `rg_parity.py`, `tresor_parity.py` et `budget_parity.py` exécutent les vraies fonctions Flask. Tout est identique : registres, régie des timbres, 4 opérations TGR, 16 montants en lettres, 3 bordereaux GIR, 8 bordereaux d'émission, et le budget à 5 étapes (création, synchronisation, import, ajout, 2022).

## Écarts avec Flask

1. **Relevé TGR** : Flask plantait sur tous les fichiers (erreur de découpage de ligne, « Erreur lors du traitement du fichier »). Laravel applique la logique que le code voulait. Le libellé « Régie » est aussi lu correctement (Flask donnait « None »).
2. **Bordereau GIR en .xls** : un code budgétaire lu comme nombre (« 1140102026.0 ») est reconnu. Flask le rangeait en « Recettes imprévues ».
3. **Paquets de timbres** de moins de 10 unités : un carnet par timbre. Flask produisait des carnets invalides.
4. **Impressions des bordereaux d'émission** : un PDF unique pour le mois (une page par bordereau), au lieu d'un ZIP de PDF.
5. **Dernier relevé TGR** : conservé par utilisateur (Flask : fichier temporaire de session).

## Fichiers principaux

- Services : `app/Domain/Documents/Services/{RegisterService, StampService, SpreadsheetReader, TgrStatementParser, AmountInWords, GirStatementParser, IssueSlipService, BudgetService}.php`, `app/Domain/Documents/Support/BudgetNomenclature.php`
- Écrans : `app/Livewire/Documents/*`, vues `resources/views/livewire/documents/*`
- Impressions et exports : `app/Http/Controllers/Documents/{RegisterController, TresorController, BudgetController}.php`, `resources/views/print/documents/*`
- Tests : `tests/Feature/Documents/*` (18 tests)

## Commandes

```powershell
. C:\wamp64\SGFC\tools\env.ps1
php artisan test tests/Feature/Documents
php artisan test --parallel
```

Suite complète : **183 tests verts**.
