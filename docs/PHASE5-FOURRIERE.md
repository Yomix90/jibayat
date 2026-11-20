# Phase 5 — Module Fourrière

Statut : **terminé, en attente de validation**.

## Pages livrées

| Page | Route | Contenu |
|---|---|---|
| Dossiers | `fourriere.dossiers` | Liste, indicateurs (parc, montant dû, +265 / +365 jours, sortis), filtres, mise en fourrière, modification, impression du registre |
| Fiche dossier | `fourriere.fiche` | Véhicule, garde (jours × tarif + remorquage), propriétaire, paiements, enchères, validation de la sortie |
| Encaisser | `fourriere.encaisser` | Propriétaire (nom, CIN, téléphone), montant calculé, n° de BV |
| Sortie du véhicule | `fourriere.sortie` | Véhicules payés et quittancés, validation de la restitution |
| Véhicules aliénables | `fourriere.encheres` | Plus de 365 jours, ajout à un groupe d'enchères avec l'état du véhicule |
| Groupes d'enchères | `fourriere.groupes` | Création, prix d'ouverture, date, lieu, véhicules, impression, enregistrement des ventes (acheteur, prix, quittance, PV) |
| Ventes | `fourriere.ventes` | Adjudications et total, PV téléchargeable |
| Paramètres | `fourriere.parametres` | Déposants, motifs, états ; rappel des tarifs de l'arrêté |
| Tableau de bord, Registre | `fourriere.tableau-de-bord`, `fourriere.registre` | Écrans génériques (module `FOURRIERE`) |

## Règles (identiques à Flask)

- Tarif journalier = tarif de l'arrêté fiscal du type de véhicule, **figé à l'entrée** dans le dossier. Frais de remorquage proposés depuis le tarif « remorquage » de l'arrêté, modifiables.
- Jours de garde = aujourd'hui − date d'entrée + 1 (minimum 1). Montant dû = jours × tarif journalier + remorquage.
- Paiement : déclaration « FOU-DECL… » + bulletin en attente. À la validation par le régisseur, le dossier passe « en attente de sortie ». La sortie exige un paiement validé.
- Véhicules aliénables au-delà de 365 jours (signalés dès 265 jours). Une vente aux enchères fait sortir le véhicule ; le groupe passe « vendu » quand tous ses véhicules sont vendus.

**Parité** : `tools/parity/fou_parity.py` compare les jours de garde, les montants dus, les tarifs, les indicateurs et la vraie route Flask `fou_payer` : tout est identique.

## Écarts avec Flask

1. **Montant encaissé** : c'est le montant dû calculé. Flask laissait l'agent saisir librement un autre montant.
2. **Double encaissement bloqué** : un dossier déjà payé, sorti ou avec un bulletin en attente ne peut plus être encaissé.
3. **N° de BV** : généré si vide (Flask l'exigeait), toujours unique.
4. **Tableau de bord** : l'écran générique des modules remplace le tableau de bord spécifique de Flask (et son export Excel), qui reviendra en phase 6 si besoin.

## Fichiers

- `app/Domain/Fourriere/Services/{ImpoundCalculator,ImpoundService}.php` ; passage « en attente de sortie » ajouté dans `app/Domain/Caisse/Services/CashierService.php`
- `app/Livewire/Fourriere/*`, `resources/views/livewire/fourriere/*`, `app/Http/Controllers/Fourriere/PrintController.php`, `resources/views/print/fourriere/*`
- Tests : `tests/Feature/Fourriere/{ImpoundParityTest,ImpoundScreensTest}.php` ; 165 tests verts au total.
