param(
  [Parameter(Mandatory)] [string] $SourceDir
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

# Scheduled Task: run agent loop at startup as SYSTEM
$taskName = 'XPLabsAgentLoop'
$script = Join-Path $programDir 'Run-AgentLoop.ps1'
$action = New-ScheduledTaskAction -Execute 'PowerShell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$script`""
$trigger = New-ScheduledTaskTrigger -AtStartup
$principal = New-ScheduledTaskPrincipal -UserId 'NT AUTHORITY\SYSTEM' -LogonType ServiceAccount -RunLevel Highest
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1)

try { Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue | Out-Null } catch {}
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings | Out-Null
Write-Host "Registered scheduled task: $taskName"

# Start immediately
Start-ScheduledTask -TaskName $taskName
Write-Host "Started scheduled task: $taskName"

# Optional: LockScreen app task (runs in user session at logon)
# Expect compiled exe at: C:\Program Files\XPLabsAgent\LockScreen\XPLabs.LockScreen.exe
$lockExe = Join-Path $programDir 'LockScreen\XPLabs.LockScreen.exe'
$lockTask = 'XPLabsLockScreen'
if (Test-Path $lockExe) {
  $lockAction = New-ScheduledTaskAction -Execute $lockExe
  $lockTrigger = New-ScheduledTaskTrigger -AtLogOn
  $lockPrincipal = New-ScheduledTaskPrincipal -UserId 'NT AUTHORITY\INTERACTIVE' -LogonType InteractiveToken -RunLevel Highest
  $lockSettings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1)
  try { Unregister-ScheduledTask -TaskName $lockTask -Confirm:$false -ErrorAction SilentlyContinue | Out-Null } catch {}
  Register-ScheduledTask -TaskName $lockTask -Action $lockAction -Trigger $lockTrigger -Principal $lockPrincipal -Settings $lockSettings | Out-Null
  Write-Host "Registered scheduled task: $lockTask"
  Start-InteractiveTaskIfSessionActive -TaskName $lockTask
} else {
  try { Unregister-ScheduledTask -TaskName $lockTask -Confirm:$false -ErrorAction SilentlyContinue | Out-Null } catch {}
  Write-Warning "LockScreen executable not found at $lockExe. Build and copy XPLabs.LockScreen.exe to enable lock UI."
}

# Optional: Widget app task (runs in user session at logon)
# Expect compiled exe at: C:\Program Files\XPLabsAgent\Widget\XPLabs.Widget.exe
$widgetExe = Join-Path $programDir 'Widget\XPLabs.Widget.exe'
$widgetTask = 'XPLabsWidget'
if (Test-Path $widgetExe) {
  $widgetAction = New-ScheduledTaskAction -Execute $widgetExe
  $widgetTrigger = New-ScheduledTaskTrigger -AtLogOn
  $widgetPrincipal = New-ScheduledTaskPrincipal -UserId 'NT AUTHORITY\INTERACTIVE' -LogonType InteractiveToken -RunLevel Highest
  $widgetSettings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1)
  try { Unregister-ScheduledTask -TaskName $widgetTask -Confirm:$false -ErrorAction SilentlyContinue | Out-Null } catch {}
  Register-ScheduledTask -TaskName $widgetTask -Action $widgetAction -Trigger $widgetTrigger -Principal $widgetPrincipal -Settings $widgetSettings | Out-Null
  Write-Host "Registered scheduled task: $widgetTask"
  Start-InteractiveTaskIfSessionActive -TaskName $widgetTask
} else {
  try { Unregister-ScheduledTask -TaskName $widgetTask -Confirm:$false -ErrorAction SilentlyContinue | Out-Null } catch {}
  Write-Warning "Widget executable not found at $widgetExe. Build and copy XPLabs.Widget.exe to enable agent widget."
}

