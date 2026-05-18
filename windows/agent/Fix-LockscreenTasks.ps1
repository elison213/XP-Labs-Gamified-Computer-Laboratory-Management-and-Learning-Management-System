# Repairs XPLabs lockscreen + widget scheduled tasks. Run as Administrator at the desktop.
param(
  [switch] $SkipAgentRestart,
  [switch] $TestLockscreen
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$register = Join-Path $PSScriptRoot 'Register-XplabsUiTasks.ps1'
if (-not (Test-Path $register)) {
  $register = Join-Path (Join-Path $env:ProgramFiles 'XPLabsAgent') 'Register-XplabsUiTasks.ps1'
}
if (-not (Test-Path $register)) {
  throw "Missing Register-XplabsUiTasks.ps1 next to this script or under Program Files\XPLabsAgent."
}

schtasks /End /TN XPLabsAgentLoop 2>$null | Out-Null
Get-Process XPLabs.LockScreen, XPLabs.Widget -ErrorAction SilentlyContinue | Stop-Process -Force
Start-Sleep -Seconds 2

$regArgs = @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $register)
if ($SkipAgentRestart) { $regArgs += '-SkipAgentRestart' }
& powershell.exe @regArgs

if ($TestLockscreen) {
  $logon = Join-Path $env:ProgramFiles 'XPLabsAgent\Logon-Lockscreen.cmd'
  if (Test-Path $logon) {
    cmd.exe /c "`"$logon`""
    Start-Sleep -Seconds 3
    Get-Process XPLabs.LockScreen -ErrorAction SilentlyContinue | Select-Object Id, SessionId
  }
}

Write-Host 'Fix-LockscreenTasks complete.' -ForegroundColor Green
