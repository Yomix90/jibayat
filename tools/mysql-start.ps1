# Demarre le MySQL 8.4 de developpement de Jibayat (port 3307, donnees dans tools\mysql\data).
# Binaire : celui de WAMP, deplace dans C:\wamp64\old. Les bases WAMP ne sont pas touchees.
#   powershell -ExecutionPolicy Bypass -File C:\wamp64\SGFC\tools\mysql-start.ps1
$bin = 'C:\wamp64\old\bin\mysql\mysql8.4.7\bin'
$ini = Join-Path $PSScriptRoot 'mysql\my.ini'
Start-Process -FilePath "$bin\mysqld.exe" -ArgumentList "--defaults-file=`"$ini`"" -WindowStyle Hidden
Write-Host 'MySQL de developpement demarre sur 127.0.0.1:3307'
