# Phase 5 — Module Transport (stationnement + TPV)

Statut : **terminé, en attente de validation**.

## Pages livrées

| Page | Route | Contenu |
|---|---|---|
| Véhicules | `transport.vehicules` | Liste, 4 indicateurs, recherche, filtre de situation, ajout (avec nouveau contribuable), modification, suppression (refusée si un trimestre est payé), impression PDF |
| Fiche véhicule | `transport.fiche` | Propriétaire, véhicule, montant par trimestre (stationnement + TPV), situation FR/AR, paiements |
| Encaisser | `transport.encaisser` | Trimestres échus cochés, détail stationnement / TPV par trimestre, date, n° de BV commun |
| Avis de non-paiement | `transport.avis` | Véhicules en retard, avis PDF bilingue avec le détail par trimestre |
| Tableau de bord, Registre | `transport.tableau-de-bord`, `transport.registre` | Écrans génériques (module `STATIONNEMENT`) |

## Règles (confirmées par youssef le 19/11/2026)

- Les deux taxes sont **trimestrielles** et **configurées dans l'arrêté fiscal**. La valeur du tarif est le montant d'un trimestre, quelle que soit l'unité écrite (l'arrêté actuel indique « DH/an » : à corriger en « DH/trimestre » pour éviter la confusion).
- Le type du véhicule est le libellé d'un tarif de l'arrêté. Montant d'un trimestre = stationnement + TPV du tarif de même libellé **en vigueur au 1er jour du trimestre** (un nouvel arrêté s'applique aux trimestres qu'il couvre). Sans tarif TPV pour ce type, la TPV vaut le premier tarif TPV (règle Flask) ; aujourd'hui l'arrêté n'a aucun tarif TPV, donc TPV = 0.
- **Aucune pénalité ni majoration de retard**, ni sur le stationnement ni sur la TPV. Pas d'amende.
- Trimestres dus : depuis le trimestre/année de début (défaut T1 2020), échéance = dernier jour du mois suivant le trimestre (T4 : 31/01 N+1).
- Total < 200 DH : « sous seuil », sans bulletin. Une déclaration « DCL-STA… » par trimestre, un bulletin en attente par trimestre, même n° de BV (généré si vide).

**Parité** : `tools/parity/sta_parity.py` appelle la vraie route Flask `sta_payer` sur 5 scénarios (11 trimestres) et les fonctions des tarifs, trimestres dus et montants de la liste. Tout est identique, sauf les pénalités, retirées volontairement.

## Écarts avec Flask

1. **Pénalités** : Flask ajoutait pénalité et majoration de retard sur la TPV. Retirées, sur instruction du régisseur.
2. **Avis** : Flask divisait les tarifs par 4 et ajoutait une pénalité forfaitaire de 10 % sur l'avis, ce qui ne correspondait pas à l'encaissement. L'avis reprend maintenant exactement les montants de l'encaissement, avec le détail par trimestre.
3. **Arrêté de la période** : le tarif est celui de l'arrêté en vigueur pour le trimestre (Flask prenait toujours l'arrêté actif).
4. **N° de BV vide** : numéro généré (comme pour la TNB et les débits de boissons).

## Fichiers

- `app/Domain/Transport/Services/TransportCalculator.php`, `app/Domain/Transport/Actions/PayVehicleQuarters.php`
- `app/Livewire/Transport/*`, `resources/views/livewire/transport/*`, `app/Http/Controllers/Transport/PrintController.php`, `resources/views/print/transport/*`
- Tests : `tests/Feature/Transport/{TransportParityTest,TransportScreensTest}.php` ; 141 tests verts au total.
