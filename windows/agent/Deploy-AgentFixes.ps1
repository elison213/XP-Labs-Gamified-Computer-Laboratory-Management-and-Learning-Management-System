# Copy latest agent scripts to Program Files and restart the agent loop. Run as Administrator.
param(
  [string] $SourceDir = ""
)

$ErrorActionPreference = 'Stop'
if (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
  throw 'Run this script as Administrator.'
}

if ([string]::IsNullOrWhiteSpace($SourceDir)) {
  $candidates = @(
    (Join-Path $PSScriptRoot '.'),
    'C:\xplabs\windows\agent',
    'C:\xampp\htdocs\xplabs\windows\agent'
  )
  $SourceDir = $candidates | Where-Object { Test-Path (Join-Path $_ 'XplabsAgent.psm1') } | Select-Object -First 1
}
if (-not $SourceDir -or -not (Test-Path (Join-Path $SourceDir 'XplabsAgent.psm1'))) {
  throw "Agent source not found. Pass -SourceDir or place repo at C:\xplabs or C:\xampp\htdocs\xplabs."
}

$dst = Join-Path $env:ProgramFiles 'XPLabsAgent'
if (-not (Test-Path $dst)) { New-Item -ItemType Directory -Path $dst -Force | Out-Null }

Write-Host "Source: $SourceDir" -ForegroundColor Cyan
Write-Host "Target: $dst" -ForegroundColor Cyan

schtasks /End /TN XPLabsAgentLoop 2>$null | Out-Null
Get-Process XPLabs.LockScreen, XPLabs.Widget -ErrorAction SilentlyContinue | Stop-Process -Force
Start-Sleep -Seconds 1

Copy-Item -Path (Join-Path $SourceDir '*') -Destination $dst -Recurse -Force

$register = Join-Path $dst 'Register-XplabsUiTasks.ps1'
if (Test-Path $register) {
  Write-Host 'Registering UI scheduled tasks...' -ForegroundColor Cyan
  & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $register -SkipAgentRestart
} else {
  $fix = Join-Path $dst 'Fix-LockscreenTasks.ps1'
  if (Test-Path $fix) {
    Write-Host 'Repairing lockscreen scheduled tasks (legacy)...' -ForegroundColor Cyan
    & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $fix -SkipAgentRestart
  }
}

schtasks /Run /TN XPLabsAgentLoop 2>$null | Out-Null
Write-Host 'Done. Agent restarted. Rebuild LockScreen EXE if Ctrl+Shift+X still only shows a message.' -ForegroundColor Green
