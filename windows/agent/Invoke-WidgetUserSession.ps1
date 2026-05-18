# User-session widget entry (Show-Widget-Now.cmd). Delegates to XplabsUserSession bridge.
param([string] $AgentDir = '')

$ErrorActionPreference = 'Continue'
if ([string]::IsNullOrWhiteSpace($AgentDir)) {
  $AgentDir = Join-Path $env:ProgramFiles 'XPLabsAgent'
}

$module = Join-Path $AgentDir 'XplabsUserSession.psm1'
if (-not (Test-Path $module)) { exit 1 }

Import-Module $module -Force -DisableNameChecking
if (Invoke-XplabsWidgetInUserSession -AgentDir $AgentDir) { exit 0 }
exit 2
