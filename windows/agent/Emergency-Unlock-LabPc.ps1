# Run as Administrator when locked out. Forces unlock without server or hotkey.
# Example: powershell -ExecutionPolicy Bypass -File Emergency-Unlock-LabPc.ps1
$ErrorActionPreference = 'Stop'
$statePath = Join-Path $env:ProgramData 'XPLabsAgent\state.json'
$dir = Split-Path -Parent $statePath
if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }

$until = (Get-Date).AddHours(8).ToString('s')
$state = @{
  locked                   = $false
  last_unlock_at           = (Get-Date).ToString('s')
  override_unlock_until    = $until
  last_override_status     = 'success'
  last_override_message    = 'Emergency local unlock'
  last_student_login_status = ''
  last_student_login_message = ''
}
$state | ConvertTo-Json -Compress | Set-Content -Path $statePath -Encoding UTF8

Get-Process -Name 'XPLabs.LockScreen' -ErrorAction SilentlyContinue | Stop-Process -Force
@(
  'student_login_request.json',
  'admin_hotkey_request.json',
  'override_request.json'
) | ForEach-Object {
  $p = Join-Path $dir $_
  if (Test-Path $p) { Remove-Item -LiteralPath $p -Force -ErrorAction SilentlyContinue }
}

$show = Join-Path $env:ProgramFiles 'XPLabsAgent\Show-Lockscreen.cmd'
if (Test-Path $show) { & cmd.exe /c "`"$show`"" | Out-Null }

Write-Host "Emergency unlock applied. locked=false until $until" -ForegroundColor Green
Write-Host "Restart agent if sign-in from lockscreen still fails: schtasks /Run /TN XPLabsAgentLoop"
