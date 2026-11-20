# Phase 5 — Module Souk (affermage)

Statut : **terminé, en attente de validation**.

## Pages livrées

| Page | Route | Contenu |
|---|---|---|
| Contrats d'affermage | `souk.contrats` | Liste, 4 indicateurs, filtres souk / situation / recherche, ajout (avec nouveau contribuable), modification, archivage, impression PDF |
| Fiche contrat | `souk.fiche` | Fermier, contrat, situation FR/AR, périodes et revalorisations, renouvellement, résiliation (date + motif), documents, historique |
| Encaisser | `souk.encaisser` | Mois dus affichés à la redevance de leur période ; on choisit le nombre de mois (les plus anciens d'abord) ; BV, mode, n° de versement |
| Avis de non-paiement | `souk.avis` | Contrats en retard, mise en demeure en arabe avec le détail par période (PDF) |
| Tableau de bord, Registre | `souk.tableau-de-bord`, `souk.registre` | Écrans génériques (module `AFFERMAGE_SOUKS`) |

## Règles (identiques à Flask)

- Redevance mensuelle (à défaut annuelle ÷ 12). Le contrat se renouvelle tous les « durée » ans avec une revalorisation de « taux » % (taux 0 ou vide ➜ 5 %, comme Flask). Fin de période = début + durée − 1 jour (29/02 ➜ 28/02).
- Mois dus : du mois de début au mois de min(date de fin, aujourd'hui), chacun à la redevance de sa période, sans les mois payés.
- Encaissement dans l'ordre chronologique ; montant = somme des redevances des mois ; une déclaration « SOU-DECL… » + un bulletin en attente.

**Parité** : `tools/parity/sou_parity.py` exécute les vraies fonctions Flask (périodes, mois dus, regroupement, liste) et la route `sou_payer` sur 4 scénarios : identique, sauf l'écart n° 1.

## Écarts avec Flask

1. **Premier mois d'un contrat commencé en cours de mois** : Flask le facturait au tarif de la *dernière* période (ex. 787,50 au lieu de 750). Il prend maintenant la redevance initiale.
2. **Renouveler** : Flask déplaçait la date de début du contrat, ce qui effaçait les mois impayés antérieurs. Ici la date de début est gardée et la date de fin est prolongée d'une période ; la revalorisation s'applique automatiquement.
3. **Mois déjà émis** (en attente du régisseur) : Flask les proposait encore, d'où un risque de double bulletin. Ils ne sont plus proposés.
4. **Avis** : Flask « Créer un avis » enregistrait une déclaration émise sans bulletin, qui bloquait ensuite ces mois. Ici l'avis est une pièce imprimée, inscrite au journal.
5. **N° de BV** : généré si vide (Flask l'exigeait), toujours unique.
6. **Encaissement direct** du bulletin par le régisseur depuis la fiche : il passe par la caisse du régisseur, comme les autres modules.
7. **Suppression** : refusée si une redevance a été payée (il faut résilier).

## Fichiers

- `app/Domain/Souk/Services/{SoukCalculator,ContractService}.php`, `app/Domain/Souk/Actions/CollectSoukMonths.php`
- `app/Livewire/Souk/*`, `resources/views/livewire/souk/*`, `app/Http/Controllers/Souk/PrintController.php`, `resources/views/print/souk/*`
- Tests : `tests/Feature/Souk/{SoukParityTest,SoukScreensTest}.php` ; 150 tests verts au total.
