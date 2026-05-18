param(
  [Parameter(Mandatory)] [string] $SourceDir,
  [switch] $SkipStart
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Ensure-Dir([string]$Path) {
  if (-not (Test-Path $Path)) { New-Item -ItemType Directory -Path $Path -Force | Out-Null }
}

function Start-InteractiveTaskIfSessionActive([string]$TaskName) {
  try {
    $interactiveUser = Get-CimInstance Win32_ComputerSystem | Select-Object -ExpandProperty UserName
    if ([string]::IsNullOrWhiteSpace([string]$interactiveUser)) { return }
    Start-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    Write-Host "Started interactive task in active session: $TaskName"
  } catch {}
}

function Get-InteractiveLogonType {
  $supported = [Enum]::GetNames([Microsoft.PowerShell.Cmdletization.GeneratedTypes.ScheduledTask.LogonTypeEnum])
  foreach ($candidate in @('Interactive', 'InteractiveToken')) {
    if ($supported -contains $candidate) { return $candidate }
  }
  return 'Interactive'
}

function New-LogonUiTaskSettings {
  return New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -MultipleInstances IgnoreNew `
    -RestartCount 3 `
    -RestartInterval (New-TimeSpan -Minutes 2)
}

function Format-SchtasksTrArgument([string]$Path) {
  if ([string]::IsNullOrWhiteSpace($Path)) { throw 'Path required for schtasks /TR' }
  return ('"{0}"' -f $Path.Trim().Trim('"'))
}

function Invoke-SchtasksCreate {
  param(
    [Parameter(Mandatory)] [string] $TaskName,
    [Parameter(Mandatory)] [string] $TaskRun,
    [Parameter(Mandatory)] [string] $Schedule,
    [string[]] $RunAs = @('BUILTIN\Users', 'Users'),
    [string] $Delay = '',
    [string] $RunLevel = 'HIGHEST'
  )
  $tr = Format-SchtasksTrArgument $TaskRun
  foreach ($ru in $RunAs) {
    $args = @('/Create', '/F', '/TN', $TaskName, '/TR', $tr, '/SC', $Schedule, '/RL', $RunLevel, '/RU', $ru)
    if ($Delay) { $args += @('/DELAY', $Delay) }
    $p = Start-Process -FilePath 'schtasks.exe' -ArgumentList $args -Wait -PassThru -NoNewWindow
    if ($p.ExitCode -eq 0) { return $true }
  }
  return $false
}

function Stop-SessionZeroLockscreenProcesses {
  Get-Process -Name 'XPLabs.LockScreen' -ErrorAction SilentlyContinue | ForEach-Object {
    if ($_.SessionId -eq 0) {
      Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue
    }
  }
}

function Register-LockscreenUiTask {
  param(
    [Parameter(Mandatory)] [string] $TaskName,
    [Parameter(Mandatory)] [string] $ExePath,
    [Parameter(Mandatory)] [string] $LauncherPath,
    [string] $LauncherCmdPath = '',
    [string] $LogonCmdPath = '',
    [int] $BootDelaySeconds = 90
  )

  if (-not (Test-Path $ExePath)) { throw "Executable not found: $ExePath" }
  if (-not (Test-Path $LauncherPath)) { throw "Launcher not found: $LauncherPath" }

  $launcherCmd = $LauncherCmdPath
  if ([string]::IsNullOrWhiteSpace($launcherCmd)) {
    $launcherCmd = [System.IO.Path]::ChangeExtension($LauncherPath, '.cmd')
  }

  $logonRun = $ExePath
  if (-not [string]::IsNullOrWhiteSpace($LogonCmdPath) -and (Test-Path $LogonCmdPath)) {
    $logonRun = $LogonCmdPath
  }

  $action = New-ScheduledTaskAction -Execute $logonRun
  $triggerLogon = New-ScheduledTaskTrigger -AtLogOn
  $settings = New-LogonUiTaskSettings
  $logonType = Get-InteractiveLogonType

  foreach ($tn in @($TaskName, "${TaskName}Boot", "${TaskName}Prep")) {
    try { Unregister-ScheduledTask -TaskName $tn -Confirm:$false -ErrorAction SilentlyContinue | Out-Null } catch {}
    schtasks.exe /Delete /TN $tn /F 2>$null | Out-Null
  }

  $registered = $false
  foreach ($attempt in @(
    @{ Id = 'Users'; RunLevel = 'Highest' },
    @{ Id = 'Users'; RunLevel = 'Limited' },
    @{ Id = 'BUILTIN\Users'; RunLevel = 'Highest' }
  )) {
    try {
      $principal = New-ScheduledTaskPrincipal -GroupId $attempt.Id -LogonType $logonType -RunLevel $attempt.RunLevel
      Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $triggerLogon -Principal $principal -Settings $settings | Out-Null
      Write-Host "Registered scheduled task: $TaskName (At logon, $($attempt.Id))"
      $registered = $true
      break
    } catch {}
  }

  if (-not $registered) {
    if (-not (Invoke-SchtasksCreate -TaskName $TaskName -TaskRun $logonRun -Schedule 'ONLOGON')) {
      throw "Failed to register $TaskName (schtasks ONLOGON)."
    }
    Write-Host "Registered scheduled task: $TaskName (schtasks ONLOGON)"
  }

  $delay = '{0:D4}:{1:D2}' -f [int][Math]::Floor($BootDelaySeconds / 60), ($BootDelaySeconds % 60)
  $prepTask = "${TaskName}Prep"
  $logonDelayed = Join-Path (Split-Path -Parent $LogonCmdPath) 'Logon-Lockscreen-Delayed.cmd'
  if (-not [string]::IsNullOrWhiteSpace($LogonCmdPath) -and (Test-Path $LogonCmdPath)) {
    if (Invoke-SchtasksCreate -TaskName "${TaskName}Delayed" -TaskRun $(if (Test-Path $logonDelayed) { $logonDelayed } else { $LogonCmdPath }) -Schedule 'ONLOGON') {
      Write-Host "Registered scheduled task: ${TaskName}Delayed (ONLOGON backup)"
    }
  }

  $prepRun = if ((Test-Path $launcherCmd)) { $launcherCmd } else { $logonRun }
  if (Invoke-SchtasksCreate -TaskName $prepTask -TaskRun $prepRun -Schedule 'ONSTART' -Delay $delay) {
    Write-Host "Registered scheduled task: $prepTask (ONSTART delay $delay)"
  } else {
    Write-Warning "Boot prep task $prepTask failed (schtasks ONSTART)."
  }
}

function Register-LogonUiTask {
  param(
    [Parameter(Mandatory)] [string] $TaskName,
    [Parameter(Mandatory)] [string] $ExePath
  )

  if (-not (Test-Path $ExePath)) {
    throw "Executable not found: $ExePath"
  }

  $quotedExe = '"' + $ExePath + '"'
  $action = New-ScheduledTaskAction -Execute $ExePath
  $trigger = New-ScheduledTaskTrigger -AtLogOn
  $settings = New-LogonUiTaskSettings
  $logonType = Get-InteractiveLogonType

  try { Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction SilentlyContinue | Out-Null } catch {}

  $principalAttempts = @(
    @{ Kind = 'Group'; Id = 'Users'; RunLevel = 'Highest' },
    @{ Kind = 'Group'; Id = 'Users'; RunLevel = 'Limited' },
    @{ Kind = 'Group'; Id = 'BUILTIN\Users'; RunLevel = 'Highest' }
  )

  foreach ($attempt in $principalAttempts) {
    try {
      $principal = New-ScheduledTaskPrincipal -GroupId $attempt.Id -LogonType $logonType -RunLevel $attempt.RunLevel
      Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings | Out-Null
      Write-Host "Registered scheduled task: $TaskName (principal: $($attempt.Id), $($attempt.RunLevel))"
      return
    } catch {
      Write-Verbose "Register-ScheduledTask failed for $TaskName ($($attempt.Id)): $($_.Exception.Message)"
    }
  }

  # schtasks fallback — valid on locked-down Win10/11 when Register-ScheduledTask principal XML is rejected
  $schArgs = @(
    '/Create', '/F',
    '/TN', $TaskName,
    '/TR', $quotedExe,
    '/SC', 'ONLOGON',
    '/RL', 'HIGHEST',
    '/RU', 'BUILTIN\Users'
  )
  $proc = Start-Process -FilePath 'schtasks.exe' -ArgumentList $schArgs -Wait -PassThru -NoNewWindow
  if ($proc.ExitCode -ne 0) {
    throw "Failed to register $TaskName via Register-ScheduledTask and schtasks.exe (exit $($proc.ExitCode))."
  }
  Write-Host "Registered scheduled task: $TaskName (schtasks ONLOGON fallback)"
}

$programDir = Join-Path $env:ProgramFiles 'XPLabsAgent'
$dataDir = Join-Path $env:ProgramData 'XPLabsAgent'
$logDir = Join-Path $dataDir 'logs'

Ensure-Dir $programDir
Ensure-Dir $dataDir
Ensure-Dir $logDir
Ensure-Dir (Join-Path $programDir 'LockScreen')
Ensure-Dir (Join-Path $programDir 'Widget')

# Copy agent files locally
Copy-Item -Path (Join-Path $SourceDir 'agent\*') -Destination $programDir -Recurse -Force

# Seed config if missing
$configPath = Join-Path $dataDir 'agent.config.json'
if (-not (Test-Path $configPath)) {
  Copy-Item -Path (Join-Path $SourceDir 'agent\agent.config.json.example') -Destination $configPath -Force
}

# Scheduled Task: run agent loop at boot (SYSTEM) — survives reboot
$taskName = 'XPLabsAgentLoop'
$script = Join-Path $programDir 'Run-AgentLoop.ps1'
$psArgs = "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$script`""
$action = New-ScheduledTaskAction -Execute 'PowerShell.exe' -Argument $psArgs
$trigger = New-ScheduledTaskTrigger -AtStartup
$principal = New-ScheduledTaskPrincipal -UserId 'NT AUTHORITY\SYSTEM' -LogonType ServiceAccount -RunLevel Highest
$settings = New-ScheduledTaskSettingsSet `
  -AllowStartIfOnBatteries `
  -DontStopIfGoingOnBatteries `
  -StartWhenAvailable `
  -ExecutionTimeLimit ([TimeSpan]::Zero) `
  -RestartCount 999 `
  -RestartInterval (New-TimeSpan -Minutes 1)

try { Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue | Out-Null } catch {}

$agentRegistered = $false
try {
  Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings | Out-Null
  $agentRegistered = $true
  Write-Host "Registered scheduled task: $taskName (At startup, SYSTEM)"
} catch {
  Write-Warning "Register-ScheduledTask failed for ${taskName}: $($_.Exception.Message)"
}

if (-not $agentRegistered) {
  $tr = "`"powershell.exe`" $psArgs"
  $schArgs = @('/Create', '/F', '/TN', $taskName, '/TR', $tr, '/SC', 'ONSTART', '/RU', 'SYSTEM', '/RL', 'HIGHEST', '/DELAY', '0000:30')
  $proc = Start-Process -FilePath 'schtasks.exe' -ArgumentList $schArgs -Wait -PassThru -NoNewWindow
  if ($proc.ExitCode -ne 0) {
    throw "Failed to register agent startup task (exit $($proc.ExitCode))."
  }
  Write-Host "Registered scheduled task: $taskName (schtasks ONSTART fallback, 30s delay)"
}

# Start immediately
if (-not $SkipStart) {
  Start-ScheduledTask -TaskName $taskName
  Write-Host "Started scheduled task: $taskName"
}

# LockScreen: at user logon + at boot (delayed) so UI appears after restart without manual start
$lockExe = Join-Path $programDir 'LockScreen\XPLabs.LockScreen.exe'
$logonLockCmd = Join-Path $programDir 'Logon-Lockscreen.cmd'
$lockTask = 'XPLabsLockScreen'
if ((Test-Path $lockExe) -and (Test-Path $logonLockCmd)) {
  try {
    Stop-SessionZeroLockscreenProcesses
    Register-LockscreenUiTask -TaskName $lockTask -ExePath $lockExe -LauncherPath $logonLockCmd -LauncherCmdPath $logonLockCmd -LogonCmdPath $logonLockCmd -BootDelaySeconds 90
    $runCmd = "`"$logonLockCmd`""
    New-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Run' -Name 'XPLabsLockScreen' -Value $runCmd -PropertyType String -Force | Out-Null
    Write-Host "Registered HKLM Run key: XPLabsLockScreen"
    Start-InteractiveTaskIfSessionActive -TaskName $lockTask
    Start-Process -FilePath $logonLockCmd -WindowStyle Hidden | Out-Null
  } catch {
    Write-Warning "LockScreen task registration failed: $($_.Exception.Message)"
  }
} else {
  try { Unregister-ScheduledTask -TaskName $lockTask -Confirm:$false -ErrorAction SilentlyContinue | Out-Null } catch {}
  Write-Warning "LockScreen not installed. Need LockScreen EXE + Logon-Lockscreen.cmd under $programDir"
}

# Optional: Widget app task (runs in user session at logon)
# Expect compiled exe at: C:\Program Files\XPLabsAgent\Widget\XPLabs.Widget.exe
$widgetExe = Join-Path $programDir 'Widget\XPLabs.Widget.exe'
$widgetTask = 'XPLabsWidget'
if (Test-Path $widgetExe) {
  try {
    Register-LogonUiTask -TaskName $widgetTask -ExePath $widgetExe
    Start-InteractiveTaskIfSessionActive -TaskName $widgetTask
  } catch {
    Write-Warning "Widget task registration failed: $($_.Exception.Message)"
  }
} else {
  try { Unregister-ScheduledTask -TaskName $widgetTask -Confirm:$false -ErrorAction SilentlyContinue | Out-Null } catch {}
  Write-Warning "Widget executable not found at $widgetExe. Build and copy XPLabs.Widget.exe to enable agent widget."
}

