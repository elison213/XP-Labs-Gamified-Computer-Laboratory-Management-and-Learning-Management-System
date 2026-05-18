# User-session lockscreen entry (Logon-Lockscreen.cmd). Delegates to XplabsUserSession bridge.
param([string] $AgentDir = '')

$ErrorActionPreference = 'Continue'
if ([string]::IsNullOrWhiteSpace($AgentDir)) {
  $AgentDir = Join-Path $env:ProgramFiles 'XPLabsAgent'
}

$logDir = Join-Path $env:ProgramData 'XPLabsAgent\logs'
$logFile = Join-Path $logDir 'logon-lockscreen.log'
if (-not (Test-Path $logDir)) { New-Item -ItemType Directory -Path $logDir -Force | Out-Null }

function Write-LockLog {
  param([string] $Message)
  $line = '{0} {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message
  Add-Content -LiteralPath $logFile -Value $line -Encoding UTF8
}

Write-LockLog 'Invoke-LockscreenUserSession start'

$module = Join-Path $AgentDir 'XplabsUserSession.psm1'
if (-not (Test-Path $module)) {
  Write-LockLog "missing module: $module"
  exit 1
}

Import-Module $module -Force -DisableNameChecking
$ok = Invoke-XplabsLockscreenInUserSession -AgentDir $AgentDir
if ($ok) {
  $visible = Get-Process -Name 'XPLabs.LockScreen' -ErrorAction SilentlyContinue | Where-Object { $_.SessionId -gt 0 } | Select-Object -First 1
  if ($visible) {
    Write-LockLog "lockscreen visible pid=$($visible.Id) session=$($visible.SessionId)"
  } else {
    Write-LockLog 'lockscreen visible (bridge reported success)'
  }
  exit 0
}

Write-LockLog 'lockscreen not visible after bridge'
exit 2
