# Déploiement de Jibayat sur un VPS

Ce guide installe Jibayat (Laravel 13, PHP 8.4, MySQL 8) sur un serveur **Ubuntu 24.04**. Il utilise Nginx et PHP-FPM, avec une tâche cron pour le planificateur. Supervisor est facultatif. Comptez environ une heure.

> **HTTPS est obligatoire.** La connexion par empreinte (WebAuthn) ne fonctionne qu'en HTTPS, ou sur `localhost`. Il faut donc un nom de domaine (par exemple `jibayat.commune.ma`) qui pointe vers le serveur.

## 1. Paquets système

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server git unzip curl supervisor certbot python3-certbot-nginx
sudo add-apt-repository -y ppa:ondrej/php && sudo apt update
sudo apt install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip \
                    php8.4-gd php8.4-intl php8.4-bcmath php8.4-opcache
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install -y nodejs
```

`mysqldump` est installé avec `mysql-server` : il sert aux sauvegardes.

## 2. Base de données

```bash
sudo mysql
```

```sql
CREATE DATABASE jibayat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'jibayat'@'localhost' IDENTIFIED BY 'UN_MOT_DE_PASSE_FORT';
GRANT ALL PRIVILEGES ON jibayat.* TO 'jibayat'@'localhost';
FLUSH PRIVILEGES;
```

## 3. Code de l'application

```bash
sudo mkdir -p /var/www/jibayat && sudo chown $USER:www-data /var/www/jibayat
git clone <adresse-du-depot> /var/www/jibayat     # ou copier le dossier jibayat-laravel
cd /var/www/jibayat
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env && php artisan key:generate
```

Dans `.env`, renseignez au minimum :

| Variable | Valeur |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | `https://jibayat.commune.ma` |
| `APP_TIMEZONE` | `Africa/Casablanca` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | ceux de l'étape 2 (port 3306) |
| `JIBAYAT_SEED_PASSWORD` | mot de passe initial des comptes (à changer à la première connexion) |
| `JIBAYAT_MYSQLDUMP` | `mysqldump` |
| `JIBAYAT_AUTO_LOGOUT_MINUTES` | `10` |
| `WEBAUTHN_ID` | `jibayat.commune.ma` |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | le serveur SMTP de la commune (alertes et rapports par e-mail) |
| `SESSION_SECURE_COOKIE` | `true` |

Ne copiez jamais `.env` dans le dépôt. Il contient tous les secrets.

```bash
php artisan migrate --force --seed
php artisan storage:link
sudo chown -R $USER:www-data storage bootstrap/cache && sudo chmod -R ug+rwX storage bootstrap/cache
php artisan optimize
```

## 4. Reprise des données Flask

1. Sur le poste de la régie, exportez la base Flask en clair avec `tools/export_sqlite_plain.py`. On obtient `data/jibayat_plain.db`.
2. Copiez le fichier sur le serveur, puis lancez d'abord une simulation, puis l'import :

```bash
php artisan jibayat:import-sqlite /chemin/jibayat_plain.db --dry-run
php artisan jibayat:import-sqlite /chemin/jibayat_plain.db
```

3. Contrôlez le rapport : lignes source, lignes importées, écarts.
4. **Supprimez ensuite le fichier `jibayat_plain.db`, sur le serveur comme sur le poste.** Il contient les données en clair.
5. Dans « Paramètres ➜ Arrêtés fiscaux », reportez les réglages validés pendant la migration : stationnement en DH/trimestre, et tarifs TPV (taxis 100, autocar/minibus 400 DH/trimestre).

## 5. Nginx et HTTPS

`/etc/nginx/sites-available/jibayat` :

```nginx
server {
    listen 80;
    server_name jibayat.commune.ma;
    root /var/www/jibayat/public;
    index index.php;
    client_max_body_size 25M;            # imports Excel, documents

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
    location ~ /\.(?!well-known) { deny all; }
    location /build/ { expires 1y; access_log off; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/jibayat /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d jibayat.commune.ma
```

Dans `/etc/php/8.4/fpm/php.ini`, mettez `upload_max_filesize = 25M`, `post_max_size = 25M`, `opcache.enable=1` et `opcache.validate_timestamps=0`. Puis lancez `sudo systemctl restart php8.4-fpm`.

## 6. Planificateur (sauvegardes, alertes, rapports)

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/jibayat && php artisan schedule:run >> /dev/null 2>&1
```

Tâches planifiées :

| Tâche | Quand |
|---|---|
| Sauvegarde de la base (si activée) | chaque jour à l'heure réglée dans « Paramètres ➜ Sauvegardes » (02:00 par défaut) |
| Alertes | 07:00 |
| Rapport quotidien | 07:15 |
| Rapport hebdomadaire | lundi 07:20 |
| Rapport mensuel | le 1er à 07:25 |

Vérifiez la liste avec `php artisan schedule:list`.

## 7. File d'attente (facultatif)

Les e-mails partent immédiatement. Si l'envoi SMTP est lent, passez `QUEUE_CONNECTION=database` et ajoutez un worker Supervisor dans `/etc/supervisor/conf.d/jibayat.conf` :

```ini
[program:jibayat-worker]
command=php /var/www/jibayat/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=www-data
autostart=true
autorestart=true
stopwaitsecs=3600
stdout_logfile=/var/www/jibayat/storage/logs/worker.log
```

Puis lancez `sudo supervisorctl reread && sudo supervisorctl update`.

## 8. Sauvegardes

- Les fichiers sont dans `storage/app/private/backups` (`.sql.gz`). Le nombre conservé se règle dans l'application.
- Copie externe : indiquez un dossier monté, par exemple un disque, un partage réseau ou un dossier synchronisé, dans « Paramètres ➜ Sauvegardes ». Copiez aussi `storage/app/private`, qui contient les documents joints.
- Restauration, à faire en dehors des heures d'ouverture : `php artisan jibayat:restore jibayat_AAAA-MM-JJ_HHMMSS_auto.sql.gz`. Une sauvegarde de précaution est faite automatiquement avant.

## 9. Mise à jour de l'application

```bash
cd /var/www/jibayat
php artisan down
php artisan jibayat:backup
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
sudo systemctl reload php8.4-fpm
php artisan up
```

## 10. Vérifications après installation

- `php artisan about` : environnement `production`, debug désactivé, caches actifs.
- Connexion avec un compte administrateur, puis changement du mot de passe imposé.
- « Paramètres ➜ État du système » : base joignable, version affichée.
- « Paramètres ➜ Sauvegardes ➜ Sauvegarder maintenant » : un fichier apparaît.
- « Paramètres ➜ Rapports par e-mail ➜ M'envoyer un rapport d'essai » : l'e-mail arrive.
- « Mon profil ➜ Enregistrer mon empreinte » (en HTTPS), puis déconnexion et « Se connecter avec l'empreinte ».
- Une quittance d'essai imprimée en PDF : l'arabe est lisible et le format est A4.
