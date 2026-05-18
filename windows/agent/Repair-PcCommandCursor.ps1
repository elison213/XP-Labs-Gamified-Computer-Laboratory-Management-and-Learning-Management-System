# Resets agent command cursor so stranded pending remote_commands are delivered again.
# Run as Administrator on the lab PC when website lock/unlock/message commands stop working.
param(
  [switch] $ResetToZero
)

$ErrorActionPreference = 'Stop'
$agentDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Import-Module (Join-Path $agentDir 'XplabsAgent.psm1') -Force

$state = Get-AgentState
$old = 0
try { if ($null -ne $state.last_command_cursor) { $old = [int64]$state.last_command_cursor } } catch {}

if ($ResetToZero) {
  $state.last_command_cursor = 0
} else {
  $state.last_command_cursor = [Math]::Max(0, $old - 1)
}

Set-AgentState -State $state
Write-Host "command cursor: $old -> $($state.last_command_cursor)" -ForegroundColor Green
Write-Host "Restarting agent loop..."
schtasks /End /TN XPLabsAgentLoop 2>$null | Out-Null
Start-Sleep -Seconds 1
schtasks /Run /TN XPLabsAgentLoop | Out-Null
Write-Host "Done. Try lock/unlock from the website again."
