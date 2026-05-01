param(
  [switch] $Once
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module (Join-Path $PSScriptRoot 'XplabsAgent.psm1') -Force

Initialize-XplabsAgentDirs
Invoke-XplabsDebugRetention

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
  $res = $null
  try {
    $res = Invoke-XplabsApi -Method 'POST' -Path '/api/pc/register' -Body $body
  } catch {
    Write-XplabsLog -Level warn -Message "Pretty route registration failed, trying .php route: $($_.Exception.Message)"
  }

  if ($null -eq $res -or -not ($res.PSObject.Properties.Name -contains 'machine_key')) {
    $res = Invoke-XplabsApi -Method 'POST' -Path '/api/pc/register.php' -Body $body
  }

  if (-not ($res.PSObject.Properties.Name -contains 'machine_key') -or [string]::IsNullOrWhiteSpace([string]$res.machine_key)) {
    $payload = $res | ConvertTo-Json -Depth 8 -Compress
    throw "Registration did not return machine_key. Response: $payload"
  }
  Set-XplabsMachineKey -MachineKey ([string]$res.machine_key)
  Write-XplabsLog -Level info -Message "Registered. pc_id=$($res.pc_id)"
  return [string]$res.machine_key
}

function Send-Heartbeat {
  param([Parameter(Mandatory)] [string] $MachineKey)
  $state = Get-AgentState
  $status = if ($state.locked) { 'locked' } else { 'online' }
  $heartbeatId = [guid]::NewGuid().ToString('N')
  $cursor = 0
  try {
    if ($null -ne $state.last_command_cursor) { $cursor = [int64]$state.last_command_cursor }
  } catch {}

  $body = @{
    heartbeat_id = $heartbeatId
    command_cursor = $cursor
    status = $status
    active_users = @()
    system_info = (Get-SystemInfo)
  }

  $hb = Invoke-XplabsApiWithRetry -Method 'POST' -Path '/api/pc/heartbeat' -Body $body -MachineKey $MachineKey -MaxAttempts 3
  if ($hb -and ($hb.PSObject.Properties.Name -contains 'server_time')) {
    $state.last_server_time = $hb.server_time
  }
  if ($hb -and ($hb.PSObject.Properties.Name -contains 'ack_id')) {
    $state.last_ack_id = [string]$hb.ack_id
  }
  if ($hb -and ($hb.PSObject.Properties.Name -contains 'command_cursor')) {
    try { $state.last_command_cursor = [int64]$hb.command_cursor } catch {}
  }
  $state.last_success_at = (Get-Date).ToString('s')
  $state.consecutive_failures = 0
  $state.next_retry_hint = $null
  if ($hb -and ($hb.PSObject.Properties.Name -contains 'active_session') -and $hb.active_session -and ($hb.active_session.PSObject.Properties.Name -contains 'lrn')) {
    $state.last_lrn = [string] $hb.active_session.lrn
  }
  Set-AgentState -State $state
  return $hb
}

function Send-HeartbeatPayload {
  param(
    [Parameter(Mandatory)] [string] $MachineKey,
    [Parameter(Mandatory)] [hashtable] $Body
  )
  return Invoke-XplabsApiWithRetry -Method 'POST' -Path '/api/pc/heartbeat' -Body $Body -MachineKey $MachineKey -MaxAttempts 3
}

function Queue-HeartbeatForRetry {
  param([Parameter(Mandatory)] [hashtable] $Payload)
  $path = Queue-XplabsHeartbeatPayload -Payload $Payload
  Write-XplabsLog -Level warn -Message "checkin_state=offline_buffering queued=$path"
  Write-XplabsDebugEvent -EventType 'spool_enqueue' -Severity 'warn' -Data @{
    path = $path
    heartbeat_id = [string]($Payload.heartbeat_id)
    command_cursor = [string]($Payload.command_cursor)
  } -MinLevel 'verbose'
}

function Build-HeartbeatPayloadFromState {
  $state = Get-AgentState
  $status = if ($state.locked) { 'locked' } else { 'online' }
  $cursor = 0
  try {
    if ($null -ne $state.last_command_cursor) { $cursor = [int64]$state.last_command_cursor }
  } catch {}
  $proto = Get-XplabsProtocolVersion
  if ($proto -eq 'v1') {
    return @{
      status = $status
      active_users = @()
      system_info = (Get-SystemInfo)
      protocol_version = 'v1'
    }
  }
  return @{
    heartbeat_id = [guid]::NewGuid().ToString('N')
    command_cursor = $cursor
    status = $status
    active_users = @()
    system_info = (Get-SystemInfo)
    protocol_version = 'v2'
  }
}

function Drain-QueuedHeartbeats {
  param([Parameter(Mandatory)] [string] $MachineKey)
  $queued = Get-XplabsQueuedHeartbeats
  foreach ($item in $queued) {
    $payload = Read-XplabsQueuedHeartbeat -Path $item.FullName
    if (-not $payload) {
      Remove-XplabsQueuedHeartbeat -Path $item.FullName
      continue
    }
    try {
      $res = Send-HeartbeatPayload -MachineKey $MachineKey -Body ([hashtable]$payload)
      Write-XplabsDebugEvent -EventType 'spool_drain' -Severity 'info' -Data @{
        file = $item.Name
        heartbeat_id = [string]($payload.heartbeat_id)
        ack_id = [string]($res.ack_id)
        command_cursor = [string]($res.command_cursor)
      } -MinLevel 'verbose'
      if ($res -and ($res.PSObject.Properties.Name -contains 'commands')) {
        foreach ($c in @($res.commands)) { Process-Command -MachineKey $MachineKey -Command $c }
      }
      Remove-XplabsQueuedHeartbeat -Path $item.FullName
    } catch {
      Write-XplabsLog -Level warn -Message "checkin_state=degraded spool_send_failed file=$($item.Name) error=$($_.Exception.Message)"
      Write-XplabsDebugEvent -EventType 'spool_drain' -Severity 'warn' -Data @{
        file = $item.Name
        error = $_.Exception.Message
      } -MinLevel 'verbose'
      break
    }
  }
}

function Sync-ServerConfig {
  param(
    [Parameter(Mandatory)] [string] $MachineKey,
    [Parameter(Mandatory)] [object] $Cfg
  )
  try {
    $res = Invoke-XplabsApi -Method 'GET' -Path '/api/pc/config' -MachineKey $MachineKey
    if (-not $res -or -not ($res.PSObject.Properties.Name -contains 'success') -or (-not $res.success)) {
      return $Cfg
    }

    $remoteCfg = $res.config
    if ($null -ne $remoteCfg) {
      foreach ($name in @('heartbeat_interval_seconds','command_poll_interval_seconds','validate_interval_seconds','validate_grace_minutes')) {
        if ($remoteCfg.PSObject.Properties.Name -contains $name) {
          try {
            $v = [int] $remoteCfg.$name
            if ($v -gt 0) { $Cfg.$name = $v }
          } catch {}
        }
      }
    }

    $pc = $res.pc
    if ($pc -and ($pc.PSObject.Properties.Name -contains 'assignment_status')) {
      $assignment = [string]$pc.assignment_status
      $state = Get-AgentState
      if ($assignment -eq 'unassigned' -and -not $state.locked) {
        # Keep unassigned PCs locked until admin assigns station/floor.
        $state.locked = $true
        Set-AgentState -State $state
        Write-XplabsLog -Level info -Message "Auto-locked: PC is currently unassigned on server"
      }
    }
  } catch {
    Write-XplabsLog -Level warn -Message "Config sync failed: $($_.Exception.Message)"
  }
  return $Cfg
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
        Invoke-XplabsAccessCleanup
        $resultText = 'Locked by command'
      }
      'unlock' {
        $params = $Command.params
        if ($params -and $params.lrn) { $state.last_lrn = [string]$params.lrn }
        $state.locked = $false
        $state.last_unlock_at = (Get-Date).ToString('s')
        $userKey = ''
        if ($null -ne $state.last_lrn) { $userKey = [string]$state.last_lrn }
        Invoke-XplabsAccessApply -MachineKey $MachineKey -Role 'student' -Username $userKey -LabName ''
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
    Write-XplabsDebugEvent -EventType 'command_ack' -Severity 'info' -Data @{
      command_id = $cmdId
      command_type = [string]$type
      status = [string]$status
    } -MinLevel 'verbose'
  } catch {
    Write-XplabsLog -Level warn -Message "Ack failed for command_id=${cmdId}: $($_.Exception.Message)"
    Write-XplabsDebugEvent -EventType 'command_ack' -Severity 'warn' -Data @{
      command_id = $cmdId
      command_type = [string]$type
      status = [string]$status
      error = $_.Exception.Message
    } -MinLevel 'verbose'
  }

  Write-XplabsLog -Level info -Message "Command processed id=$cmdId type=$type status=$status result=$resultText"
}

function Check-LockscreenReadiness {
  $exePath = Join-Path $env:ProgramFiles 'XPLabsAgent\LockScreen\XPLabs.LockScreen.exe'
  if (-not (Test-Path $exePath)) {
    Write-XplabsLog -Level warn -Message "Lockscreen executable missing: $exePath"
    Write-XplabsDebugEvent -EventType 'ui_selfheal' -Severity 'warn' -Data @{ component = 'lockscreen'; code = 'exe_missing'; path = $exePath } -MinLevel 'normal'
    return
  }
  try {
    $task = Get-ScheduledTask -TaskName 'XPLabsLockScreen' -ErrorAction SilentlyContinue
    if (-not $task) {
      Write-XplabsLog -Level warn -Message "Lockscreen scheduled task XPLabsLockScreen not found"
      Write-XplabsDebugEvent -EventType 'ui_selfheal' -Severity 'warn' -Data @{ component = 'lockscreen'; code = 'task_missing'; task = 'XPLabsLockScreen' } -MinLevel 'normal'
      return
    }
    $state = Get-AgentState
    if ($state.locked) {
      try {
        Start-ScheduledTask -TaskName 'XPLabsLockScreen' -ErrorAction SilentlyContinue
        Write-XplabsDebugEvent -EventType 'ui_selfheal' -Severity 'info' -Data @{ component = 'lockscreen'; code = 'task_started'; task = 'XPLabsLockScreen' } -MinLevel 'normal'
      } catch {}
    }
    Write-XplabsLog -Level info -Message "Lockscreen ready: exe+task detected"
  } catch {
    Write-XplabsLog -Level warn -Message "Lockscreen readiness check failed: $($_.Exception.Message)"
    Write-XplabsDebugEvent -EventType 'ui_selfheal' -Severity 'warn' -Data @{ component = 'lockscreen'; code = 'check_failed'; error = $_.Exception.Message } -MinLevel 'normal'
  }
}

function Check-WidgetReadiness {
  $exePath = Join-Path $env:ProgramFiles 'XPLabsAgent\Widget\XPLabs.Widget.exe'
  if (-not (Test-Path $exePath)) {
    Write-XplabsLog -Level warn -Message "Widget executable missing: $exePath"
    Write-XplabsDebugEvent -EventType 'ui_selfheal' -Severity 'warn' -Data @{ component = 'widget'; code = 'exe_missing'; path = $exePath } -MinLevel 'normal'
    return
  }
  try {
    $task = Get-ScheduledTask -TaskName 'XPLabsWidget' -ErrorAction SilentlyContinue
    if (-not $task) {
      Write-XplabsLog -Level warn -Message "Widget scheduled task XPLabsWidget not found"
      Write-XplabsDebugEvent -EventType 'ui_selfheal' -Severity 'warn' -Data @{ component = 'widget'; code = 'task_missing'; task = 'XPLabsWidget' } -MinLevel 'normal'
      return
    }
    try {
      Start-ScheduledTask -TaskName 'XPLabsWidget' -ErrorAction SilentlyContinue
      Write-XplabsDebugEvent -EventType 'ui_selfheal' -Severity 'info' -Data @{ component = 'widget'; code = 'task_started'; task = 'XPLabsWidget' } -MinLevel 'normal'
    } catch {}
    Write-XplabsLog -Level info -Message "Widget ready: exe+task detected"
  } catch {
    Write-XplabsLog -Level warn -Message "Widget readiness check failed: $($_.Exception.Message)"
    Write-XplabsDebugEvent -EventType 'ui_selfheal' -Severity 'warn' -Data @{ component = 'widget'; code = 'check_failed'; error = $_.Exception.Message } -MinLevel 'normal'
  }
}

function Poll-Commands {
  param([Parameter(Mandatory)] [string] $MachineKey)
  $state = Get-AgentState
  $cursor = 0
  try {
    if ($null -ne $state.last_command_cursor) { $cursor = [int64]$state.last_command_cursor }
  } catch {}
  $res = Invoke-XplabsApiWithRetry -Method 'GET' -Path "/api/pc/commands?after_cursor=$cursor" -MachineKey $MachineKey -MaxAttempts 2
  Write-XplabsDebugEvent -EventType 'command_poll' -Severity 'info' -Data @{
    request_cursor = $cursor
    next_cursor = [string]($res.next_cursor)
    count = [string](@($res.commands).Count)
  } -MinLevel 'trace'
  if ($res -and ($res.PSObject.Properties.Name -contains 'next_cursor')) {
    try {
      $state.last_command_cursor = [int64]$res.next_cursor
      Set-AgentState -State $state
    } catch {}
  }
  if ($res -and ($res.PSObject.Properties.Name -contains 'commands') -and $null -ne $res.commands) { return @($res.commands) }
  return @()
}

function Process-OverrideUnlockRequest {
  param([Parameter(Mandatory)] [string] $MachineKey)
  $req = Get-OverrideRequest
  if (-not $req) { return }

  $identifier = [string]$req.identifier
  $password = [string]$req.password
  if ([string]::IsNullOrWhiteSpace($identifier) -or [string]::IsNullOrWhiteSpace($password)) {
    Write-XplabsLog -Level warn -Message "Ignoring malformed override request"
    Clear-OverrideRequest
    return
  }

  try {
    $res = Invoke-XplabsApi -Method 'POST' -Path '/api/session/override-unlock' -MachineKey $MachineKey -Body @{
      identifier = $identifier
      password   = $password
    }
    Write-XplabsLog -Level info -Message "Override unlock accepted: command_id=$($res.command_id)"
  } catch {
    Write-XplabsLog -Level warn -Message "Override unlock failed: $($_.Exception.Message)"
  } finally {
    Clear-OverrideRequest
  }
}

function Process-LocalLockRequest {
  $req = Get-LockRequest
  if (-not $req) { return }

  try {
    $state = Get-AgentState
    if (-not $state.locked) {
      $state.locked = $true
      Set-AgentState -State $state
      Invoke-XplabsAccessCleanup
      Write-XplabsLog -Level info -Message "Local lock request applied (widget exit)"
    } else {
      Write-XplabsLog -Level info -Message "Local lock request ignored (already locked)"
    }
  } catch {
    Write-XplabsLog -Level warn -Message "Local lock request failed: $($_.Exception.Message)"
  } finally {
    Clear-LockRequest
  }
}

$cfg = Get-XplabsConfig
$machineKey = Ensure-Registered
$cfg = Sync-ServerConfig -MachineKey $machineKey -Cfg $cfg
Check-LockscreenReadiness

# Default to locked until an unlock arrives
$state = Get-AgentState
if ($null -eq $state.locked) { $state.locked = $true }
Set-AgentState -State $state
if ($state.locked) {
  Invoke-XplabsAccessCleanup
}

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
$lastCfgSync = [datetime]::MinValue
$lastUiCheck = [datetime]::MinValue
$consecutiveHeartbeatFailures = 0

while ($true) {
  $now = Get-Date

  if (($now - $lastHb).TotalSeconds -ge $hbEvery) {
    $payload = $null
    try {
      Drain-QueuedHeartbeats -MachineKey $machineKey
      $payload = Build-HeartbeatPayloadFromState
      Write-XplabsDebugEvent -EventType 'checkin_attempt' -Severity 'info' -Data @{
        heartbeat_id = [string]($payload.heartbeat_id)
        command_cursor = [string]($payload.command_cursor)
        protocol_version = [string](Get-XplabsProtocolVersion)
      } -MinLevel 'normal'
      $hb = Send-HeartbeatPayload -MachineKey $machineKey -Body $payload
      if ($hb -and ($hb.PSObject.Properties.Name -contains 'commands')) {
        foreach ($c in @($hb.commands)) { Process-Command -MachineKey $machineKey -Command $c }
      }
      $state = Get-AgentState
      if ($hb -and ($hb.PSObject.Properties.Name -contains 'server_time')) {
        $state.last_server_time = $hb.server_time
      }
      if ($hb -and ($hb.PSObject.Properties.Name -contains 'ack_id')) {
        $state.last_ack_id = [string]$hb.ack_id
      }
      if ($hb -and ($hb.PSObject.Properties.Name -contains 'command_cursor')) {
        try { $state.last_command_cursor = [int64]$hb.command_cursor } catch {}
      }
      if ($hb -and ($hb.PSObject.Properties.Name -contains 'active_session') -and $hb.active_session -and ($hb.active_session.PSObject.Properties.Name -contains 'lrn')) {
        $state.last_lrn = [string] $hb.active_session.lrn
      }
      $state.last_success_at = (Get-Date).ToString('s')
      $state.consecutive_failures = 0
      $state.next_retry_hint = $null
      Set-AgentState -State $state
      $consecutiveHeartbeatFailures = 0
      Write-XplabsLog -Level info -Message "checkin_state=online ack_id=$($state.last_ack_id) cursor=$($state.last_command_cursor)"
      Write-XplabsDebugEvent -EventType 'checkin_result' -Severity 'info' -Data @{
        heartbeat_id = [string]($payload.heartbeat_id)
        ack_id = [string]($state.last_ack_id)
        command_cursor = [string]($state.last_command_cursor)
        duplicate = [string]($hb.duplicate)
      } -MinLevel 'normal'
    } catch {
      $consecutiveHeartbeatFailures++
      $msg = $_.Exception.Message
      $retryDelay = [math]::Round((Get-XplabsRetryDelaySeconds -Attempt $consecutiveHeartbeatFailures -BaseDelaySeconds 2 -MaxDelaySeconds 90), 2)
      $state = Get-AgentState
      $state.consecutive_failures = $consecutiveHeartbeatFailures
      $state.next_retry_hint = (Get-Date).AddSeconds([double]$retryDelay).ToString('s')
      Set-AgentState -State $state
      if ($null -ne $payload) {
        Queue-HeartbeatForRetry -Payload $payload
      }
      $severity = if ($consecutiveHeartbeatFailures -ge 3) { 'error' } else { 'warn' }
      Write-XplabsLog -Level $severity -Message "checkin_state=degraded failures=$consecutiveHeartbeatFailures retry_sec=$retryDelay error=$msg"
      Write-XplabsDebugEvent -EventType 'checkin_backoff' -Severity $severity -Data @{
        heartbeat_id = [string]($payload.heartbeat_id)
        attempt = $consecutiveHeartbeatFailures
        delay_ms = [int]([Math]::Ceiling($retryDelay * 1000))
        error = $msg
        error_class = if (Test-XplabsRetryableError -Message $msg) { 'retryable' } else { 'fatal' }
      } -MinLevel 'normal'
      Start-Sleep -Milliseconds ([int]([Math]::Ceiling($retryDelay * 1000)))
    }
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

  Process-OverrideUnlockRequest -MachineKey $machineKey
  Process-LocalLockRequest

  if (($now - $lastCfgSync).TotalSeconds -ge 60) {
    $cfg = Sync-ServerConfig -MachineKey $machineKey -Cfg $cfg
    $hbEvery = Get-CfgInt $cfg 'heartbeat_interval_seconds' 30
    $pollEvery = Get-CfgInt $cfg 'command_poll_interval_seconds' 5
    $validateEvery = Get-CfgInt $cfg 'validate_interval_seconds' 10
    $lastCfgSync = $now
  }

  if (($now - $lastUiCheck).TotalSeconds -ge 120) {
    Check-LockscreenReadiness
    Check-WidgetReadiness
    $lastUiCheck = $now
  }

  if (($now - $lastVal).TotalSeconds -ge $validateEvery) {
    $val = Validate-Access -MachineKey $machineKey -Cfg $cfg
    if ($val -and $val.action -eq 'lock_screen') {
      $s = Get-AgentState
      if (-not $s.locked) {
        $s.locked = $true
        Set-AgentState -State $s
        Invoke-XplabsAccessCleanup
        Write-XplabsLog -Level info -Message "Auto-locked: server validate returned lock_screen"
      }
    }
    $lastVal = $now
  }

  if ($Once) { break }
  Start-Sleep -Seconds 1
}

