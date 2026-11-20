# Prépare la session PowerShell pour travailler sur jibayat-laravel :
#   . C:\wamp64\SGFC\tools\env.ps1
# Utilise le php.ini du projet (extensions Laravel activées) sans toucher à la config de Herd.
$env:PHPRC = Join-Path $PSScriptRoot 'php'
Write-Host "PHPRC = $env:PHPRC"
php -r "echo 'PHP ' . PHP_VERSION . ' — ' . count(get_loaded_extensions()) . ' extensions' . PHP_EOL;"
