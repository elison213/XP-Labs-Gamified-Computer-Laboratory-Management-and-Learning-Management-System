# DEPRECATED: use Logon-Lockscreen.cmd or Invoke-LockscreenUserSession.ps1 (XplabsUserSession bridge).
param([int] $MaxWaitSeconds = 300)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'SilentlyContinue'

$programDir = Join-Path $env:ProgramFiles 'XPLabsAgent'
$invoke = Join-Path $programDir 'Invoke-LockscreenUserSession.ps1'
if (Test-Path $invoke) {
  & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $invoke -AgentDir $programDir
  exit $LASTEXITCODE
}

$logonCmd = Join-Path $programDir 'Logon-Lockscreen.cmd'
if (Test-Path $logonCmd) {
  Start-Process -FilePath $logonCmd -WindowStyle Hidden | Out-Null
  Start-Sleep -Seconds 3
  $visible = Get-Process -Name 'XPLabs.LockScreen' -ErrorAction SilentlyContinue | Where-Object { $_.SessionId -gt 0 }
  exit $(if ($visible) { 0 } else { 3 })
}

Write-Warning 'Launch-Lockscreen.ps1 is deprecated; deploy Logon-Lockscreen.cmd + XplabsUserSession.psm1'
exit 1
