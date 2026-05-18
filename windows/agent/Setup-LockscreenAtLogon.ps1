# Run once as Administrator while signed in at the desktop.
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$programDir = Join-Path $env:ProgramFiles 'XPLabsAgent'
$exe = Join-Path $programDir 'LockScreen\XPLabs.LockScreen.exe'
$logonCmd = Join-Path $programDir 'Logon-Lockscreen.cmd'
$logonDelayed = Join-Path $programDir 'Logon-Lockscreen-Delayed.cmd'

foreach ($path in @($exe, $logonCmd)) {
  if (-not (Test-Path $path)) {
    throw "Missing: $path - copy windows\agent\* and LockScreen EXE to Program Files first."
  }
}

if (-not (Test-Path $logonDelayed)) {
  @'
@echo off
timeout /t 20 /nobreak >nul
call "%~dp0Show-Lockscreen.cmd"
'@ | Set-Content -Path $logonDelayed -Encoding ASCII
  Write-Host "Created Logon-Lockscreen-Delayed.cmd" -ForegroundColor Green
}

function Format-SchtasksTr([string]$Path) {
  return ('"{0}"' -f $Path.Trim().Trim('"'))
}

function Remove-SchtaskSafe([string]$Name) {
  $null = Start-Process -FilePath 'schtasks.exe' -ArgumentList @('/Delete', '/TN', $Name, '/F') -Wait -PassThru -NoNewWindow
}

function New-LogonSchtask([string]$Name, [string]$RunPath) {
  Remove-SchtaskSafe -Name $Name
  $tr = Format-SchtasksTr $RunPath
  foreach ($ru in @('Users', 'BUILTIN\Users')) {
    $args = @('/Create', '/F', '/TN', $Name, '/TR', $tr, '/SC', 'ONLOGON', '/RL', 'HIGHEST', '/RU', $ru, '/IT')
    $p = Start-Process -FilePath 'schtasks.exe' -ArgumentList $args -Wait -PassThru -NoNewWindow
    if ($p.ExitCode -eq 0) {
      Write-Host "OK: $Name (ONLOGON, /IT, $ru)" -ForegroundColor Green
      return
    }
  }
  $args = @('/Create', '/F', '/TN', $Name, '/TR', $tr, '/SC', 'ONLOGON', '/RL', 'HIGHEST', '/RU', 'Users')
  $p = Start-Process -FilePath 'schtasks.exe' -ArgumentList $args -Wait -PassThru -NoNewWindow
  if ($p.ExitCode -ne 0) { throw "schtasks failed for $Name (exit $($p.ExitCode))" }
  Write-Host "OK: $Name (ONLOGON fallback)" -ForegroundColor Green
}

Write-Host "Removing session-0 lockscreen processes..." -ForegroundColor Yellow
Get-Process -Name 'XPLabs.LockScreen' -ErrorAction SilentlyContinue | ForEach-Object {
  Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue
}

$statePath = Join-Path $env:ProgramData 'XPLabsAgent\state.json'
$stateDir = Split-Path -Parent $statePath
if (-not (Test-Path $stateDir)) { New-Item -ItemType Directory -Path $stateDir -Force | Out-Null }
Set-Content -Path $statePath -Value '{"locked":true}' -Encoding UTF8
Write-Host "Set state.json locked=true" -ForegroundColor Green

New-LogonSchtask -Name 'XPLabsLockScreen' -RunPath $logonCmd
New-LogonSchtask -Name 'XPLabsLockScreenDelayed' -RunPath $logonDelayed

$runValue = "`"$logonCmd`""
New-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Run' -Name 'XPLabsLockScreen' -Value $runValue -PropertyType String -Force | Out-Null
Write-Host "HKLM Run key set." -ForegroundColor Green

Write-Host "Testing logon launcher now..." -ForegroundColor Cyan
cmd.exe /c "`"$logonCmd`""
Start-Sleep -Seconds 3
$proc = Get-Process -Name 'XPLabs.LockScreen' -ErrorAction SilentlyContinue | Where-Object { $_.SessionId -gt 0 }
if ($proc) {
  Write-Host "OK: Lockscreen visible (PID $($proc.Id), Session $($proc.SessionId))." -ForegroundColor Green
  Write-Host "Reboot or sign out/in to confirm it runs automatically at logon." -ForegroundColor Cyan
} else {
  Write-Host "Lockscreen not visible. Check:" -ForegroundColor Yellow
  Write-Host "  schtasks /Query /TN XPLabsLockScreen /V /FO LIST"
  Write-Host "  Get-Content C:\ProgramData\XPLabsAgent\state.json"
}
