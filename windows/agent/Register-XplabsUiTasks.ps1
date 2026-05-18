# Register all XPLabs UI scheduled tasks (lockscreen, widget, boot prep). Run as Administrator.
param(
  [string] $AgentDir = '',
  [int] $BootDelaySeconds = 90,
  [switch] $SkipAgentRestart
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($AgentDir)) {
  $AgentDir = Join-Path $env:ProgramFiles 'XPLabsAgent'
}

$logon = Join-Path $AgentDir 'Logon-Lockscreen.cmd'
$show = Join-Path $AgentDir 'Show-Lockscreen.cmd'
$showWidget = Join-Path $AgentDir 'Show-Widget-Now.cmd'
$delayed = Join-Path $AgentDir 'Logon-Lockscreen-Delayed.cmd'
$widgetExe = Join-Path $AgentDir 'Widget\XPLabs.Widget.exe'
$lockExe = Join-Path $AgentDir 'LockScreen\XPLabs.LockScreen.exe'

if (-not (Test-Path $logon)) {
  throw "Missing $logon - copy agent files to Program Files\XPLabsAgent first."
}

function Format-SchtasksTr([string]$Path) {
  $p = $Path.Trim().Trim('"')
  if ($p -like '*.ps1') {
    return "`"powershell.exe -NoProfile -ExecutionPolicy Bypass -File `"$p`"`""
  }
  if ($p -like '*.exe') {
    return "`"$p`""
  }
  return "`"$p`""
}

function Invoke-SchtasksQuiet {
  param([Parameter(Mandatory)] [string[]] $ArgumentList)
  $prev = $ErrorActionPreference
  $ErrorActionPreference = 'Continue'
  & schtasks.exe @ArgumentList 1>$null 2>$null
  $code = $LASTEXITCODE
  $ErrorActionPreference = $prev
  return $code
}

function Remove-SchtaskSafe([string]$TaskName) {
  if ((Invoke-SchtasksQuiet -ArgumentList @('/Query', '/TN', $TaskName)) -ne 0) { return }
  Invoke-SchtasksQuiet -ArgumentList @('/Delete', '/TN', $TaskName, '/F') | Out-Null
}

function New-OnLogonTask([string]$Name, [string]$RunPath) {
  if (-not (Test-Path $RunPath)) {
    Write-Warning "Skip $Name - missing: $RunPath"
    return $false
  }
  $tr = Format-SchtasksTr -Path $RunPath
  foreach ($ru in @('Users', 'BUILTIN\Users')) {
    $code = Invoke-SchtasksQuiet -ArgumentList @('/Create', '/F', '/TN', $Name, '/TR', $tr, '/SC', 'ONLOGON', '/RL', 'HIGHEST', '/RU', $ru, '/IT')
    if ($code -eq 0) {
      Write-Host "OK: $Name ($ru, ONLOGON)" -ForegroundColor Green
      return $true
    }
  }
  $code = Invoke-SchtasksQuiet -ArgumentList @('/Create', '/F', '/TN', $Name, '/TR', $tr, '/SC', 'ONLOGON', '/RL', 'HIGHEST', '/RU', 'Users')
  if ($code -ne 0) {
    Write-Warning "Failed to create $Name (exit $code)"
    return $false
  }
  Write-Host "OK: $Name (fallback ONLOGON)" -ForegroundColor Green
  return $true
}

function New-OnStartDelayedTask([string]$Name, [string]$RunPath, [int]$DelaySeconds) {
  if (-not (Test-Path $RunPath)) {
    Write-Warning "Skip $Name - missing: $RunPath"
    return $false
  }
  $tr = Format-SchtasksTr -Path $RunPath
  $delay = '{0:D4}:{1:D2}' -f [int][Math]::Floor($DelaySeconds / 60), ($DelaySeconds % 60)
  foreach ($ru in @('Users', 'BUILTIN\Users', 'SYSTEM')) {
    $code = Invoke-SchtasksQuiet -ArgumentList @('/Create', '/F', '/TN', $Name, '/TR', $tr, '/SC', 'ONSTART', '/DELAY', $delay, '/RL', 'HIGHEST', '/RU', $ru)
    if ($code -eq 0) {
      Write-Host "OK: $Name ($ru, ONSTART delay $delay)" -ForegroundColor Green
      return $true
    }
  }
  Write-Warning "Failed to create $Name boot prep (exit $LASTEXITCODE)"
  return $false
}

$taskNames = @(
  'XPLabsLockScreen',
  'XPLabsLockScreenDelayed',
  'XPLabsLockScreenPrep',
  'XPLabsLockScreenBoot',
  'XPLabsShowLockScreen',
  'XPLabsShowWidget',
  'XPLabsWidget'
)

Write-Host "Removing old UI tasks..." -ForegroundColor Cyan
foreach ($tn in $taskNames) {
  Remove-SchtaskSafe -TaskName $tn
}

Write-Host "Registering UI tasks under $AgentDir" -ForegroundColor Cyan
New-OnLogonTask -Name 'XPLabsLockScreen' -RunPath $logon | Out-Null
if (Test-Path $delayed) { New-OnLogonTask -Name 'XPLabsLockScreenDelayed' -RunPath $delayed | Out-Null }
if (Test-Path $show) { New-OnLogonTask -Name 'XPLabsShowLockScreen' -RunPath $show | Out-Null }
New-OnStartDelayedTask -Name 'XPLabsLockScreenPrep' -RunPath $logon -DelaySeconds $BootDelaySeconds | Out-Null

if (Test-Path $widgetExe) {
  New-OnLogonTask -Name 'XPLabsWidget' -RunPath $widgetExe | Out-Null
} else {
  Write-Warning "Widget exe missing: $widgetExe"
}

if (Test-Path $showWidget) {
  New-OnLogonTask -Name 'XPLabsShowWidget' -RunPath $showWidget | Out-Null
} elseif (Test-Path $widgetExe) {
  New-OnLogonTask -Name 'XPLabsShowWidget' -RunPath $widgetExe | Out-Null
}

Set-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Run' -Name 'XPLabsLockScreen' -Value "`"$logon`"" -Force
Write-Host 'HKLM Run key XPLabsLockScreen updated.' -ForegroundColor Green

if (-not (Test-Path $lockExe)) {
  Write-Warning "Lockscreen exe missing: $lockExe"
}

Write-Host "`nRegistered tasks:" -ForegroundColor Cyan
foreach ($tn in @('XPLabsLockScreen', 'XPLabsLockScreenPrep', 'XPLabsWidget', 'XPLabsShowWidget')) {
  if ((Invoke-SchtasksQuiet -ArgumentList @('/Query', '/TN', $tn)) -eq 0) {
    $detail = schtasks.exe /Query /TN $tn /V /FO LIST 2>$null |
      Select-String -Pattern '^(TaskName|Task To Run|Execute)\s*:' |
      ForEach-Object { $_.Line.Trim() }
    if ($detail) {
      $detail | ForEach-Object { Write-Host "  $_" }
    } else {
      Write-Host "  $tn (registered)" -ForegroundColor Green
    }
  } else {
    Write-Warning "Task not present: $tn"
  }
}

if (-not $SkipAgentRestart) {
  Invoke-SchtasksQuiet -ArgumentList @('/Run', '/TN', 'XPLabsAgentLoop') | Out-Null
  Write-Host 'Agent loop task triggered (XPLabsAgentLoop).' -ForegroundColor Green
}
