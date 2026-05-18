# One-shot repair: register lockscreen tasks and show UI on the interactive desktop.
# Shipped under windows\agent so "Copy-Item .\windows\agent\*" includes it on lab PCs.
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$programDir = Join-Path $env:ProgramFiles 'XPLabsAgent'
$exe = Join-Path $programDir 'LockScreen\XPLabs.LockScreen.exe'
$launcher = Join-Path $programDir 'Launch-Lockscreen.ps1'
$launcherCmd = Join-Path $programDir 'Launch-Lockscreen.cmd'
$logonLockCmd = Join-Path $programDir 'Logon-Lockscreen.cmd'
$statePath = Join-Path $env:ProgramData 'XPLabsAgent\state.json'

function Format-SchtasksTrArgument([string]$Path) {
  return ('"{0}"' -f $Path.Trim().Trim('"'))
}

function Invoke-SchtasksCreate {
  param(
    [Parameter(Mandatory)] [string] $TaskName,
    [Parameter(Mandatory)] [string] $TaskRun,
    [Parameter(Mandatory)] [string] $Schedule,
    [string[]] $RunAs = @('BUILTIN\Users', 'Users'),
    [string] $Delay = ''
  )
  $tr = Format-SchtasksTrArgument $TaskRun
  foreach ($ru in $RunAs) {
    $args = @('/Create', '/F', '/TN', $TaskName, '/TR', $tr, '/SC', $Schedule, '/RL', 'HIGHEST', '/RU', $ru)
    if ($Delay) { $args += @('/DELAY', $Delay) }
    $p = Start-Process -FilePath 'schtasks.exe' -ArgumentList $args -Wait -PassThru -NoNewWindow
    if ($p.ExitCode -eq 0) { return $true }
  }
  return $false
}

if (-not (Test-Path $exe)) {
  throw "Missing: $exe - copy LockScreen build to Program Files\XPLabsAgent\LockScreen first."
}

Write-Host "Stopping session-0 lockscreen processes..." -ForegroundColor Yellow
Get-Process -Name 'XPLabs.LockScreen' -ErrorAction SilentlyContinue | ForEach-Object {
  Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue
}

if (Test-Path $statePath) {
  $raw = Get-Content -Raw -Path $statePath -Encoding UTF8
  if ($raw -match '"locked"\s*:\s*false') {
    $raw = [regex]::Replace($raw, '"locked"\s*:\s*false', '"locked": true')
    Set-Content -Path $statePath -Value $raw -Encoding UTF8
    Write-Host "Set state.json locked=true" -ForegroundColor Green
  }
}

foreach ($tn in @('XPLabsLockScreen', 'XPLabsLockScreenBoot', 'XPLabsLockScreenPrep')) {
  schtasks.exe /Delete /TN $tn /F 2>$null | Out-Null
}

$logonRun = if (Test-Path $logonLockCmd) { $logonLockCmd } else { $exe }
Write-Host "Creating XPLabsLockScreen (ONLOGON)..." -ForegroundColor Cyan
if (-not (Invoke-SchtasksCreate -TaskName 'XPLabsLockScreen' -TaskRun $logonRun -Schedule 'ONLOGON')) {
  throw 'schtasks ONLOGON create failed'
}

$prepRun = if (Test-Path $launcherCmd) { $launcherCmd } elseif (Test-Path $launcher) { $launcher } else { $exe }
Write-Host "Creating XPLabsLockScreenPrep (ONSTART delay 90s)..." -ForegroundColor Cyan
if (-not (Invoke-SchtasksCreate -TaskName 'XPLabsLockScreenPrep' -TaskRun $prepRun -Schedule 'ONSTART' -Delay '0001:30')) {
  Write-Warning 'XPLabsLockScreenPrep failed (boot delay launcher). ONLOGON task is still registered.'
}

$runValue = if (Test-Path $logonLockCmd) { "`"$logonLockCmd`"" } elseif (Test-Path $launcherCmd) { "`"$launcherCmd`"" } else { "powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$launcher`"" }
New-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Run' -Name 'XPLabsLockScreen' -Value $runValue -PropertyType String -Force | Out-Null
Write-Host "HKLM Run key updated." -ForegroundColor Green

Write-Host "Starting lockscreen on your desktop..." -ForegroundColor Cyan
if (Test-Path $launcher) {
  & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $launcher
} else {
  schtasks.exe /Run /TN 'XPLabsLockScreen'
}

Start-Sleep -Seconds 2
$proc = Get-Process -Name 'XPLabs.LockScreen' -ErrorAction SilentlyContinue | Where-Object { $_.SessionId -gt 0 }
if ($proc) {
  Write-Host "OK: Lockscreen running (PID $($proc.Id), Session $($proc.SessionId))." -ForegroundColor Green
} else {
  Write-Host "Lockscreen not visible yet. Check:" -ForegroundColor Yellow
  Write-Host "  Get-Content C:\ProgramData\XPLabsAgent\logs\lockscreen-launch.log"
  Write-Host "  schtasks /Query /TN XPLabsLockScreen"
}
