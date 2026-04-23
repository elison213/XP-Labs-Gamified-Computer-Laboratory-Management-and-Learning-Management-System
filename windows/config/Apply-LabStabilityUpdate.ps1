param(
  [string] $ProjectPath = "",
  [string] $XamppPath = "C:\xampp",
  [string] $DatabaseName = "xplabs",
  [string] $DbUser = "root",
  [string] $DbPassword = "",
  [switch] $SkipMigrate,
  [switch] $SkipServiceRestart
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Assert-Admin {
  $id = [Security.Principal.WindowsIdentity]::GetCurrent()
  $p = New-Object Security.Principal.WindowsPrincipal($id)
  if (-not $p.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw "Run this script in an elevated PowerShell window."
  }
}

function Resolve-ProjectPath([string]$InputPath) {
  if ($InputPath -and $InputPath.Trim().Length -gt 0) { return $InputPath }
  $scriptRoot = if ($PSScriptRoot) { $PSScriptRoot } else { Split-Path -Parent $MyInvocation.MyCommand.Path }
  return Split-Path -Parent (Split-Path -Parent $scriptRoot)
}

function Get-PhpExe([string]$XamppRoot) {
  $php = Join-Path $XamppRoot "php\php.exe"
  if (Test-Path $php) { return $php }
  $cmd = Get-Command php -ErrorAction SilentlyContinue
  if ($cmd) { return $cmd.Path }
  return $null
}

function Get-MysqlExe([string]$XamppRoot) {
  $mysql = Join-Path $XamppRoot "mysql\bin\mysql.exe"
  if (Test-Path $mysql) { return $mysql }
  return $null
}

function Build-MySqlArgs([string]$User, [string]$Pass) {
  if ([string]::IsNullOrEmpty($Pass)) { return @("-u", $User) }
  return @("-u", $User, "-p$Pass")
}

function Ensure-ServiceRunning([string]$ServiceName) {
  $svc = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
  if (-not $svc) {
    Write-Host "Service '$ServiceName' not found. Skipping." -ForegroundColor Yellow
    return
  }
  if ($svc.StartType -ne "Automatic") { Set-Service -Name $ServiceName -StartupType Automatic }
  if ($svc.Status -ne "Running") { Start-Service -Name $ServiceName }
}

Assert-Admin
$ProjectPath = Resolve-ProjectPath -InputPath $ProjectPath

$phpExe = Get-PhpExe -XamppRoot $XamppPath
$mysqlExe = Get-MysqlExe -XamppRoot $XamppPath
$migratePhp = Join-Path $ProjectPath "database\migrate.php"
$mysqlAuth = Build-MySqlArgs -User $DbUser -Pass $DbPassword

Write-Host "== XPLabs Stability/Security Update ==" -ForegroundColor Cyan
Write-Host "Project: $ProjectPath"
Write-Host "XAMPP: $XamppPath"
Write-Host "Database: $DatabaseName"

if (-not (Test-Path $ProjectPath)) { throw "Project path not found: $ProjectPath" }
if (-not (Test-Path (Join-Path $ProjectPath "api\session\override-unlock.php"))) {
  throw "Expected updated file missing: api/session/override-unlock.php"
}

if (-not $SkipMigrate) {
  if (-not $phpExe) { throw "php.exe not found. Install XAMPP/PHP or add php to PATH." }
  if (-not (Test-Path $migratePhp)) { throw "Migration runner not found: $migratePhp" }
  Write-Host "Running migrations..." -ForegroundColor Cyan
  & $phpExe $migratePhp
}

if ($mysqlExe) {
  Write-Host "Validating new override schema..." -ForegroundColor Cyan
  & $mysqlExe @mysqlAuth -e "USE `${DatabaseName}`; SHOW COLUMNS FROM users LIKE 'can_unlock_pc_override';"
  & $mysqlExe @mysqlAuth -e "USE `${DatabaseName}`; SHOW TABLES LIKE 'pc_override_tokens';"
  & $mysqlExe @mysqlAuth -e "USE `${DatabaseName}`; SHOW TABLES LIKE 'pc_override_attempts';"
} else {
  Write-Host "mysql.exe not found under XAMPP; skipping DB verification queries." -ForegroundColor Yellow
}

if (-not $SkipServiceRestart) {
  Write-Host "Ensuring Apache/MySQL services are running..." -ForegroundColor Cyan
  Ensure-ServiceRunning -ServiceName "Apache2.4"
  Ensure-ServiceRunning -ServiceName "mysql"
  Ensure-ServiceRunning -ServiceName "mysql80"
  Ensure-ServiceRunning -ServiceName "mariadb"
}

Write-Host "Restarting XPLabs agent task if present..." -ForegroundColor Cyan
try {
  $task = Get-ScheduledTask -TaskName "XPLabs-AgentLoop" -ErrorAction SilentlyContinue
  if ($task) {
    Stop-ScheduledTask -TaskName "XPLabs-AgentLoop" -ErrorAction SilentlyContinue | Out-Null
    Start-ScheduledTask -TaskName "XPLabs-AgentLoop" | Out-Null
    Write-Host "Task restarted: XPLabs-AgentLoop" -ForegroundColor Green
  } else {
    Write-Host "Task XPLabs-AgentLoop not found. Skipping." -ForegroundColor Yellow
  }
} catch {
  Write-Host "Could not restart XPLabs-AgentLoop task: $($_.Exception.Message)" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Update applied. Quick checks:" -ForegroundColor Green
Write-Host "1) Login and open monitoring/dashboard_lab_pcs pages"
Write-Host "2) Test kiosk unlock + checkout"
Write-Host "3) Test force logout from dashboard_lab_pcs"
Write-Host "4) Test lockscreen authorized override login"
Write-Host ""
Write-Host "If lockscreen binary was rebuilt, redeploy latest EXE to client machines." -ForegroundColor Yellow
