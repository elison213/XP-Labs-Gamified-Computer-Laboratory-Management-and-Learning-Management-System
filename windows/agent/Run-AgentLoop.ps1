param(
  [switch] $Once
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module (Join-Path $PSScriptRoot 'XplabsAgent.psm1') -Force

Initialize-XplabsAgentDirs

function Ensure-Registered {
  $cfg = Get-XplabsConfig
  $key = Get-XplabsMachineKey
  if ($key) { return $key }

  $id = Get-HostIdentity
  $body = @{
    hostname    = $id.hostname
    ip_address  = $id.ip_address
    mac_address = $id.mac_address
    floor_id    = $cfg.floor_id
    station_id  = $cfg.station_id
  }

  Write-XplabsLog -Level info -Message "Registering PC hostname=$($id.hostname) floor_id=$($cfg.floor_id) station_id=$($cfg.station_id)"
  $res = Invoke-XplabsApi -Method 'POST' -Path '/api/pc/register' -Body $body
  if (-not $res.machine_key) { throw "Registration did not return machine_key" }
  Set-XplabsMachineKey -MachineKey $res.machine_key
  Write-XplabsLog -Level info -Message "Registered. pc_id=$($res.pc_id)"
  return $res.machine_key
}

function Send-Heartbeat {
  param([Parameter(Mandatory)] [string] $MachineKey)
  $state = Get-AgentState
  $status = if ($state.locked) { 'locked' } else { 'online' }

  $body = @{
    status = $status
    active_users = @()
    system_info = (Get-SystemInfo)
  }

  $hb = Invoke-XplabsApi -Method 'POST' -Path '/api/pc/heartbeat' -Body $body -MachineKey $MachineKey
  $state.last_server_time = $hb.server_time
  Set-AgentState -State $state
  return $hb
}

function Validate-Access {
  param(
    [Parameter(Mandatory)] [string] $MachineKey,
    [Parameter(Mandatory)] $Cfg
  )
  $state = Get-AgentState
  if (-not $state.last_lrn) { return $null }

  $grace = 5
  try {
    if ($null -ne $Cfg.validate_grace_minutes) { $grace = [int]$Cfg.validate_grace_minutes }
  } catch {}
  $lrn = [uri]::EscapeDataString([string]$state.last_lrn)
  $path = "/api/session/validate?lrn=$lrn&grace_minutes=$grace"
  try {
    $res = Invoke-XplabsApi -Method 'GET' -Path $path -MachineKey $MachineKey
    $state.last_validate_at = (Get-Date).ToString('s')
    Set-AgentState -State $state
    return $res
  } catch {
    Write-XplabsLog -Level warn -Message "Validate failed: $($_.Exception.Message)"
    return $null
  }
}

function Process-Command {
  param(
    [Parameter(Mandatory)] [string] $MachineKey,
    [Parameter(Mandatory)] $Command
  )
  $type = $Command.type
  $cmdId = [int]$Command.id

  $state = Get-AgentState
  $resultText = $null
  $status = 'executed'

  try {
    switch ($type) {
      'lock' {
        $state.locked = $true
        $resultText = 'Locked by command'
      }
      'unlock' {
        $params = $Command.params
        if ($params -and $params.lrn) { $state.last_lrn = [string]$params.lrn }
        $state.locked = $false
        $state.last_unlock_at = (Get-Date).ToString('s')
        $resultText = "Unlocked for LRN=$($state.last_lrn)"
      }
      'message' {
        $msg = $Command.params.message
        $resultText = "Message: $msg"
      }
      'restart' {
        $resultText = 'Restarting'
        Restart-Computer -Force
      }
      'shutdown' {
        $resultText = 'Shutting down'
        Stop-Computer -Force
      }
      default {
        $status = 'failed'
        $resultText = "Unsupported command type: $type"
      }
    }
  } catch {
    $status = 'failed'
    $resultText = $_.Exception.Message
  } finally {
    Set-AgentState -State $state
  }

  try {
    Invoke-XplabsApi -Method 'POST' -Path '/api/pc/commands' -Body @{
      command_id = $cmdId
      status     = $status
      result     = $resultText
    } -MachineKey $MachineKey | Out-Null
  } catch {
    Write-XplabsLog -Level warn -Message "Ack failed for command_id=$cmdId: $($_.Exception.Message)"
  }

  Write-XplabsLog -Level info -Message "Command processed id=$cmdId type=$type status=$status result=$resultText"
}

function Poll-Commands {
  param([Parameter(Mandatory)] [string] $MachineKey)
  $res = Invoke-XplabsApi -Method 'GET' -Path '/api/pc/commands' -MachineKey $MachineKey
  if ($res.commands) { return $res.commands }
  return @()
}

$cfg = Get-XplabsConfig
$machineKey = Ensure-Registered

# Default to locked until an unlock arrives
$state = Get-AgentState
if ($null -eq $state.locked) { $state.locked = $true }
Set-AgentState -State $state

function Get-CfgInt([object]$Obj, [string]$Name, [int]$Default) {
  try {
    $v = $Obj.$Name
    if ($null -eq $v) { return $Default }
    return [int]$v
  } catch { return $Default }
}

$hbEvery = Get-CfgInt $cfg 'heartbeat_interval_seconds' 30
$pollEvery = Get-CfgInt $cfg 'command_poll_interval_seconds' 5
$validateEvery = Get-CfgInt $cfg 'validate_interval_seconds' 10

$lastHb = [datetime]::MinValue
$lastPoll = [datetime]::MinValue
$lastVal = [datetime]::MinValue

while ($true) {
  $now = Get-Date

  if (($now - $lastHb).TotalSeconds -ge $hbEvery) {
    try { Send-Heartbeat -MachineKey $machineKey | Out-Null } catch { Write-XplabsLog -Level warn -Message "Heartbeat failed: $($_.Exception.Message)" }
    $lastHb = $now
  }

  if (($now - $lastPoll).TotalSeconds -ge $pollEvery) {
    try {
      $cmds = Poll-Commands -MachineKey $machineKey
      foreach ($c in $cmds) { Process-Command -MachineKey $machineKey -Command $c }
    } catch {
      Write-XplabsLog -Level warn -Message "Command poll failed: $($_.Exception.Message)"
    }
    $lastPoll = $now
  }

  if (($now - $lastVal).TotalSeconds -ge $validateEvery) {
    $val = Validate-Access -MachineKey $machineKey -Cfg $cfg
    if ($val -and $val.action -eq 'lock_screen') {
      $s = Get-AgentState
      if (-not $s.locked) {
        $s.locked = $true
        Set-AgentState -State $s
        Write-XplabsLog -Level info -Message "Auto-locked: server validate returned lock_screen"
      }
    }
    $lastVal = $now
  }

  if ($Once) { break }
  Start-Sleep -Seconds 1
}

