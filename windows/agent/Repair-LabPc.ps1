# One-shot lab PC repair: sync agent from server share, deploy, register UI tasks. Run as Administrator on the LAB PC only.
param(
  [string] $SourceDir = '',
  [string] $ServerShare = '',
  [int] $MinModuleLines = 800,
  [int] $MinUserSessionLines = 250
)

$ErrorActionPreference = 'Stop'
$ExpectedAgentVersion = '4'
if (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
  throw 'Run this script as Administrator on the lab PC.'
}

$labAgentDir = 'C:\xplabs\windows\agent'
if (-not (Test-Path (Split-Path $labAgentDir -Parent))) {
  New-Item -ItemType Directory -Path $labAgentDir -Force | Out-Null
}

function Test-AgentSourceFresh {
  param([string]$Dir)
  $psm1 = Join-Path $Dir 'XplabsAgent.psm1'
  if (-not (Test-Path $psm1)) { return $false }
  $lines = (Get-Content -LiteralPath $psm1).Count
  if ($lines -lt $MinModuleLines) { return $false }
  $userPsm1 = Join-Path $Dir 'XplabsUserSession.psm1'
  if (-not (Test-Path $userPsm1)) { return $false }
  if ((Get-Content -LiteralPath $userPsm1).Count -lt $MinUserSessionLines) { return $false }
  $verPath = Join-Path $Dir 'AGENT_VERSION.txt'
  if (Test-Path $verPath) {
    $v = (Get-Content -LiteralPath $verPath -Raw).Trim()
    if ($v -ne $ExpectedAgentVersion) { return $false }
  }
  $loop = Join-Path $Dir 'Run-AgentLoop.ps1'
  if (-not (Test-Path $loop)) { return $false }
  if (-not (Select-String -LiteralPath $loop -Pattern 'Process-DesktopSessionLock' -Quiet)) { return $false }
  return (Test-Path (Join-Path $Dir 'XplabsUserSession.psm1'))
}

if (-not [string]::IsNullOrWhiteSpace($ServerShare)) {
  $sharePsm1 = Join-Path $ServerShare.Trim().TrimEnd('\') 'XplabsAgent.psm1'
  if (-not (Test-Path $sharePsm1)) {
    throw "ServerShare not found or missing XplabsAgent.psm1: $ServerShare"
  }
  Write-Host "=== Sync agent from server share ===" -ForegroundColor Cyan
  Write-Host "  From: $ServerShare"
  Write-Host "  To:   $labAgentDir"
  Copy-Item -Path (Join-Path $ServerShare '*') -Destination $labAgentDir -Recurse -Force
  $SourceDir = $labAgentDir
}

if ([string]::IsNullOrWhiteSpace($SourceDir)) {
  $candidates = @(
    $labAgentDir,
    (Join-Path $PSScriptRoot '.'),
    '\\localhost\c$\xampp\htdocs\xplabs\windows\agent'
  )
  $SourceDir = $candidates | Where-Object { Test-AgentSourceFresh -Dir $_ } | Select-Object -First 1
  if (-not $SourceDir) {
    $SourceDir = $candidates | Where-Object { Test-Path (Join-Path $_ 'XplabsAgent.psm1') } | Select-Object -First 1
  }
}

if (-not $SourceDir -or -not (Test-Path (Join-Path $SourceDir 'XplabsAgent.psm1'))) {
  throw @"
Agent source not found. Copy the latest agent from the XAMPP server, then re-run:

  Copy-Item -Path '\\YOUR-SERVER\c$\xampp\htdocs\xplabs\windows\agent\*' -Destination C:\xplabs\windows\agent -Recurse -Force
  powershell -ExecutionPolicy Bypass -File C:\xplabs\windows\agent\Repair-LabPc.ps1

Or: Repair-LabPc.ps1 -ServerShare '\\YOUR-SERVER\c$\xampp\htdocs\xplabs\windows\agent'
"@
}

$srcLines = (Get-Content -LiteralPath (Join-Path $SourceDir 'XplabsAgent.psm1')).Count
Write-Host "Agent source: $SourceDir ($srcLines lines in XplabsAgent.psm1)" -ForegroundColor Cyan
if (-not (Test-AgentSourceFresh -Dir $SourceDir)) {
  Write-Host "WARNING: Source looks OUTDATED (need XplabsAgent.psm1 >= $MinModuleLines, XplabsUserSession.psm1 >= $MinUserSessionLines, AGENT_VERSION $ExpectedAgentVersion)." -ForegroundColor Red
  Write-Host "Sync from server share before continuing." -ForegroundColor Red
  if ($srcLines -lt $MinModuleLines) {
    throw 'Refusing to deploy stale agent. Use -ServerShare or copy from \\server\c$\xampp\htdocs\xplabs\windows\agent'
  }
}

$deploy = Join-Path $SourceDir 'Deploy-AgentFixes.ps1'
if (-not (Test-Path $deploy)) {
  $deploy = Join-Path (Join-Path $env:ProgramFiles 'XPLabsAgent') 'Deploy-AgentFixes.ps1'
}

$rebuild = Join-Path $SourceDir 'Rebuild-LabClientFromServer.ps1'
if (-not [string]::IsNullOrWhiteSpace($ServerShare) -and (Test-Path $rebuild)) {
  $windowsShare = Split-Path $ServerShare.Trim().TrimEnd('\') -Parent
  if (-not (Test-Path (Join-Path $windowsShare 'agent\XplabsAgent.psm1'))) {
    $windowsShare = $ServerShare.Trim().TrimEnd('\')
  }
  Write-Host "`n=== Full rebuild from server share ===" -ForegroundColor Cyan
  & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $rebuild -ServerShare $windowsShare
} elseif (Test-Path $deploy) {
  Write-Host "`n=== Deploy agent scripts ===" -ForegroundColor Cyan
  & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $deploy -SourceDir $SourceDir
} else {
  throw "Missing Deploy-AgentFixes.ps1 in $SourceDir"
}

$pfPsm1 = Join-Path $env:ProgramFiles 'XPLabsAgent\XplabsAgent.psm1'
$pfUser = Join-Path $env:ProgramFiles 'XPLabsAgent\XplabsUserSession.psm1'
$pfLines = if (Test-Path $pfPsm1) { (Get-Content -LiteralPath $pfPsm1).Count } else { 0 }
$pfUserLines = if (Test-Path $pfUser) { (Get-Content -LiteralPath $pfUser).Count } else { 0 }
Write-Host "`nProgram Files: XplabsAgent.psm1=$pfLines lines, XplabsUserSession.psm1=$pfUserLines lines" -ForegroundColor $(if ($pfLines -ge $MinModuleLines -and $pfUserLines -ge $MinUserSessionLines) { 'Green' } else { 'Red' })
$buildInfo = Join-Path $env:ProgramFiles 'XPLabsAgent\Get-AgentBuildInfo.ps1'
if (Test-Path $buildInfo) {
  & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $buildInfo -AgentDir (Join-Path $env:ProgramFiles 'XPLabsAgent') | Out-Null
}
$pfLoop = Join-Path $env:ProgramFiles 'XPLabsAgent\Run-AgentLoop.ps1'
if (Test-Path $pfLoop) {
  $hasSignIn = Select-String -LiteralPath $pfLoop -Pattern 'Process-DesktopSessionLock' -Quiet
  Write-Host "Run-AgentLoop sign-in lock: $(if ($hasSignIn) { 'YES' } else { 'NO — redeploy from server' })" -ForegroundColor $(if ($hasSignIn) { 'Green' } else { 'Red' })
}

function Get-SchtaskRunLine {
  param([string]$TaskName)
  $lines = @(schtasks.exe /Query /TN $TaskName /V /FO LIST 2>$null)
  if ($lines.Count -eq 0) { $lines = @(schtasks.exe /Query /TN $TaskName /FO LIST 2>$null) }
  if ($lines.Count -eq 0) { return $null }
  $match = $lines | Select-String -Pattern '^(Task To Run|Execute)\s*:' | Select-Object -First 1
  if ($match) { return $match.Line.Trim() }
  return $null
}

Write-Host "`n=== Verification ===" -ForegroundColor Cyan
$tasks = @('XPLabsLockScreen', 'XPLabsLockScreenPrep', 'XPLabsWidget', 'XPLabsShowWidget', 'XPLabsAgentLoop')
$prevEap = $ErrorActionPreference
$ErrorActionPreference = 'Continue'
foreach ($tn in $tasks) {
  schtasks.exe /Query /TN $tn /FO LIST 2>$null | Out-Null
  if ($LASTEXITCODE -eq 0) {
    $run = Get-SchtaskRunLine -TaskName $tn
    Write-Host "OK  $tn" -ForegroundColor Green
    if ($run) { Write-Host "    $run" }
  } else {
    Write-Host "MISSING  $tn" -ForegroundColor Yellow
  }
}
$ErrorActionPreference = $prevEap

Write-Host "`nProcesses (SessionId must be > 0 for UI):" -ForegroundColor Cyan
Get-Process XPLabs.LockScreen, XPLabs.Widget -ErrorAction SilentlyContinue |
  Select-Object Name, Id, SessionId | Format-Table -AutoSize

if (Test-Path 'C:\ProgramData\XPLabsAgent\state.json') {
  Write-Host 'state.json:' -ForegroundColor Cyan
  Get-Content 'C:\ProgramData\XPLabsAgent\state.json' -Raw
}

Write-Host "`nNext steps:" -ForegroundColor Cyan
Write-Host '  1. Sign OUT of Windows, then sign IN (not just lock). Watch agent.log for: Windows sign-in detected'
Write-Host '  2. On server: queue Lock from dashboard (cursor was reset). Client should log Command processed + lockscreen visible'
Write-Host '  3. Get-Process XPLabs.LockScreen | SessionId must be > 0'
