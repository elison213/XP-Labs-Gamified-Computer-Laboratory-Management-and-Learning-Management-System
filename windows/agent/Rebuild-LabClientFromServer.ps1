# Full lab PC rebuild from canonical server repo (MeshCentral-informed user-session bridge).
param(
  [Parameter(Mandatory)] [string] $ServerShare,
  [switch] $ResetState,
  [switch] $SkipExeCopy,
  [string] $ServerBaseUrl = ''
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
  throw 'Run as Administrator on the lab PC.'
}

$shareRoot = $ServerShare.Trim().TrimEnd('\')
$agentShare = Join-Path $shareRoot 'agent'
if (-not (Test-Path (Join-Path $agentShare 'XplabsAgent.psm1'))) {
  if (Test-Path (Join-Path $shareRoot 'XplabsAgent.psm1')) {
    $agentShare = $shareRoot
  } else {
    throw "Server agent not found under $ServerShare (expected ...\windows\agent or agent folder with XplabsAgent.psm1)"
  }
}

$programDir = Join-Path $env:ProgramFiles 'XPLabsAgent'
$dataDir = Join-Path $env:ProgramData 'XPLabsAgent'
$statePath = Join-Path $dataDir 'state.json'

Write-Host '=== Rebuild XPLabs lab client from server ===' -ForegroundColor Cyan
Write-Host "  Share: $agentShare"
Write-Host "  Target: $programDir"

Write-Host 'Stopping UI and agent...' -ForegroundColor Cyan
Get-Process XPLabs.LockScreen, XPLabs.Widget -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
schtasks.exe /End /TN XPLabsAgentLoop 2>$null | Out-Null
Start-Sleep -Seconds 2

if (-not (Test-Path $programDir)) {
  New-Item -ItemType Directory -Path $programDir -Force | Out-Null
}

Write-Host 'Copying agent scripts...' -ForegroundColor Cyan
Copy-Item -Path (Join-Path $agentShare '*') -Destination $programDir -Recurse -Force

if (-not $SkipExeCopy) {
  $lockSrc = Join-Path $shareRoot 'lockscreen\XPLabs.LockScreen\bin\Release\net48\XPLabs.LockScreen.exe'
  if (-not (Test-Path $lockSrc)) {
    $lockSrc = Join-Path $shareRoot '..\lockscreen\XPLabs.LockScreen\bin\Release\net48\XPLabs.LockScreen.exe'
  }
  $widgetSrc = Join-Path $shareRoot 'widget\XPLabs.Widget\bin\Release\net48\XPLabs.Widget.exe'
  if (-not (Test-Path $widgetSrc)) {
    $widgetSrc = Join-Path $shareRoot '..\widget\XPLabs.Widget\bin\Release\net48\XPLabs.Widget.exe'
  }
  if (Test-Path $lockSrc) {
    $lockDst = Join-Path $programDir 'LockScreen'
    if (-not (Test-Path $lockDst)) { New-Item -ItemType Directory -Path $lockDst -Force | Out-Null }
    Copy-Item -Path $lockSrc -Destination (Join-Path $lockDst 'XPLabs.LockScreen.exe') -Force
    Write-Host "  Lockscreen EXE: $lockSrc" -ForegroundColor Green
  } else {
    Write-Host '  Lockscreen EXE not on share (build on server or pass existing Program Files copy)' -ForegroundColor Yellow
  }
  if (Test-Path $widgetSrc) {
    $widgetDst = Join-Path $programDir 'Widget'
    if (-not (Test-Path $widgetDst)) { New-Item -ItemType Directory -Path $widgetDst -Force | Out-Null }
    Copy-Item -Path $widgetSrc -Destination (Join-Path $widgetDst 'XPLabs.Widget.exe') -Force
    Write-Host "  Widget EXE: $widgetSrc" -ForegroundColor Green
  } else {
    Write-Host '  Widget EXE not on share' -ForegroundColor Yellow
  }
}

$install = Join-Path $programDir 'Install-Agent.ps1'
if (Test-Path $install) {
  Write-Host 'Running Install-Agent.ps1...' -ForegroundColor Cyan
  & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $install -SourceDir $programDir
} else {
  Write-Warning 'Install-Agent.ps1 missing after copy'
}

$register = Join-Path $programDir 'Register-XplabsUiTasks.ps1'
if (Test-Path $register) {
  Write-Host 'Registering UI tasks...' -ForegroundColor Cyan
  & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $register -AgentDir $programDir
}

if ($ResetState) {
  Write-Host 'Resetting state.json to locked boot state...' -ForegroundColor Cyan
  if (-not (Test-Path $dataDir)) { New-Item -ItemType Directory -Path $dataDir -Force | Out-Null }
  $boot = @{
    locked                   = $true
    last_command_cursor      = 0
    last_student_login_status = ''
    last_student_login_message = ''
    override_unlock_until    = ''
    student_login_grace_until = ''
  }
  if (Test-Path $statePath) {
    try {
      $existing = Get-Content -LiteralPath $statePath -Raw -Encoding UTF8 | ConvertFrom-Json
      # Always reset command cursor on -ResetState so it matches server queue resets.
      if ($existing.PSObject.Properties.Name -contains 'machine_key') {
        $boot['machine_key'] = $existing.machine_key
      }
    } catch {}
  }
  $boot | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath $statePath -Encoding UTF8
}

$buildInfo = Join-Path $programDir 'Get-AgentBuildInfo.ps1'
if (Test-Path $buildInfo) {
  Write-Host "`n=== Build validation ===" -ForegroundColor Cyan
  & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $buildInfo -AgentDir $programDir
}

$lines = (Get-Content -LiteralPath (Join-Path $programDir 'XplabsAgent.psm1')).Count
$ver = 'unknown'
$verFile = Join-Path $programDir 'AGENT_VERSION.txt'
if (Test-Path $verFile) { $ver = (Get-Content -LiteralPath $verFile -Raw).Trim() }

$userLines = 0
$userPsm1 = Join-Path $programDir 'XplabsUserSession.psm1'
if (Test-Path $userPsm1) { $userLines = (Get-Content -LiteralPath $userPsm1).Count }
$ok = ($lines -ge 800) -and ($userLines -ge 250) -and ($ver -eq '4')
Write-Host "`nSummary: AGENT_VERSION=$ver  agent=$lines lines  user-session=$userLines lines" -ForegroundColor $(if ($ok) { 'Green' } else { 'Red' })
Write-Host 'Next: sign OUT and sign IN; run Get-Process XPLabs.LockScreen | Select SessionId (must be > 0)' -ForegroundColor Cyan
Write-Host 'On server only: php tools\reset-pc-command-queue.php --pc_id=N' -ForegroundColor Cyan
