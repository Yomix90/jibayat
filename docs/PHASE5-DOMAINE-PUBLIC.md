# Phase 5 — Module Domaine public (ODP)

Statut : **terminé, en attente de validation**.

## Règles (confirmées par youssef le 20/11/2026)

- Montant = **tarif de l'arrêté fiscal × superficie occupée**. Selon l'unité du tarif : DH/m²/an ➜ année = surface × tarif, trimestre = année ÷ 4 ; DH/m²/trimestre ➜ trimestre = surface × tarif ; DH/m²/mois ➜ trimestre = surface × tarif × 3 ; DH/m²/jour ➜ trimestre × 90, année × 365.
- Facturation **au trimestre ou à l'année entière**.
- **Aucune pénalité ni majoration** de retard.
- Trimestres dus depuis le trimestre/année de début (défaut : année de début de l'autorisation, sinon 2022), échéance = dernier jour du trimestre.
- Moins de 100 DH : « sous seuil », sans bulletin. Une déclaration « DCL-ODP… » par période, un bulletin en attente par période émise, même n° de BV (généré si vide).
- Le tarif d'une période est celui de l'arrêté en vigueur à son début.

**Parité** : `tools/parity/odp_parity.py` appelle la vraie route Flask `odp_declarer` (trimestres et année entière) et compare aussi les bases de calcul pour 6 unités × 4 surfaces et les montants de la liste : tout est identique.

## Pages livrées

Occupations (liste, indicateurs dont autorisations expirées, ajout, modification, archivage, PDF) · Fiche (occupant, autorisation, redevance, situation FR/AR, liquidations, renouvellement +3 mois / +6 mois / +1 an) · Encaisser (trimestres ou année entière) · Renouvellement (autorisations expirées ou bientôt échues) · Avis de non-paiement (PDF arabe avec le détail par trimestre) · Tableau de bord · Registre.

## Écarts avec Flask

1. **Bug de tarif corrigé** : Flask ne reconnaissait que le tarif le moins cher (la recherche s'arrêtait au premier). Pour tout autre type (Terrasse, Kiosque…), la liste affichait 0 et la liquidation plantait. Tous les tarifs sont maintenant reconnus.
2. **Année entière** : proposée seulement si aucun trimestre de l'année n'est déjà payé ou émis. Flask la proposait toujours, ce qui permettait de payer deux fois.
3. **Période déjà payée ou émise** : refusée à la liquidation (Flask ne vérifiait pas).
4. **Archivage** refusé s'il existe une période payée (passer l'occupation en « Expiré »).
5. Comme dans Flask, les trimestres restent dus après la date de fin de l'autorisation (une autorisation expirée est signalée mais continue de compter). À confirmer.

## Fichiers

- `app/Domain/DomainePublic/Services/{OccupationCalculator,OccupationService}.php`, `app/Domain/DomainePublic/Actions/DeclareOccupationPeriods.php`
- `app/Livewire/DomainePublic/*`, `resources/views/livewire/domaine-public/*`, `app/Http/Controllers/DomainePublic/PrintController.php`, `resources/views/print/domaine-public/*`
- Tests : `tests/Feature/DomainePublic/{OccupationParityTest,OccupationScreensTest}.php` ; 158 tests verts au total.
