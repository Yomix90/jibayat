# Phase 6 — Finitions et pages restantes

Statut : **terminé, en attente de validation**. Suite complète : **205 tests verts**.

## 1. Pages qui étaient encore vides (modules Flask non encore portés)

| Page | Route | Contenu | Parité Flask |
|---|---|---|---|
| Contribuables | `caisse.contribuables` | Répertoire, recherche (8 champs), filtre par taxe ou « sans dossier », impayés, export Excel, impression | `ctb_parity.py` ✔ |
| Fiche contribuable | `caisse.fiche-contribuable` | Identité FR/AR, taxes et dossiers, impayés par taxe, déclarations, bulletins, documents, modification, avis global (PDF arabe) | ✔ |
| Nouveau contribuable | `caisse.nouveau-contribuable` | Personne physique ou morale ; un CIN déjà connu est signalé au lieu de créer un doublon | — |
| Avis de non-paiement | `caisse.avis` | Impayés par taxe, avis par lot ou un par un, lettre à partir des avis cochés, statistiques, export Excel | `avis_parity.py` ✔ |
| Lettres de relance | `caisse.lettres` | Création, impression (une page par redevable), « envoyée », annulation motivée | ✔ |
| Importation Excel | `parametres.importation` | Modèles téléchargeables (listes déroulantes, guide, tarifs de l'arrêté), aperçu contrôlé, import | `imp_parity.py` ✔ |
| Journal des imports | `parametres.imports` | Historique, erreurs par ligne | — |
| Sauvegardes | `parametres.sauvegardes` | Sauvegarde immédiate, quotidienne, conservation, copie vers un dossier externe, téléchargement | — |
| Guide d'utilisation | `parametres.guide` | Démarches pas à pas et plan des pages accessibles | — |
| À propos | `parametres.a-propos` | Version, « Quoi de neuf », vérification des mises à jour | — |
| Signaler un problème | `parametres.signaler` | Signalement ou suggestion ; l'administrateur est prévenu et répond dans l'application | — |

## 2. Finitions

- **Recherche globale** (barre du haut) : pages, et désormais les données. On peut chercher un contribuable (nom, CIN, ICE, téléphone), un dossier de chaque taxe (n°, immatriculation, titre foncier…), une déclaration, un bulletin ou une quittance. Seules les taxes que l'utilisateur peut consulter apparaissent.
- **Notifications** : une cloche dans la barre du haut. Les alertes sont envoyées chaque jour à 7 h, une seule fois par jour, et seulement aux comptes concernés :
  - bulletins en attente depuis plus de 2 jours ;
  - véhicules en fourrière depuis plus d'un an ;
  - autorisations ODP et baux qui expirent dans les 30 jours ;
  - aucune sauvegarde récente.
- **Rapports par e-mail** (`parametres.rapports-email`) : encaissements par taxe, émis, bulletins en attente et reste à recouvrer. Fréquence au choix par compte (quotidien, hebdomadaire, mensuel), avec un envoi d'essai. Les alertes peuvent aussi partir par e-mail.
- **Empreinte (WebAuthn)** : enregistrement dans « Mon profil », puis connexion par le bouton « Se connecter avec l'empreinte », sans saisir d'identifiant. L'en-tête affiche « Empreinte activée · fermeture dans mm:ss ». Le script est servi en local, sans CDN.
- **Performances** : les requêtes en cascade (N+1) sont détectées pendant les tests et notées dans le journal en développement. `php artisan optimize` a été vérifié, et les index des tables principales ont été contrôlés.
- **Déploiement** : `docs/DEPLOIEMENT.md` (Ubuntu 24.04, Nginx, PHP 8.4-FPM, MySQL 8, HTTPS, cron, Supervisor facultatif, reprise des données, mise à jour, vérifications).
- Tâches planifiées (`routes/console.php`) :
  - sauvegarde à 02:00 (heure réglable) ;
  - alertes à 07:00 ;
  - rapports à 07:15 (quotidien), le lundi (hebdomadaire) et le 1er du mois (mensuel).

## 3. Écarts avec Flask

1. **Importation Excel** : Flask importait aussi les lignes signalées « en erreur » à l'aperçu (par exemple un type « kiosque » refusé, ou un établissement sans nom). Laravel les ignore et les note au journal.
2. **Importation des contribuables** : le type de personne, le RC et l'e-mail du fichier sont enregistrés. Flask les ignorait et devinait le type d'après le nom.
3. **Impayés (avis)** : une déclaration n'apparaît qu'une fois, avec son dernier avis. Flask pouvait la compter deux fois si elle avait plusieurs avis.
4. **Contribuables** : un contribuable « supprimé » est retiré du répertoire, mais son historique est conservé (comme Flask). Les nouveaux numéros sont de la forme `CTB{année}{n°}`, sans risque de doublon.
5. **Lettres** : l'annulation demande un motif, et la lettre reste visible avec ce motif (pièces immuables).
6. **Signaler un problème** : Flask envoyait le message vers une feuille de calcul externe. Ici, il reste dans l'application : aucune donnée ne sort du serveur.
7. **Sauvegarde et restauration** : la restauration se fait en ligne de commande (`php artisan jibayat:restore`), avec une sauvegarde de précaution automatique. Elle n'est pas proposée dans l'écran, pour éviter un écrasement par erreur.
8. **Mises à jour** : l'application vérifie s'il existe une version plus récente (adresse `JIBAYAT_UPDATE_URL`). L'installation se fait sur le serveur (guide de déploiement), pas depuis l'écran comme dans Flask.

## 4. À configurer sur le serveur

| Variable | Rôle |
|---|---|
| `MAIL_*` | envoi des alertes et des rapports |
| `WEBAUTHN_ID` | domaine utilisé par l'empreinte |
| `JIBAYAT_MYSQLDUMP` | chemin de `mysqldump` pour les sauvegardes |
| `JIBAYAT_UPDATE_URL` | vérification des mises à jour (facultatif) |

Il faut aussi la tâche cron du planificateur (voir `docs/DEPLOIEMENT.md`).

## Commandes

```powershell
. C:\wamp64\SGFC\tools\env.ps1
php artisan test --parallel
php artisan jibayat:backup
php artisan jibayat:alerts
php artisan jibayat:alerts --reports=hebdomadaire
php artisan schedule:list
```
