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

  $hb = Invoke-XplabsApiWithRetry -Method 'POST' -Path '/api/pc/heartbeat.php' -Body $body -MachineKey $MachineKey -MaxAttempts 3
  if ($hb -and ($hb.PSObject.Properties.Name -contains 'server_time')) {
    $state.last_server_time = $hb.server_time
  }
  if ($hb -and ($hb.PSObject.Properties.Name -contains 'ack_id')) {
    $state.last_ack_id = [string]$hb.ack_id
  }
  # Never advance local cursor from heartbeat alone — only after command ack (MeshCentral-style).
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
  return Invoke-XplabsApiWithRetry -Method 'POST' -Path '/api/pc/heartbeat.php' -Body $Body -MachineKey $MachineKey -MaxAttempts 3
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
      $payloadBody = @{}
      foreach ($prop in $payload.PSObject.Properties) {
        $payloadBody[$prop.Name] = $prop.Value
      }
      $liveStatus = if ((Get-AgentState).locked) { 'locked' } else { 'online' }
      $payloadBody['status'] = $liveStatus
      $res = Send-HeartbeatPayload -MachineKey $MachineKey -Body $payloadBody
      Write-XplabsDebugEvent -EventType 'spool_drain' -Severity 'info' -Data @{
        file = $item.Name
        heartbeat_id = [string]($payload.heartbeat_id)
        ack_id = [string]($res.ack_id)
        command_cursor = [string]($res.command_cursor)
      } -MinLevel 'verbose'
      if ($res -and ($res.PSObject.Properties.Name -contains 'commands')) {
        Add-CommandsToTickBatch -Commands $res.commands
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
      $overrideGraceActive = $false
      if ($state -and ($state.PSObject.Properties.Name -contains 'override_unlock_until')) {
        $untilRaw = [string]$state.override_unlock_until
        if (-not [string]::IsNullOrWhiteSpace($untilRaw)) {
          try {
            $until = [datetime]::Parse($untilRaw)
            if ($until -gt (Get-Date)) { $overrideGraceActive = $true }
          } catch {}
        }
      }
      if ($assignment -eq 'unassigned' -and -not $state.locked) {
        if ($overrideGraceActive) {
          Write-XplabsLog -Level info -Message "Override grace active: skipping unassigned auto-lock"
        } else {
          # Keep unassigned PCs locked until admin assigns station/floor.
          $state.locked = $true
          Set-AgentState -State $state
          Start-XplabsLockscreen | Out-Null
          Write-XplabsLog -Level info -Message "Auto-locked: PC is currently unassigned on server"
        }
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
  $type = [string]$Command.type
  $cmdId = [int]$Command.id
  if ($cmdId -le 0) { return }

  $cursor = Get-CommandCursor
  if ($cmdId -le $cursor) {
    Write-XplabsLog -Level info -Message "Command id=$cmdId at/below cursor=$cursor; running idempotent handler (orphan/stale delivery)"
  }
  if ($script:InFlightCommandIds.ContainsKey($cmdId)) {
    Write-XplabsLog -Level info -Message "Command id=$cmdId already in flight; skip duplicate"
    return
  }
  $script:InFlightCommandIds[$cmdId] = $true

  $state = Get-AgentState
  $resultText = $null
  $status = 'executed'
  $script:_startLockscreenAfterCommand = $false
  $script:_startWidgetAfterCommand = $false
  $script:_syncServerStatus = $null

  try {
    switch ($type) {
      'lock' {
        $state.locked = $true
        if ($state.PSObject.Properties.Name -contains 'override_unlock_until') {
          $state.override_unlock_until = ''
        }
        Invoke-XplabsAccessCleanup
        $visible = Test-XplabsLockscreenVisibleInUserSession
        $resultText = if ($visible) { 'Locked (lockscreen already visible)' } else { 'Locked by command' }
        if (-not $visible) {
          $script:_startLockscreenAfterCommand = $true
        }
        $script:_syncServerStatus = 'locked'
      }
      'unlock' {
        $params = $Command.params
        if ($params -and $params.lrn) { $state.last_lrn = [string]$params.lrn }
        $lockscreenUp = Test-XplabsLockscreenVisibleInUserSession
        $widgetUp = Test-XplabsWidgetVisibleInUserSession
        $state.locked = $false
        $state.last_unlock_at = (Get-Date).ToString('s')
        Clear-XplabsShowLockscreenDemand
        if ($lockscreenUp) { Stop-XplabsUserLockscreen }
        $script:_syncServerStatus = 'online'
        if ($state.PSObject.Properties.Name -contains 'override_unlock_until') {
          $state.override_unlock_until = (Get-Date).AddMinutes(15).ToString('s')
        }
        $userKey = ''
        if ($null -ne $state.last_lrn) { $userKey = [string]$state.last_lrn }
        Invoke-XplabsAccessApply -MachineKey $MachineKey -Role 'student' -Username $userKey -LabName ''
        $resultText = if (-not $lockscreenUp -and $widgetUp) {
          'Already unlocked'
        } else {
          "Unlocked for LRN=$($state.last_lrn)"
        }
        if (-not $widgetUp) { $script:_startWidgetAfterCommand = $true }
      }
      'message' {
        $msg = ''
        if ($null -ne $Command.params) {
          if ($Command.params.PSObject.Properties.Name -contains 'message') {
            $msg = [string]$Command.params.message
          }
        }
        $threadId = 0
        if ($null -ne $Command.params -and ($Command.params.PSObject.Properties.Name -contains 'thread_id')) {
          try { $threadId = [int]$Command.params.thread_id } catch { $threadId = 0 }
        }
        if ([string]::IsNullOrWhiteSpace($msg)) { $msg = '(no text)' }
        try {
          Add-PendingMessage -CommandId $cmdId -Message $msg -ThreadId $threadId
          $resultText = 'Message queued for widget UI'
          Start-XplabsWidget | Out-Null
        } catch {
          $resultText = "Message queue failed: $($_.Exception.Message)"
          Write-XplabsLog -Level warn -Message $resultText
        }
      }
      'restart' {
        $resultText = 'Restarting'
        Set-AgentState -State $state
        if (Send-CommandAck -MachineKey $MachineKey -CommandId $cmdId -Status 'executed' -ResultText $resultText) {
          $script:InFlightCommandIds.Remove($cmdId) | Out-Null
        }
        Restart-Computer -Force
        return
      }
      'shutdown' {
        $resultText = 'Shutting down'
        Set-AgentState -State $state
        if (Send-CommandAck -MachineKey $MachineKey -CommandId $cmdId -Status 'executed' -ResultText $resultText) {
          $script:InFlightCommandIds.Remove($cmdId) | Out-Null
        }
        Stop-Computer -Force
        return
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

  if ($script:_startLockscreenAfterCommand) {
    $script:_startLockscreenAfterCommand = $false
    $shown = Start-XplabsLockscreen
    if (-not $shown) {
      Write-XplabsLog -Level warn -Message "Lock command applied but lockscreen UI not visible yet (self-heal will retry)"
    }
  }

  if ($script:_startWidgetAfterCommand) {
    $script:_startWidgetAfterCommand = $false
    Start-XplabsWidget | Out-Null
  }

  if ($script:_syncServerStatus) {
    Send-XplabsStatusHeartbeat -Status $script:_syncServerStatus -MachineKey $MachineKey | Out-Null
    $script:_syncServerStatus = $null
  }

  if (Send-CommandAck -MachineKey $MachineKey -CommandId $cmdId -Status $status -ResultText ([string]$resultText)) {
    Write-XplabsDebugEvent -EventType 'command_ack' -Severity 'info' -Data @{
      command_id = $cmdId
      command_type = [string]$type
      status = [string]$status
    } -MinLevel 'verbose'
  } else {
    Write-XplabsDebugEvent -EventType 'command_ack' -Severity 'warn' -Data @{
      command_id = $cmdId
      command_type = [string]$type
      status = [string]$status
    } -MinLevel 'verbose'
  }

  $script:InFlightCommandIds.Remove($cmdId) | Out-Null
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
    if (-not (Test-XplabsScheduledTaskExists -TaskName 'XPLabsLockScreen')) {
      Write-XplabsLog -Level warn -Message "Lockscreen scheduled task XPLabsLockScreen not found"
      Write-XplabsDebugEvent -EventType 'ui_selfheal' -Severity 'warn' -Data @{ component = 'lockscreen'; code = 'task_missing'; task = 'XPLabsLockScreen' } -MinLevel 'normal'
      return
    }
    $state = Get-AgentState
    if ($state.locked) {
      if (Test-XplabsStudentLoginGraceActive) { return }
      Stop-XplabsSessionZeroUi
      $explorer = Get-Process -Name 'explorer' -ErrorAction SilentlyContinue | Where-Object { $_.SessionId -gt 0 }
      $visible = Get-Process -Name 'XPLabs.LockScreen' -ErrorAction SilentlyContinue | Where-Object { $_.SessionId -gt 0 }
      if ($explorer -and -not $visible) {
        $started = Start-XplabsLockscreen
        if ($started) {
          Write-XplabsLog -Level info -Message 'Lockscreen self-heal: started in user session'
          Write-XplabsDebugEvent -EventType 'ui_selfheal' -Severity 'info' -Data @{ component = 'lockscreen'; code = 'started_visible' } -MinLevel 'normal'
        } else {
          Write-XplabsLog -Level warn -Message 'Lockscreen self-heal: state locked but UI not visible (retrying)'
          Write-XplabsDebugEvent -EventType 'ui_selfheal' -Severity 'warn' -Data @{ component = 'lockscreen'; code = 'not_visible' } -MinLevel 'normal'
        }
      } elseif ($state.locked -and -not $explorer) {
        Write-XplabsLog -Level warn -Message 'Lockscreen self-heal: locked but no user desktop (explorer not running)'
      } elseif ($state.locked -and $visible) {
        Clear-XplabsShowLockscreenDemand
      }
    }
    Write-XplabsLog -Level info -Message "Lockscreen ready: exe+task detected"
  } catch {
    Write-XplabsLog -Level warn -Message "Lockscreen readiness check failed: $($_.Exception.Message)"
    Write-XplabsDebugEvent -EventType 'ui_selfheal' -Severity 'warn' -Data @{ component = 'lockscreen'; code = 'check_failed'; error = $_.Exception.Message } -MinLevel 'normal'
  }
}

function Test-XplabsStudentLoginGraceActive {
  $state = Get-AgentState
  if ($state -and ($state.PSObject.Properties.Name -contains 'student_login_grace_until')) {
    $until = [string]$state.student_login_grace_until
    if (-not [string]::IsNullOrWhiteSpace($until)) {
      try {
        if ((Get-Date) -lt [datetime]::Parse($until)) { return $true }
      } catch {}
    }
  }
  return $false
}

function Process-DesktopSessionLock {
  param([Parameter(Mandatory)] [string] $MachineKey)

  if (Test-XplabsStudentLoginGraceActive -or (Test-XplabsOverrideGraceActive)) {
    return
  }

  $sid = Get-XplabsExplorerSessionId
  if ($sid -le 0) {
    $script:hadExplorerSession = $false
    $script:lastDesktopSessionId = 0
    return
  }

  $prev = 0
  try { $prev = [int]$script:lastDesktopSessionId } catch { $prev = 0 }

  $freshLogon = $false
  try {
    if (-not $script:hadExplorerSession) { $freshLogon = $true }
  } catch {
    $freshLogon = $true
  }
  $script:hadExplorerSession = $true

  if ($prev -eq $sid -and -not $freshLogon) { return }

  $sessionChanged = ($prev -gt 0 -and $prev -ne $sid)
  $script:lastDesktopSessionId = $sid

  if ($freshLogon -or $sessionChanged) {
    Write-XplabsLog -Level info -Message "Windows sign-in detected (session $sid); applying lab lock"
    Invoke-XplabsApplyDesktopSessionLock -MachineKey $MachineKey
    return
  }

  if ($prev -eq 0) {
    $state = Get-AgentState
    $locked = $true
    try { if ($null -ne $state.locked) { $locked = [bool]$state.locked } } catch {}
    if (-not $locked) { return }
    if (Test-XplabsLockscreenVisibleInUserSession) { return }
    Write-XplabsLog -Level info -Message "Agent start: locked=true but lockscreen UI missing (session $sid)"
    Invoke-XplabsApplyDesktopSessionLock -MachineKey $MachineKey
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
    if (-not (Test-XplabsScheduledTaskExists -TaskName 'XPLabsWidget')) {
      Write-XplabsLog -Level warn -Message "Widget scheduled task XPLabsWidget not found (run Register-XplabsUiTasks.ps1)"
      Write-XplabsDebugEvent -EventType 'ui_selfheal' -Severity 'warn' -Data @{ component = 'widget'; code = 'task_missing'; task = 'XPLabsWidget' } -MinLevel 'normal'
    }
    $state = Get-AgentState
    Stop-XplabsSessionZeroUi
    if (-not $state.locked) {
      if (-not (Test-XplabsWidgetVisibleInUserSession)) {
        $started = Start-XplabsWidget
        if ($started) {
          Write-XplabsLog -Level info -Message 'Widget self-heal: started in user session'
          Write-XplabsDebugEvent -EventType 'ui_selfheal' -Severity 'info' -Data @{ component = 'widget'; code = 'started_visible' } -MinLevel 'normal'
        } else {
          Write-XplabsLog -Level warn -Message 'Widget self-heal: unlocked but widget not visible (retrying)'
          Write-XplabsDebugEvent -EventType 'ui_selfheal' -Severity 'warn' -Data @{ component = 'widget'; code = 'not_visible' } -MinLevel 'normal'
        }
      }
    }
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
  $res = Invoke-XplabsApiWithRetry -Method 'GET' -Path "/api/pc/commands.php?after_cursor=$cursor" -MachineKey $MachineKey -MaxAttempts 2
  $nextCursorLog = ''
  if ($res -and ($res.PSObject.Properties.Name -contains 'next_cursor')) {
    $nextCursorLog = [string]$res.next_cursor
  } else {
    $nextCursorLog = [string]$cursor
  }
  $commandCount = 0
  if ($res -and ($res.PSObject.Properties.Name -contains 'commands') -and $null -ne $res.commands) {
    $commandCount = @($res.commands).Count
  }
  Write-XplabsDebugEvent -EventType 'command_poll' -Severity 'info' -Data @{
    request_cursor = $cursor
    next_cursor = $nextCursorLog
    count = [string]$commandCount
  } -MinLevel 'trace'
  # Do not advance cursor here — only after Process-Command ack succeeds (avoids skipping pending commands).
  if ($res -and ($res.PSObject.Properties.Name -contains 'commands') -and $null -ne $res.commands) { return @($res.commands) }
  return @()
}

function Set-CommandCursorIfHigher {
  param([int] $CommandId)
  if ($CommandId -le 0) { return }
  $state = Get-AgentState
  $cur = 0
  try {
    if ($null -ne $state.last_command_cursor) { $cur = [int64]$state.last_command_cursor }
  } catch {}
  if ($CommandId -gt $cur) {
    $state.last_command_cursor = $CommandId
    Set-AgentState -State $state
  }
}

function Get-CommandCursor {
  $state = Get-AgentState
  $cur = 0
  try {
    if ($null -ne $state.last_command_cursor) { $cur = [int64]$state.last_command_cursor }
  } catch {}
  return $cur
}

function Send-CommandAck {
  param(
    [Parameter(Mandatory)] [string] $MachineKey,
    [Parameter(Mandatory)] [int] $CommandId,
    [Parameter(Mandatory)] [string] $Status,
    [string] $ResultText = ''
  )
  if ($CommandId -le 0) { return $false }
  try {
    Invoke-XplabsApi -Method 'POST' -Path '/api/pc/commands.php' -Body @{
      command_id = $CommandId
      status     = $Status
      result     = $ResultText
    } -MachineKey $MachineKey | Out-Null
    Set-CommandCursorIfHigher -CommandId $CommandId
    return $true
  } catch {
    Write-XplabsLog -Level warn -Message "Ack failed for command_id=${CommandId}: $($_.Exception.Message)"
    return $false
  }
}

$script:CommandsThisTick = [System.Collections.ArrayList]::new()
$script:CommandIdsThisTick = @{}

function Add-CommandsToTickBatch {
  param($Commands)
  if ($null -eq $Commands) { return }
  foreach ($c in @($Commands)) {
    if ($null -eq $c) { continue }
    $id = 0
    try { $id = [int]$c.id } catch { continue }
    if ($id -le 0) { continue }
    if ($script:CommandIdsThisTick.ContainsKey($id)) { continue }
    $script:CommandIdsThisTick[$id] = $true
    [void]$script:CommandsThisTick.Add($c)
  }
}

function Invoke-CommandsThisTick {
  param([Parameter(Mandatory)] [string] $MachineKey)
  if ($script:CommandsThisTick.Count -le 0) { return }
  foreach ($c in @($script:CommandsThisTick.ToArray())) {
    Process-Command -MachineKey $MachineKey -Command $c
  }
  $script:CommandsThisTick.Clear()
  $script:CommandIdsThisTick = @{}
}

$script:InFlightCommandIds = @{}

function Process-AdminHotkeyRequest {
  param([Parameter(Mandatory)] [string] $MachineKey)
  $req = Get-AdminHotkeyRequest
  if (-not $req) { return }

  $state = Get-AgentState
  $state.locked = $false
  $state.last_unlock_at = (Get-Date).ToString('s')
  if (-not ($state.PSObject.Properties.Name -contains 'override_unlock_until')) {
    $state | Add-Member -NotePropertyName override_unlock_until -NotePropertyValue ''
  }
  $state.override_unlock_until = (Get-Date).AddMinutes(30).ToString('s')
  if (-not ($state.PSObject.Properties.Name -contains 'last_override_status')) {
    $state | Add-Member -NotePropertyName last_override_status -NotePropertyValue ''
  }
  if (-not ($state.PSObject.Properties.Name -contains 'last_override_message')) {
    $state | Add-Member -NotePropertyName last_override_message -NotePropertyValue ''
  }
  $state.last_override_status = 'success'
  $state.last_override_message = 'Admin hotkey unlock (Ctrl+Shift+X).'
  Set-AgentState -State $state
  Clear-AdminHotkeyRequest
  Clear-XplabsShowLockscreenDemand
  Stop-XplabsUserLockscreen
  Write-XplabsLog -Level info -Message 'Admin hotkey unlock applied; starting widget'
  Send-XplabsStatusHeartbeat -Status 'online' -MachineKey $MachineKey | Out-Null
  Start-XplabsWidget | Out-Null
}

function Process-StudentLoginRequest {
  param([Parameter(Mandatory)] [string] $MachineKey)
  $req = Get-StudentLoginRequest
  if (-not $req) { return }

  $state = Get-AgentState
  if (-not ($state.PSObject.Properties.Name -contains 'last_student_login_status')) {
    $state | Add-Member -NotePropertyName last_student_login_status -NotePropertyValue ''
  }
  if (-not ($state.PSObject.Properties.Name -contains 'last_student_login_message')) {
    $state | Add-Member -NotePropertyName last_student_login_message -NotePropertyValue ''
  }

  $lrn = [string]$req.lrn
  $password = [string]$req.password
  if ([string]::IsNullOrWhiteSpace($lrn) -or [string]::IsNullOrWhiteSpace($password)) {
    $state.last_student_login_status = 'error'
    $state.last_student_login_message = 'Enter your LRN and password.'
    Set-AgentState -State $state
    Clear-StudentLoginRequest
    return
  }

  $state.last_student_login_status = 'pending'
  $state.last_student_login_message = 'Contacting XPLabs server...'
  Set-AgentState -State $state

  try {
    $res = Invoke-XplabsApi -Method 'POST' -Path '/api/session/pc-student-login' -MachineKey $MachineKey -Body @{
      lrn      = $lrn.Trim()
      password = $password
    }
    $welcome = 'Signed in successfully.'
    if ($res -and ($res.PSObject.Properties.Name -contains 'message') -and -not [string]::IsNullOrWhiteSpace([string]$res.message)) {
      $welcome = [string]$res.message
    }
    $state.last_lrn = $lrn.Trim()
    $state.locked = $false
    $state.last_unlock_at = (Get-Date).ToString('s')
    if ($state.PSObject.Properties.Name -contains 'override_unlock_until') {
      $state.override_unlock_until = (Get-Date).AddHours(4).ToString('s')
    }
    if (-not ($state.PSObject.Properties.Name -contains 'student_login_grace_until')) {
      $state | Add-Member -NotePropertyName student_login_grace_until -NotePropertyValue ''
    }
    $state.student_login_grace_until = (Get-Date).AddMinutes(15).ToString('s')
    Clear-XplabsShowLockscreenDemand
    # Write unlocked state first so lockscreen UI can dismiss (do not kill process before flush).
    Set-AgentState -State $state
    Start-Sleep -Milliseconds 400
    $state.last_student_login_status = 'success'
    $state.last_student_login_message = $welcome
    if ($state.PSObject.Properties.Name -contains 'last_override_status') {
      $state.last_override_status = ''
      $state.last_override_message = ''
    }
    Set-AgentState -State $state
    Start-Sleep -Milliseconds 300
    Stop-XplabsUserLockscreen
    $userKey = $state.last_lrn
    Invoke-XplabsAccessApply -MachineKey $MachineKey -Role 'student' -Username $userKey -LabName ''
    Write-XplabsLog -Level info -Message "Student lockscreen login accepted: lrn=$userKey"
    Send-XplabsStatusHeartbeat -Status 'online' -MachineKey $MachineKey | Out-Null
    Start-XplabsWidget | Out-Null
    Open-XplabsStudentPortal | Out-Null
  } catch {
    $err = [string]$_.Exception.Message
    Write-XplabsLog -Level warn -Message "Student lockscreen login failed: $err"
    $friendly = if ($err -match 'Invalid LRN or password') {
      'Invalid LRN or password.'
    } elseif ($err -match 'Too many failed attempts') {
      'Too many failed attempts. Ask your instructor to unlock this PC.'
    } elseif ($err -match 'already have an active session') {
      'You already have an active session on another PC.'
    } elseif ($err -match 'already has an active session') {
      'This PC is in use. Ask your instructor for help.'
    } elseif ($err -match 'maintenance') {
      'This PC is in maintenance. Use another station.'
    } elseif ($err -match 'server_base_url|Unable to connect|name could not be resolved|YOUR-SERVER') {
      'Cannot reach XPLabs server. Fix server_base_url in agent.config.json.'
    } else {
      "Sign-in failed: $err"
    }
    $state.last_student_login_status = 'error'
    $state.last_student_login_message = $friendly
    Set-AgentState -State $state
  } finally {
    Clear-StudentLoginRequest
  }
}

function Process-OverrideUnlockRequest {
  param([Parameter(Mandatory)] [string] $MachineKey)
  $req = Get-OverrideRequest
  if (-not $req) { return }

  $state = Get-AgentState
  if (-not ($state.PSObject.Properties.Name -contains 'last_override_status')) {
    $state | Add-Member -NotePropertyName last_override_status -NotePropertyValue ''
  }
  if (-not ($state.PSObject.Properties.Name -contains 'last_override_message')) {
    $state | Add-Member -NotePropertyName last_override_message -NotePropertyValue ''
  }

  $identifier = [string]$req.identifier
  $password = [string]$req.password
  if ([string]::IsNullOrWhiteSpace($identifier) -or [string]::IsNullOrWhiteSpace($password)) {
    Write-XplabsLog -Level warn -Message "Ignoring malformed override request"
    $state.last_override_status = 'error'
    $state.last_override_message = 'Provide admin ID/email and password.'
    Set-AgentState -State $state
    Clear-OverrideRequest
    return
  }

  try {
    $res = Invoke-XplabsApi -Method 'POST' -Path '/api/session/override-unlock' -MachineKey $MachineKey -Body @{
      identifier = $identifier
      password   = $password
    }
    $cmdId = ''
    if ($res -and ($res.PSObject.Properties.Name -contains 'command_id')) { $cmdId = [string]$res.command_id }
    Write-XplabsLog -Level info -Message "Override unlock accepted: command_id=$cmdId"
    $state.locked = $false
    $state.last_unlock_at = (Get-Date).ToString('s')
    $state.last_override_status = 'success'
    $state.last_override_message = 'Override approved. PC unlocked.'
    if (-not ($state.PSObject.Properties.Name -contains 'override_unlock_until')) {
      $state | Add-Member -NotePropertyName override_unlock_until -NotePropertyValue ''
    }
    $state.override_unlock_until = (Get-Date).AddMinutes(15).ToString('s')
    Set-AgentState -State $state
    Clear-XplabsShowLockscreenDemand
    Stop-XplabsUserLockscreen
    Send-XplabsStatusHeartbeat -Status 'online' -MachineKey $MachineKey | Out-Null
    Start-XplabsWidget | Out-Null
  } catch {
    $err = [string]$_.Exception.Message
    Write-XplabsLog -Level warn -Message "Override unlock failed: $err"
    $friendly = if ($err -match 'Invalid override credentials') {
      'Invalid admin credentials.'
    } elseif ($err -match 'Too many failed attempts') {
      'Too many failed attempts. Please wait and try again.'
    } elseif ($err -match 'Machine authentication required') {
      'Client authentication failed. Contact administrator.'
    } else {
      "Override failed: $err"
    }
    $state.last_override_status = 'error'
    $state.last_override_message = $friendly
    Set-AgentState -State $state
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
      Start-XplabsLockscreen | Out-Null
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

# After Windows boot only (not each agent loop restart).
if (Test-ShouldApplyBootLock) {
  $state = Initialize-BootLockState
  if ($state.locked) {
    Invoke-XplabsAccessCleanup
    Stop-XplabsSessionZeroUi
    Send-XplabsStatusHeartbeat -Status 'locked' -MachineKey $machineKey | Out-Null
    Write-XplabsLog -Level info -Message 'Boot lock applied; lockscreen shows at user logon via scheduled task'
  }
} else {
  Write-XplabsLog -Level info -Message 'Agent loop started (skipping boot lock; not a fresh Windows boot)'
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
$script:lastDesktopSessionId = Get-XplabsExplorerSessionId
$script:hadExplorerSession = ($script:lastDesktopSessionId -gt 0)

while ($true) {
  $now = Get-Date

  try {
    Process-DesktopSessionLock -MachineKey $machineKey
  } catch {
    Write-XplabsLog -Level warn -Message "Desktop session lock check failed: $($_.Exception.Message)"
  }

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
        Add-CommandsToTickBatch -Commands $hb.commands
      }
      $state = Get-AgentState
      if ($hb -and ($hb.PSObject.Properties.Name -contains 'server_time')) {
        $state.last_server_time = $hb.server_time
      }
      if ($hb -and ($hb.PSObject.Properties.Name -contains 'ack_id')) {
        $state.last_ack_id = [string]$hb.ack_id
      }
      # Cursor advances in Process-Command after ack — do not jump ahead from heartbeat alone.
      if ($hb -and ($hb.PSObject.Properties.Name -contains 'active_session') -and $hb.active_session -and ($hb.active_session.PSObject.Properties.Name -contains 'lrn')) {
        $state.last_lrn = [string] $hb.active_session.lrn
      }
      $state.last_success_at = (Get-Date).ToString('s')
      $state.consecutive_failures = 0
      $state.next_retry_hint = $null
      Set-AgentState -State $state
      $consecutiveHeartbeatFailures = 0
      Write-XplabsLog -Level info -Message "checkin_state=online ack_id=$($state.last_ack_id) cursor=$($state.last_command_cursor)"
      $dup = $null
      if ($hb -and ($hb.PSObject.Properties.Name -contains 'duplicate')) { $dup = [string]$hb.duplicate }
      Write-XplabsDebugEvent -EventType 'checkin_result' -Severity 'info' -Data @{
        heartbeat_id = [string]($payload.heartbeat_id)
        ack_id = [string]($state.last_ack_id)
        command_cursor = [string]($state.last_command_cursor)
        duplicate = $dup
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
      Add-CommandsToTickBatch -Commands $cmds
    } catch {
      Write-XplabsLog -Level warn -Message "Command poll failed: $($_.Exception.Message)"
    }
    $lastPoll = $now
  }

  try {
    Invoke-CommandsThisTick -MachineKey $machineKey
  } catch {
    Write-XplabsLog -Level warn -Message "Command batch failed: $($_.Exception.Message)"
  }

  foreach ($proc in @(
    { Process-StudentLoginRequest -MachineKey $machineKey },
    { Process-OverrideUnlockRequest -MachineKey $machineKey },
    { Process-AdminHotkeyRequest -MachineKey $machineKey },
    { Process-LocalLockRequest }
  )) {
    try { & $proc } catch {
      Write-XplabsLog -Level error -Message "Request processor failed: $($_.Exception.Message)"
    }
  }

  if (($now - $lastCfgSync).TotalSeconds -ge 60) {
    $cfg = Sync-ServerConfig -MachineKey $machineKey -Cfg $cfg
    $hbEvery = Get-CfgInt $cfg 'heartbeat_interval_seconds' 30
    $pollEvery = Get-CfgInt $cfg 'command_poll_interval_seconds' 5
    $validateEvery = Get-CfgInt $cfg 'validate_interval_seconds' 10
    $lastCfgSync = $now
  }

  $uiCheckEvery = 45
  if (($now - $lastUiCheck).TotalSeconds -ge $uiCheckEvery) {
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
        Send-XplabsStatusHeartbeat -Status 'locked' -MachineKey $machineKey | Out-Null
        Start-XplabsLockscreen | Out-Null
        Write-XplabsLog -Level info -Message "Auto-locked: server validate returned lock_screen"
      }
    }
    $lastVal = $now
  }

  if ($Once) { break }
  Start-Sleep -Seconds 1
}

