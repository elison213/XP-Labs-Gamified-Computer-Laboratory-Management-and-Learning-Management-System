param(
  [Parameter(Mandatory)] [string] $SourceDir
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Ensure-Dir([string]$Path) {
  if (-not (Test-Path $Path)) { New-Item -ItemType Directory -Path $Path -Force | Out-Null }
}

$programDir = Join-Path $env:ProgramFiles 'XPLabsAgent'
$dataDir = Join-Path $env:ProgramData 'XPLabsAgent'
$logDir = Join-Path $dataDir 'logs'

Ensure-Dir $programDir
Ensure-Dir $dataDir
Ensure-Dir $logDir

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

# Start immediately
Start-ScheduledTask -TaskName $taskName

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
} else {
  # No exe yet (source-only in repo). Deployment should place the compiled exe into ProgramDir\LockScreen.
}

