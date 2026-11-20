# Régie des recettes (demande du 20/11/2026)

youssef a envoyé la liste des fonctions de la régie (saisie, registres et modèles, bordereaux, PV, contrôle et clôture, documents imprimés, stock de tickets et vignettes, décharges, rapports). Ce qui existait déjà a été amélioré ; le reste est réuni dans un nouvel espace du menu, **« Régie des recettes »** (groupe « Régie & documents », adresse `/regie`).

## 1. Correspondance avec la liste

| Fonction demandée | Dans Jibayat | Nouveau / amélioré |
|---|---|---|
| Saisie des recettes (saisie.php) | Régie ➜ Saisie des recettes : une ligne par recette (rubrique, mode, quantité × prix ou montant, référence, partie versante) | Nouveau |
| Saisie livre (saisie_livre.php) | Les recettes validées et les bulletins quittancés des modules alimentent le livre de caisse automatiquement ; le solde de départ est saisi dans « Paramètres de la régie » (livre de caisse) | Nouveau |
| Brouillons, validation | Régie ➜ Brouillons & validation : modifier, valider une à une, la sélection ou tout | Nouveau |
| Rubriques | Paramètres ➜ Rubriques : ajout de la famille (récap comptable), « saisie à la régie » et « taxe spéciale » | Amélioré |
| Livre de caisse, Modèle 6 | Régie ➜ Livre de caisse : report du mois précédent, espèces, chèques et virements, versements, solde par jour | Nouveau |
| Modèle 7 | Régie ➜ Opérations Trésor : par mois, recettes, versements, accusés, soldes | Nouveau |
| Modèle 10 | Régie ➜ État mensuel : par rubrique, report, mois, cumul ; figé à la clôture ; **virements du Ministère exclus** | Nouveau |
| Situation journalière | Régie ➜ Situation journalière (rubrique × mode, versements, soldes) | Nouveau |
| Bordereau d'émission | États & documents ➜ Bordereaux d'émission | Existant |
| Bordereau de versement | Régie ➜ Bordereaux de versement au comptable (période, détail par rubrique, contrôle du solde, accusé) | Nouveau (le versement des carnets au percepteur reste dans États & documents) |
| Envoi au Trésor | Régie ➜ Envois au Trésor : choix des pièces non encore envoyées, accusé de réception du receveur | Nouveau |
| Bordereau taxe abattage | Régie ➜ Taxe spéciale : recettes du mois de la rubrique cochée « taxe spéciale » | Nouveau (hypothèse, voir §4) |
| Autorisation virement Ministère | Régie ➜ Autorisations de virement : une autorisation imprimable par recette « virement Ministère » | Nouveau (hypothèse, voir §4) |
| PV d'arrêté, vérification, récolement, remise de service | Régie ➜ Procès-verbaux : solde théorique du livre de caisse, billetage, écart ; stock compté pour le récolement et la remise ; modèles vierges | Nouveau |
| Clôture mensuelle | Régie ➜ Clôture mensuelle : verrou du mois, points bloquants, réouverture motivée (administration) | Nouveau |
| Centre de contrôle | Régie ➜ Centre de contrôle (et tableau de bord de la régie) | Nouveau |
| Rapprochement | Régie ➜ Contrôle croisé : modules ✕ saisies par jour, doublons, références manquantes, versements | Nouveau |
| État de rapprochement régie / comptable | Régie ➜ État de rapprochement : Modèle 10 ✕ relevé TGR importé, par code budgétaire | Nouveau |
| État des pièces annulées | États & documents ➜ Pièces annulées : reprend aussi les recettes, bordereaux, envois, PV et décharges annulés (filtre « Régie des recettes ») | Amélioré |
| Récap comptable | Régie ➜ Récapitulatif comptable : famille, rubrique, mode | Nouveau |
| Registre des documents avec QR | **Toute impression PDF** reçoit un n° « DOC… », un code de contrôle et un QR en pied de page ; Régie ➜ Registre des documents | Nouveau |
| Contrôle du registre, scanner, boîtes d'archive | Régie ➜ Contrôle du registre, Scanner (une feuille fait-elle foi ? + scan joint), Boîtes d'archive (étiquette imprimable) | Nouveau |
| Stock GIR, config stock TGR | Régie ➜ Stock GIR (réserve TGR, régie, chez les agents, consommé) et Configuration du stock (dotation TGR, rubrique, seuil) | Nouveau, branché sur les paquets et carnets existants |
| Stock tickets, saisie tickets | Régie ➜ Décharges de tickets : bon de décharge, retour (rendus ➜ vendus ➜ recette), modification motivée | Nouveau |
| État de stock, intelligence stock | Régie ➜ État de stock (PDF) et Intelligence du stock (consommation, couverture, retrait conseillé, Excel) | Nouveau |
| Décharges vignettes 2 DH | Régie ➜ Décharges de vignettes : bon de décharge par fonctionnaire et par jour à partir des carnets affectés | Amélioré (l'affectation reste dans Carnets) |
| Rapports, tableau, rapport annuel | Régie ➜ Rapports détaillés, Tableau des recettes (Excel), Rapport annuel (N-1, budget) | Nouveau |

## 2. Règles retenues

- **Recettes de la régie** = bulletins quittancés des modules (date de la quittance) + recettes saisies et validées à la régie. Une recette brouillon n'entre nulle part.
- **Virements du Ministère** (mode « Virement Ministère », référence obligatoire) : hors caisse, donc absents du livre de caisse et du Modèle 10 ; présents dans le récapitulatif comptable et les rapports.
- **Clôture** : un mois clôturé interdit toute saisie, validation, annulation, quittance de bulletin ou versement datés de ce mois. On ne clôture pas un mois tant que le précédent est ouvert ou qu'il reste des brouillons. L'état mensuel est figé dans la clôture.
- **Versement au comptable** : il ne peut pas dépasser le solde de caisse à sa date.
- **Pièces officielles** (recettes validées, bordereaux, envois, PV, décharges, mouvements de stock) : annulation motivée seulement ; elles apparaissent dans « Pièces annulées ».
- **Stock** (en unités) : réserve TGR = dotations − reçus par la régie (paquets et retraits) ; régie = reçus − carnets remis − tickets sortis ; une décharge soldée enregistre la recette des tickets vendus (rubrique liée à la valeur).
- **Droits** : nouvelles permissions `recettes.*` et `archives.*`, accordées au régisseur et au suppléant. Valider, clôturer et établir un PV demandent « Valider les paiements » ; rouvrir un mois demande la configuration.

## 3. Également livré ce jour

La majoration de 15 % (minimum 500 DH) pour défaut de déclaration est appliquée : voir `docs/DECLARATIONS-ASSIETTE.md`.

## 4. Points à confirmer par youssef

1. **Taxe d'abattage** : il n'y a pas de module abattoir. Le bordereau est fait à partir des recettes saisies sur la rubrique cochée « taxe spéciale ». Faut-il un calcul particulier (par tête, par kg, part reversée) ?
2. **Virement Ministère** : interprété comme une recette payée par virement d'une administration directement au compte de la commune, hors caisse. Est-ce bien cela ?
3. **Solde de départ** de la régie et nom du comptable : à saisir dans Livre de caisse ➜ Paramètres de la régie.

## Tests

- `tests/Feature/Regie/RegieTest.php` (7 tests) : saisie et validation, livre de caisse, virements exclus, annulation ; clôture et verrou (y compris quittances des modules) ; versements, accusés, envois ; PV et écarts ; stock, décharges, retour, modification ; registre des documents, QR, scanner, boîtes ; ouverture de toutes les pages, impressions et droits.
- Suite complète : **221 tests verts**.
