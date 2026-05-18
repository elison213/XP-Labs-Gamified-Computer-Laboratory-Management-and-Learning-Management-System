# Force lab lock + lockscreen on the interactive desktop (works from Admin PowerShell).
$ErrorActionPreference = 'Stop'
$invoke = Join-Path (Join-Path $env:ProgramFiles 'XPLabsAgent') 'Invoke-LockscreenUserSession.ps1'
if (-not (Test-Path $invoke)) {
  throw "Missing $invoke — copy latest agent scripts to Program Files\XPLabsAgent."
}
Write-Host 'Starting lockscreen in user session...' -ForegroundColor Cyan
& powershell.exe -NoProfile -ExecutionPolicy Bypass -File $invoke
$code = $LASTEXITCODE
Write-Host "Exit code: $code" -ForegroundColor $(if ($code -eq 0) { 'Green' } else { 'Yellow' })
Get-Process XPLabs.LockScreen -ErrorAction SilentlyContinue | Select-Object Name, Id, SessionId
Get-Content (Join-Path $env:ProgramData 'XPLabsAgent\logs\logon-lockscreen.log') -Tail 5 -ErrorAction SilentlyContinue
