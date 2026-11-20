# Déclarations d'assiette, cessation, renouvellements (20/11/2026)

Les règles ont été données par youssef le 20/11/2026. La base légale est la loi n° 47-06, modifiée par la loi n° 07-20 : j'ai lu le texte consolidé publié par le SGG, mis à jour en juin 2025.

## 1. Nouveau module « Déclarations d'assiette » (الإقرارات الخاصة بالوعاء)

Le module est placé dans le menu, groupe « Régie & documents ». Il contient :

- **Nouvelle déclaration**. Le parcours se fait en quatre étapes :
  1. choix de la taxe ;
  2. choix du type de déclaration (l'article de loi et le délai sont affichés) ;
  3. choix du terrain, de l'établissement ou du véhicule ;
  4. saisie du contenu.

  Le délai légal est calculé automatiquement. Une déclaration déposée après ce délai est signalée « hors délai », avec le rappel de la majoration prévue (art. 134 et 135).
- **Registre** : filtres par taxe, type et état (hors délai, non signée, annulée). On peut y marquer une déclaration comme signée, l'imprimer, ou l'annuler avec un motif : c'est une pièce officielle, elle ne se supprime jamais.
- **Échéances annuelles** : terrains sans déclaration annuelle (avant le 1er mars) et débits de boissons sans déclaration des recettes (avant le 1er avril). Un bouton « Déclarer » prérempli figure sur chaque ligne.
- **Formulaires officiels** : un formulaire bilingue par type, vierge ou prérempli. Il comprend :
  - l'identification du redevable et de l'objet ;
  - le contenu de la déclaration ;
  - le délai et une mention « hors délai » s'il y a lieu ;
  - l'attestation sur l'honneur ;
  - la signature du redevable, ainsi que le visa et le cachet du service.

| Taxe | Déclaration | Article | Délai |
|---|---|---|---|
| TNB | Déclaration annuelle des terrains (الإقرار السنوي بالأراضي) | 47 | avant le 1er mars |
| TNB | Changement de propriétaire, d'affectation ou cession | 48 | 45 jours |
| Débits de boissons | Déclaration d'existence (التصريح بالتأسيس) | 67-I | 30 jours |
| Débits de boissons | Déclaration annuelle des recettes | 67-II | avant le 1er avril |
| Débits de boissons | **Déclaration d'arrêt d'activité (الإقرار بإيقاف النشاط)** | 68 | 45 jours |
| Débits de boissons | Déclaration de reprise d'activité | règle interne | — |
| Débits de boissons | Cession, transfert, changement de forme juridique | 68 | 45 jours |
| Débits de boissons | Déclaration d'inactivité (العطالة) | 69 | avant le 31 janvier N+1 |
| Transport public de voyageurs | Déclaration d'existence, déclaration d'arrêt | 87 | au début de l'activité / à l'arrêt |

## 2. Cessation d'un débit de boissons

La règle de la commune est la suivante :

- Le redevable signe la déclaration d'arrêt. Il y indique le **motif** (liste proposée ou texte libre) et la **date de reprise prévue**.
- La **période d'arrêt est exonérée**. Le trimestre de l'arrêt reste dû, sauf si l'arrêt commence le premier jour du trimestre. Les trimestres suivants ne sont plus dus jusqu'à la reprise. Le trimestre de la reprise est dû.
- La **déclaration de reprise** met fin à l'arrêt. Si on annule une déclaration d'arrêt ou de reprise, le statut de l'établissement est rétabli.
- La page « Cessation » affiche l'arrêt en cours : date, motif, reprise prévue (en rouge si elle est dépassée), mention « période exonérée » et « à faire signer ». Elle propose aussi les boutons « Déclarer l'arrêt » et « Déclarer la reprise ».
- Une **alerte** prévient l'agent quand la date de reprise prévue est dépassée sans déclaration de reprise.

## 3. Domaine public : autorisations à renouveler

- Les autorisations expirées, ou qui expirent dans les 30 jours, déclenchent une **alerte** pour l'agent dans la cloche et, s'il l'a demandé, par e-mail.
- Sur la page « Renouvellement », chaque autorisation affiche le téléphone du titulaire (le lien appelle directement depuis un téléphone) et l'état « À contacter » ou « Contacté le … : résultat ».
- Le bouton « Contacté » enregistre le résultat du contact. Il est inscrit au journal, et l'alerte cesse pendant 15 jours.
- Le décompte des trimestres d'une autorisation expirée n'a pas changé.

## 4. Souk : prolongation en fin de période

- Le bouton « Prolonger » sur la fiche du contrat affiche :
  - la nouvelle période ;
  - la redevance actuelle ;
  - l'augmentation proposée (le taux du contrat), modifiable ;
  - la nouvelle redevance, recalculée et modifiable ;
  - une observation (accord du locataire, n° de décision…).
- La prolongation est enregistrée (table `souk_contract_renewals`). La redevance choisie s'applique à toute la nouvelle période, et les mois impayés antérieurs restent dus.
- Une **alerte** signale les contrats qui arrivent en fin de période dans les 60 jours.

## Majoration pour défaut ou retard de déclaration (règle validée par youssef le 20/11/2026)

- **TNB** : majoration de 15 % du droit dû, minimum 500 DH, sur l'année N si la déclaration annuelle (art. 47) n'a pas été déposée avant le 1er mars N. Une déclaration déposée à temps supprime la majoration ; les pénalités de retard de paiement restent dues.
- **Débits de boissons** : majoration de 15 %, minimum 500 DH, portée sur le T4 de l'année N quand il est payé à partir du 1er avril N+1 sans déclaration des recettes (art. 67-II) déposée avant le 1er avril.
- Le taux est le paramètre `AMENDE_NON_DECLARATION` (Paramètres ➜ Paramètres de calcul), 15 % par défaut.

## Tests

- `tests/Feature/Declarations/DeclarationsTest.php` : délais légaux, exonération de la période d'arrêt, reprise, annulation, registre, échéances, permissions, alertes et contact pour le domaine public.
- `tests/Feature/Souk/SoukScreensTest.php` : prolongation avec augmentation modifiable.
- Test « surcharge of 15 % » : majoration avec ou sans déclaration dans le délai, pour la TNB et les débits de boissons.
- Suite complète : **213 tests verts**.
