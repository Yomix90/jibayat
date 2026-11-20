# Phase 2 — Socle Laravel : résumé

> Livré le 19/11/2026. En attente de la validation visuelle de youssef (mobile et bureau)
> avant la Phase 3 (base de données & import).

## Ce qui est en place

| Élément | Détail |
|---|---|
| Projet | `jibayat-laravel/` — Laravel 13.43, PHP 8.4 (Herd), MySQL 8.4 (`utf8mb4_unicode_ci`) |
| Front | Blade + Livewire 4 (dernière version stable ; le CLAUDE.md citait la v3) + Alpine (fourni par Livewire) + Tailwind 3 (Vite) |
| Auth | Breeze (Blade) adapté : connexion par **identifiant** (nom d'utilisateur comme dans Flask, ou e-mail), pas d'inscription libre, compte désactivé = déconnecté |
| Déconnexion auto | 10 min sans activité (`JIBAYAT_AUTO_LOGOUT_MINUTES`), contrôlée côté serveur (middleware `AutoLogout`) + compte à rebours dans l'en-tête |
| Rôles | spatie/laravel-permission : Administrateur, Régisseur, Régisseur suppléant (= droits du régisseur), Collecteur, Consultation/contrôle. Permissions `{module}.{voir,ajouter,modifier,supprimer,exporter}` + `bulletins.creer`, `paiements.valider`, `config.gerer`, `dashboard.voir` |
| Journal | spatie/laravel-activitylog : connexion, déconnexion, déconnexion pour inactivité |
| Navigation | `config/navigation.php` : **10 espaces, 95 pages**, chaque page = une route `/{espace}/{page}` protégée par sa permission, avec l'écran Flask de référence |
| Design | palette du CLAUDE.md §4.1, polices Inter + Noto Kufi Arabic **servies en local**, motif zellige SVG original (`resources/svg/zellige.svg`), emblème octogramme, icônes Lucide inline |
| Composants | `x-user-header`, `x-page-search` (Livewire), `x-recent-chips`, `x-space-carousel`, `x-section-title`, `x-tile`, `x-footer-card`, + `x-emblem`, `x-svg-icon`, `x-desktop-nav` |
| Écrans | Connexion, Accueil (en-tête, recherche, récents, espaces, tuiles de l'espace choisi, pied), page d'espace, page vide pour chaque entrée, profil |
| Tests | Pest sur MySQL (`jibayat_test`) : **43 tests, 615 assertions, tous verts** |

## Espaces (couleurs)

Caisse & recouvrement (navy/or) · TNB (bronze) · Transport (bleu ardoise) · Locations (orange brûlé) ·
Souk (jaune doré) · Débits de boissons (bordeaux) · Domaine public (vert) · Fourrière (anthracite) ·
États & documents (olive/or) · Paramètres (bleu acier).

## Comptes de démonstration

Créés par `php artisan db:seed` : `admin`, `regisseur`, `suppleant`, `collecteur`, `controle`
(un par rôle). Le mot de passe commun est la valeur `JIBAYAT_SEED_PASSWORD` du fichier
`jibayat-laravel/.env` (générée aléatoirement, jamais versionnée).

## Lancer l'application

```powershell
powershell -ExecutionPolicy Bypass -File C:\wamp64\SGFC\tools\mysql-start.ps1
powershell -ExecutionPolicy Bypass -File C:\wamp64\SGFC\tools\serve.ps1
```
Puis ouvrir http://127.0.0.1:8000.

Autres commandes (dans `jibayat-laravel`, après `. C:\wamp64\SGFC\tools\env.ps1`) :
```powershell
php artisan migrate:fresh --seed
php artisan test
npm run build
```

Pourquoi ces scripts : le PHP de Herd n'a aucune extension active dans son `php.ini`.
`tools/php/php.ini` les active pour ce projet seulement (via `PHPRC`), sans modifier Herd.
Le MySQL de WAMP ne démarrait plus (WAMP déplacé dans `C:\wamp64\old`) : un MySQL de dev
séparé tourne sur le port 3307 avec ses propres données (`tools/mysql/data`).

## Fichiers principaux

- `config/navigation.php`, `config/jibayat.php`
- `app/Support/Navigation.php` (registre, filtrage par permission, recherche FR/AR sans accents)
- `app/Http/Controllers/{Home,Space,Page,SessionStatus}Controller.php`
- `app/Http/Middleware/{AutoLogout,EnsureUserIsActive,TrackRecentPage}.php`
- `app/Livewire/PageSearch.php`, `app/Models/{User,RecentPage}.php`
- `database/seeders/{RolesAndPermissionsSeeder,DatabaseSeeder}.php`
- `resources/views/components/*`, `resources/views/{home,spaces/show,pages/placeholder}.blade.php`
- `resources/css/app.css`, `resources/js/session-timer.js`, `resources/svg/zellige.svg`
- `tests/Feature/{NavigationTest,SearchAndRecentsTest,AutoLogoutTest}.php`

## Reste à faire avant / pendant la Phase 3

- **Export de la base Flask** : lancer `tools/export_sqlite_plain.py` (voir la commande dans le
  fil). Je n'ai pas l'autorisation de le lancer moi-même : il lit la clé DPAPI de la session Windows.
- Bouton empreinte : visuel seulement (WebAuthn en Phase 6).
- Arabe/RTL : la structure est prête (`dir`, polices, `lang/ar`) ; la bascule de langue viendra avec les écrans métier.
