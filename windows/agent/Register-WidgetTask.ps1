# Register XPLabsWidget + XPLabsShowWidget at user logon. Run as Administrator.
# Delegates to Register-XplabsUiTasks.ps1 (single source of truth).
param([switch] $SkipAgentRestart)

$ErrorActionPreference = 'Stop'
$register = Join-Path $PSScriptRoot 'Register-XplabsUiTasks.ps1'
if (-not (Test-Path $register)) {
  throw "Missing $register"
}

$args = @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $register)
if ($SkipAgentRestart) { $args += '-SkipAgentRestart' }
& powershell.exe @args

Start-Sleep -Seconds 2
Get-Process XPLabs.Widget -ErrorAction SilentlyContinue | Select-Object Name, Id, SessionId
