# Start widget on the logged-in desktop. Run via: schtasks /Run /TN XPLabsShowWidget
$ErrorActionPreference = 'SilentlyContinue'
$exe = Join-Path $env:ProgramFiles 'XPLabsAgent\Widget\XPLabs.Widget.exe'
if (-not (Test-Path $exe)) { exit 1 }

Get-Process -Name 'XPLabs.Widget' -ErrorAction SilentlyContinue | ForEach-Object {
  if ($_.SessionId -eq 0) {
    Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue
  }
}

$visible = Get-Process -Name 'XPLabs.Widget' -ErrorAction SilentlyContinue | Where-Object { $_.SessionId -gt 0 }
if ($visible) { exit 0 }

Start-Process -FilePath $exe -WindowStyle Normal
exit 0
