param(
  [Parameter(Mandatory)] [string] $KioskUsername,
  [string] $ProgramDir = "C:\Program Files\XPLabsAgent",
  [switch] $SetAutoLogon
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

Assert-Admin

$lockExe = Join-Path $ProgramDir 'LockScreen\XPLabs.LockScreen.exe'
if (-not (Test-Path $lockExe)) {
  throw "Lockscreen executable not found: $lockExe"
}

# Backup current shell configuration.
$backupDir = Join-Path $env:ProgramData 'XPLabsAgent'
if (-not (Test-Path $backupDir)) { New-Item -ItemType Directory -Path $backupDir -Force | Out-Null }
$backupPath = Join-Path $backupDir 'kiosk-shell-backup.txt'

$winlogonPath = 'HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon'
$currentShell = (Get-ItemProperty -Path $winlogonPath -Name 'Shell' -ErrorAction SilentlyContinue).Shell
if (-not $currentShell) { $currentShell = 'explorer.exe' }
Set-Content -Path $backupPath -Value $currentShell -Encoding UTF8

# Set global shell to lockscreen executable.
Set-ItemProperty -Path $winlogonPath -Name 'Shell' -Value "`"$lockExe`""

if ($SetAutoLogon) {
  Write-Warning "Auto-logon is not auto-configured for security reasons. Configure autologon manually for kiosk account: $KioskUsername"
}

Write-Host "Configured kiosk shell to lockscreen executable." -ForegroundColor Green
Write-Host "Backup saved at: $backupPath"
Write-Host "Reboot required for shell change to fully apply."
