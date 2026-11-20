# Phase 5 — Module Locations (+ caisse du régisseur) : résumé

> Livré le 19/11/2026. En attente de validation avant le module suivant (TNB).

## Parité avec Flask (règle n° 2 du CLAUDE.md)

`tools/parity/locations_parity.py` exécute **les vraies fonctions Python** de Flask
(`modules/location.py` : `_calculer_loyer`, `_calculer_mois_non_payes`, `_grouper_par_tarif`) sur
6 baux d'essai, à une date figée, sans modifier le projet Flask. Il produit
`tests/Fixtures/parity/locations.{db,json}`. Le test `LeaseParityTest` importe la même base avec
`jibayat:import-sqlite` et compare tout : loyer mensuel, liste des mois impayés, tarif de chaque
mois, source du tarif (arrêté, tarif du local, loyer du bail), mois « en attente », totaux, groupes.
**Résultat : identique sur les 6 baux.**

Cas couverts : tarif fixe avec historique d'arrêtés (300 ➜ 350 DH), tarif au m² (20 ➜ 22,5 DH/m²),
tarif de local non synchronisé, secteur au m², loyer saisi sur le bail, date de fin de bail, bail pas
encore commencé, mois payés, mois émis en attente, déclaration annulée.

Les arrondis reproduisent exactement Python (`App\Support\PyMath` : `round(2.675, 2) = 2.67`, et la
« majoration au centime supérieur » de Flask), utilisables par les modules suivants.

## Écart volontaire avec Flask (à valider)

Flask **affiche** le total des mois choisis avec le tarif de chaque mois, mais **enregistre**
« loyer actuel × nombre de mois » (anomalie n° 7 du plan). Laravel enregistre **la somme des
tarifs de chaque mois**, c'est-à-dire ce que l'agent voit à l'écran. Les deux sont identiques tant
que le tarif n'a pas changé sur la période. Dites-moi si vous préférez reproduire Flask à l'identique.

## Écrans

| Écran | Contenu |
|---|---|
| Locataires | indicateurs (baux, avec impayés, en attente, à jour, boutiques libres), recherche, filtres secteur / situation, situation de chaque bail (période, à émettre, en attente), création / modification (avec ajout rapide d'un contribuable et choix d'une boutique libre), suppression (archivage s'il y a des pièces) |
| Fiche locataire | identité FR/AR, local et tarif, loyer actuel, situation, historique du tarif dans les arrêtés, déclarations et quittances |
| Encaisser | mois impayés avec le tarif de chaque mois, choix des « N premiers mois » ou mois à mois, regroupement par tarif, n° de BV (automatique si vide), mode, date ; historique |
| Secteurs & tarifs | secteurs ➜ groupes de tarif (fixe ou au m²) ➜ boutiques ; chaque tarif est recopié dans l'arrêté fiscal actif (code « secteur-T{n°} ») |
| Boutiques | par statut (disponibles, exploitées, fermées), occupant |
| Synchronisation | état de chaque tarif par rapport à l'arrêté, synchronisation unitaire ou globale |
| Tableau de bord | indicateurs, courbe mensuelle, statuts, historique par année, dernières déclarations (écran réutilisable par chaque module) |
| Registre des loyers | déclarations, BV et quittances, filtres année / statut (écran réutilisable) |
| Avis de non-paiement | baux en retard, impression de l'avis |
| **Caisse** (espace Caisse & recouvrement) | bulletins en attente groupés par n° de BV : solder & quittancer, validation groupée, rejet motivé, correction et réémission, annulation motivée ; bulletins rejetés ; encaissements |

## Règles de la caisse (Flask `bulletins.py`)

- Le n° de quittance est **saisi** par le régisseur (carnet), obligatoire ; il est refusé s'il a déjà
  été donné à un autre bulletin (contrôle verrouillé en base).
- Valider un bulletin solde aussi les autres bulletins « en attente » portant le même n° de BV.
- Rejeter remet la déclaration « émise » ; corriger renvoie le bulletin en attente.
- **Rien ne se supprime** : un bulletin s'annule avec un motif ; on peut annuler aussi la déclaration,
  ce qui rend les mois de nouveau encaissables. Tout est journalisé.
- Numérotation automatique (BV, déclarations) `{préfixe}{année}{00001}` verrouillée (`lockForUpdate`).

## Impressions (PDF A4, mPDF, arabe lisible)

- Registre des baux (A4 paysage, bilingue, mêmes filtres que la liste, totaux, signatures).
- Avis de non-paiement (mise en demeure en arabe, reprise du texte Flask, détail par tarif, total).

## Tests

`php artisan test` : **106 tests, 1 306 assertions, tous verts** (dont 4 de parité Flask, 7 sur
l'encaissement et la caisse, 6 sur les écrans, permissions et PDF).
