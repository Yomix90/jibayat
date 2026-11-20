# Lance jibayat-laravel en local avec le php.ini du projet (extensions actives).
# « php artisan serve » ne transmet pas PHPRC au serveur enfant : on appelle donc le serveur PHP intégré directement.
#   powershell -ExecutionPolicy Bypass -File C:\wamp64\SGFC\tools\serve.ps1   ->  http://127.0.0.1:8000
$php = 'C:\Users\PC\.config\herd\bin\php84\php.exe'
$ini = Join-Path $PSScriptRoot 'php\php.ini'
$app = Join-Path (Split-Path $PSScriptRoot -Parent) 'jibayat-laravel'
Set-Location (Join-Path $app 'public')
& $php -c $ini -S 127.0.0.1:8000 -t . ..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php
