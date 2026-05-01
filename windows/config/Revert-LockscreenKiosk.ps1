param(
  [string] $BackupPath = "$env:ProgramData\XPLabsAgent\kiosk-shell-backup.txt"
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

$winlogonPath = 'HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion\Winlogon'
$shell = 'explorer.exe'
if (Test-Path $BackupPath) {
  $saved = (Get-Content -Path $BackupPath -ErrorAction SilentlyContinue | Select-Object -First 1)
  if ($saved -and $saved.Trim().Length -gt 0) {
    $shell = $saved.Trim()
  }
}

Set-ItemProperty -Path $winlogonPath -Name 'Shell' -Value $shell
Write-Host "Restored shell to: $shell" -ForegroundColor Green
Write-Host "Reboot required for shell change to fully apply."
