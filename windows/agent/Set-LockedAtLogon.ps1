# Called from Logon-Lockscreen.cmd at user logon. Force lab lock after sign-in / reboot.
$ErrorActionPreference = 'SilentlyContinue'
$p = Join-Path $env:ProgramData 'XPLabsAgent\state.json'
$dir = Split-Path -Parent $p
if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }

$j = '{}'
if (Test-Path $p) {
  $raw = Get-Content -LiteralPath $p -Raw -Encoding UTF8
  if (-not [string]::IsNullOrWhiteSpace($raw)) { $j = $raw }
}

if ($j -match '"locked"\s*:') {
  $j = [regex]::Replace($j, '"locked"\s*:\s*(true|false)', '"locked": true')
} else {
  if ($j.Trim() -eq '{}') { $j = '{"locked":true}' }
  else { $j = $j.TrimEnd().TrimEnd('}') + ',"locked":true}' }
}

$j = [regex]::Replace($j, '"override_unlock_until"\s*:\s*"[^"]*"', '"override_unlock_until": ""')
$j = [regex]::Replace($j, '"student_login_grace_until"\s*:\s*"[^"]*"', '"student_login_grace_until": ""')
$j = [regex]::Replace($j, '"last_student_login_status"\s*:\s*"[^"]*"', '"last_student_login_status": ""')
$j = [regex]::Replace($j, '"last_student_login_message"\s*:\s*"[^"]*"', '"last_student_login_message": ""')

Set-Content -LiteralPath $p -Value $j -Encoding UTF8

try {
  $sync = Join-Path ${env:ProgramFiles} 'XPLabsAgent\Sync-LockedStatusToServer.ps1'
  if (Test-Path $sync) {
    & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $sync -Status locked | Out-Null
  }
} catch {}
