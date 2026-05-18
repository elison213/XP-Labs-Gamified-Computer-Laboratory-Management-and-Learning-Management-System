# Watch agent lock state while testing remote unlock from the dashboard/server.
param(
  [int] $IntervalSeconds = 2
)

$statePath = Join-Path $env:ProgramData 'XPLabsAgent\state.json'
$logPath = Join-Path $env:ProgramData 'XPLabsAgent\logs\agent.log'

Write-Host "Watching: $statePath" -ForegroundColor Cyan
Write-Host "Press Ctrl+C to stop." -ForegroundColor DarkGray

while ($true) {
  $locked = '?'
  $until = ''
  $lrn = ''
  if (Test-Path $statePath) {
    try {
      $json = Get-Content -Raw -Path $statePath -Encoding UTF8
      if ($json -match '"locked"\s*:\s*(true|false)') { $locked = $Matches[1] }
      if ($json -match '"override_unlock_until"\s*:\s*"([^"]*)"') { $until = $Matches[1] }
      if ($json -match '"last_lrn"\s*:\s*"([^"]*)"') { $lrn = $Matches[1] }
    } catch {}
  }
  $line = (Get-Date).ToString('HH:mm:ss') + " locked=$locked override_until=$until lrn=$lrn"
  Write-Host $line
  if (Test-Path $logPath) {
    Get-Content -Path $logPath -Tail 2 -ErrorAction SilentlyContinue | ForEach-Object {
      if ($_ -match 'unlock|lock|command|Unlocked') { Write-Host "  log: $_" -ForegroundColor DarkYellow }
    }
  }
  Start-Sleep -Seconds $IntervalSeconds
}
