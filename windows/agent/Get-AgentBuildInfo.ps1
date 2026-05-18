# Prints agent build validation info (version, line counts, key symbols).
param(
  [string] $AgentDir = ''
)

$ErrorActionPreference = 'Continue'
if ([string]::IsNullOrWhiteSpace($AgentDir)) {
  $AgentDir = if (Test-Path (Join-Path $env:ProgramFiles 'XPLabsAgent\XplabsAgent.psm1')) {
    Join-Path $env:ProgramFiles 'XPLabsAgent'
  } else {
    $PSScriptRoot
  }
}

$verPath = Join-Path $AgentDir 'AGENT_VERSION.txt'
$version = if (Test-Path $verPath) { (Get-Content -LiteralPath $verPath -Raw).Trim() } else { 'unknown' }

$symbols = @{
  XplabsUserSession_psm1     = Test-Path (Join-Path $AgentDir 'XplabsUserSession.psm1')
  Invoke_LockscreenBridge    = Test-Path (Join-Path $AgentDir 'Invoke-LockscreenUserSession.ps1')
  Logon_Lockscreen_cmd       = Test-Path (Join-Path $AgentDir 'Logon-Lockscreen.cmd')
  Process_DesktopSessionLock = $false
}

$psm1 = Join-Path $AgentDir 'XplabsAgent.psm1'
$psm1Lines = 0
if (Test-Path $psm1) {
  $psm1Lines = (Get-Content -LiteralPath $psm1).Count
  $symbols.Process_DesktopSessionLock = [bool](Select-String -LiteralPath $psm1 -Pattern 'Invoke-XplabsApplyDesktopSessionLock' -Quiet)
}

$userLines = 0
$userPsm1 = Join-Path $AgentDir 'XplabsUserSession.psm1'
if (Test-Path $userPsm1) { $userLines = (Get-Content -LiteralPath $userPsm1).Count }

$loop = Join-Path $AgentDir 'Run-AgentLoop.ps1'
$hasDesktopLock = $false
if (Test-Path $loop) {
  $hasDesktopLock = [bool](Select-String -LiteralPath $loop -Pattern 'Process-DesktopSessionLock' -Quiet)
}

$lockExe = Join-Path $AgentDir 'LockScreen\XPLabs.LockScreen.exe'
$widgetExe = Join-Path $AgentDir 'Widget\XPLabs.Widget.exe'

$combinedLines = $psm1Lines + $userLines
$buildOk = ($version -eq '4') -and ($psm1Lines -ge 800) -and ($userLines -ge 250) -and $symbols.XplabsUserSession_psm1 -and $hasDesktopLock

[pscustomobject]@{
  agent_dir                    = $AgentDir
  agent_version                = $version
  build_ok                     = $buildOk
  xplabs_agent_psm1_lines      = $psm1Lines
  xplabs_user_session_lines    = $userLines
  combined_module_lines        = $combinedLines
  lockscreen_exe               = Test-Path $lockExe
  widget_exe                   = Test-Path $widgetExe
  process_desktop_session_lock = $hasDesktopLock
  symbols                      = $symbols
} | Format-List

Write-Host ''
Write-Host 'Scheduled tasks:' -ForegroundColor Cyan
foreach ($tn in @('XPLabsLockScreen', 'XPLabsLockScreenPrep', 'XPLabsWidget', 'XPLabsAgentLoop')) {
  schtasks.exe /Query /TN $tn /FO LIST 2>$null | Out-Null
  $ok = ($LASTEXITCODE -eq 0)
  Write-Host ("  {0,-22} {1}" -f $tn, $(if ($ok) { 'OK' } else { 'MISSING' }))
}

Write-Host ''
Write-Host 'UI processes (SessionId > 0 = visible desktop):' -ForegroundColor Cyan
Get-Process XPLabs.LockScreen, XPLabs.Widget -ErrorAction SilentlyContinue |
  Select-Object Name, Id, SessionId | Format-Table -AutoSize
