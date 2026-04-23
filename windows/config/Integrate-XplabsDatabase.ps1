param(
  [string] $ProjectPath = "C:\xampp\htdocs\xplabs",
  [string] $XamppPath = "C:\xampp",
  [string] $DatabaseName = "xplabs",
  [string] $DbUser = "root",
  [string] $DbPassword = "",
  [string] $DumpPath = "",
  [switch] $SkipMigrations,
  [switch] $SkipSeed
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Assert-Admin {
  $id = [Security.Principal.WindowsIdentity]::GetCurrent()
  $p = New-Object Security.Principal.WindowsPrincipal($id)
  if (-not $p.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw "Run this script in an elevated PowerShell window."
  }
}

function Ensure-ServiceRunning([string]$ServiceName) {
  $svc = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
  if (-not $svc) {
    Write-Host "Service '$ServiceName' not found (continuing)." -ForegroundColor Yellow
    return
  }
  if ($svc.StartType -ne 'Automatic') { Set-Service -Name $ServiceName -StartupType Automatic }
  if ($svc.Status -ne 'Running') { Start-Service -Name $ServiceName }
}

function Get-MysqlExe([string]$XamppRoot) {
  $mysql = Join-Path $XamppRoot "mysql\bin\mysql.exe"
  if (-not (Test-Path $mysql)) { throw "mysql.exe not found: $mysql" }
  return $mysql
}

function Get-PhpExe([string]$XamppRoot) {
  $php = Join-Path $XamppRoot "php\php.exe"
  if (Test-Path $php) { return $php }
  $cmd = Get-Command php -ErrorAction SilentlyContinue
  if ($cmd) { return $cmd.Path }
  return $null
}

function Build-MySqlArgs([string]$User, [string]$Pass) {
  if ([string]::IsNullOrEmpty($Pass)) {
    return @("-u", $User)
  }
  return @("-u", $User, "-p$Pass")
}

Assert-Admin

$mysqlExe = Get-MysqlExe -XamppRoot $XamppPath
$phpExe = Get-PhpExe -XamppRoot $XamppPath
$migratePhp = Join-Path $ProjectPath "database\migrate.php"
$seedSql = Join-Path $ProjectPath "windows\config\db\xplabs.post-import.seed.sql"
if (-not $DumpPath) {
  $DumpPath = Join-Path $ProjectPath "windows\config\db\xplabs_dump.sql"
}

Write-Host "== XPLabs DB integration ==" -ForegroundColor Cyan
Write-Host "Project: $ProjectPath"
Write-Host "DB: $DatabaseName"
Write-Host "MySQL: $mysqlExe"
Write-Host "Dump path: $DumpPath"

if (-not (Test-Path $ProjectPath)) { throw "Project path not found: $ProjectPath" }

# Ensure MySQL is running
Ensure-ServiceRunning -ServiceName "mysql"
Ensure-ServiceRunning -ServiceName "mysql80"
Ensure-ServiceRunning -ServiceName "mariadb"

$mysqlAuth = Build-MySqlArgs -User $DbUser -Pass $DbPassword

Write-Host "Ensuring database exists..." -ForegroundColor Cyan
& $mysqlExe @mysqlAuth -e "CREATE DATABASE IF NOT EXISTS \`$DatabaseName\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if (Test-Path $DumpPath) {
  Write-Host "Importing dump file..." -ForegroundColor Cyan
  cmd /c "`"$mysqlExe`" $($mysqlAuth -join ' ') $DatabaseName < `"$DumpPath`""
  Write-Host "Dump import completed." -ForegroundColor Green
} else {
  if (-not $SkipMigrations) {
    if ($phpExe -and (Test-Path $migratePhp)) {
      Write-Host "No dump found. Running migrations..." -ForegroundColor Cyan
      & $phpExe $migratePhp
    } else {
      Write-Host "No dump and migrate.php/php not available; skipping migrations." -ForegroundColor Yellow
    }
  }
}

if (-not $SkipSeed -and (Test-Path $seedSql)) {
  Write-Host "Applying seed package..." -ForegroundColor Cyan
  cmd /c "`"$mysqlExe`" $($mysqlAuth -join ' ') $DatabaseName < `"$seedSql`""
}

Write-Host "Verifying key tables..." -ForegroundColor Cyan
& $mysqlExe @mysqlAuth -e "USE \`$DatabaseName\`; SHOW TABLES;"
& $mysqlExe @mysqlAuth -e "USE \`$DatabaseName\`; SELECT id,lrn,role,is_active FROM users LIMIT 10;"

Write-Host "Done. Database integration complete." -ForegroundColor Green

