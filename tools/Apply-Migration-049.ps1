# Apply heartbeat delivery migration (049) using XAMPP MySQL.
param(
  [string] $MysqlExe = 'C:\xampp\mysql\bin\mysql.exe',
  [string] $Database = 'xplabs',
  [string] $User = 'root',
  [string] $Password = ''
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$sqlFile = Join-Path $root 'database\migrations\049_add_heartbeat_delivery_protocol.sql'

if (-not (Test-Path $MysqlExe)) {
  throw "mysql.exe not found at $MysqlExe. Install XAMPP or pass -MysqlExe."
}
if (-not (Test-Path $sqlFile)) {
  throw "Migration file not found: $sqlFile"
}

$mysqlArgs = @('-u', $User, $Database)
if ($Password) { $mysqlArgs = @('-u', $User, "-p$Password", $Database) }

Write-Host "Applying migration 049 to database '$Database'..." -ForegroundColor Cyan
Get-Content -LiteralPath $sqlFile -Raw | & $MysqlExe @mysqlArgs
if ($LASTEXITCODE -ne 0) {
  throw "mysql exited with code $LASTEXITCODE"
}

$out = & $MysqlExe @mysqlArgs -N -e "SHOW TABLES LIKE 'pc_heartbeat_receipts';" 2>&1
Write-Host "pc_heartbeat_receipts table: $out" -ForegroundColor Green
Write-Host "Done." -ForegroundColor Green
