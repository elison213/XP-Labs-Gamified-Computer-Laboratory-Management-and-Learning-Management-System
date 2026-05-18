# Reset agent command cursor when dashboard lock/unlock stops working after a server queue reset.
param([switch] $ResetToZero)

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
schtasks /End /TN XPLabsAgentLoop 2>$null | Out-Null
Start-Sleep -Seconds 1
schtasks /Run /TN XPLabsAgentLoop | Out-Null
Write-Host "Agent loop restarted. Try lock from dashboard again."
