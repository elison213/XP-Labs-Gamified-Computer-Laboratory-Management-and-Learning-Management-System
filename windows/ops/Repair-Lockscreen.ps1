# One-shot repair: register lockscreen task (EXE) and show it on the interactive desktop.
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$exe = Join-Path $env:ProgramFiles 'XPLabsAgent\LockScreen\XPLabs.LockScreen.exe'
$launcher = Join-Path $env:ProgramFiles 'XPLabsAgent\Launch-Lockscreen.ps1'
$statePath = Join-Path $env:ProgramData 'XPLabsAgent\state.json'

if (-not (Test-Path $exe)) {
  throw "Missing: $exe - run Install-Agent.ps1 and deploy lockscreen build first."
}

Write-Host "Stopping hidden session-0 lockscreen (if any)..." -ForegroundColor Yellow
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

$launcherCmd = Join-Path $env:ProgramFiles 'XPLabsAgent\Launch-Lockscreen.cmd'
$repair = Join-Path $env:ProgramFiles 'XPLabsAgent\Repair-Lockscreen.ps1'
if (Test-Path $repair) {
  & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $repair
  return
}

foreach ($tn in @('XPLabsLockScreen', 'XPLabsLockScreenBoot', 'XPLabsLockScreenPrep')) {
  schtasks.exe /Delete /TN $tn /F 2>$null | Out-Null
}

$tr = ('"{0}"' -f $exe)
Write-Host "Creating scheduled task XPLabsLockScreen (ONLOGON)..." -ForegroundColor Cyan
$p = Start-Process -FilePath 'schtasks.exe' -ArgumentList @('/Create','/F','/TN','XPLabsLockScreen','/TR',$tr,'/SC','ONLOGON','/RL','HIGHEST','/RU','BUILTIN\Users') -Wait -PassThru -NoNewWindow
if ($p.ExitCode -ne 0) { throw "schtasks create failed (exit $($p.ExitCode))" }

$prepRun = if (Test-Path $launcherCmd) { $launcherCmd } else { $launcher }
if ($prepRun -and (Test-Path $prepRun)) {
  $trPrep = ('"{0}"' -f $prepRun)
  Start-Process -FilePath 'schtasks.exe' -ArgumentList @('/Create','/F','/TN','XPLabsLockScreenPrep','/TR',$trPrep,'/SC','ONSTART','/DELAY','0001:30','/RL','HIGHEST','/RU','BUILTIN\Users') -Wait -NoNewWindow | Out-Null
}

$runCmd = if (Test-Path $launcherCmd) { "`"$launcherCmd`"" } else { "powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$launcher`"" }
New-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Run' -Name 'XPLabsLockScreen' -Value $runCmd -PropertyType String -Force | Out-Null
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
  Write-Host "Lockscreen not visible yet. Sign out/in once, or check:" -ForegroundColor Yellow
  Write-Host "  Get-Content C:\ProgramData\XPLabsAgent\logs\lockscreen-launch.log"
}
