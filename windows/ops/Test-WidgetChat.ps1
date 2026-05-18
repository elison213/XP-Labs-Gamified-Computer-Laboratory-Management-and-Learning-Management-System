# Quick widget messaging smoke test (run on lab PC in user session).
param(
  [string] $TestReply = ''
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$dataDir = Join-Path $env:ProgramData 'XPLabsAgent'
$configPath = Join-Path $dataDir 'agent.config.json'
$keyPath = Join-Path $dataDir 'machine_key.txt'
$pendingPath = Join-Path $dataDir 'pending_messages.json'
$statePath = Join-Path $dataDir 'state.json'

function Read-JsonFile([string]$Path) {
  if (-not (Test-Path $Path)) { return $null }
  return Get-Content -Raw -Path $Path -Encoding UTF8 | ConvertFrom-Json
}

Write-Host "== XPLabs widget chat test ==" -ForegroundColor Cyan

if (-not (Test-Path $configPath)) { throw "Missing $configPath" }
if (-not (Test-Path $keyPath)) { throw "Missing $keyPath — register PC first." }

$cfg = Read-JsonFile $configPath
$baseUrl = [string]$cfg.server_base_url
$machineKey = (Get-Content -Path $keyPath -Encoding UTF8 | Select-Object -First 1).Trim()

Write-Host "Server:  $baseUrl"
Write-Host "PC key:  $($machineKey.Substring(0, [Math]::Min(8, $machineKey.Length)))..."

$state = Read-JsonFile $statePath
$lrn = ''
if ($state -and $state.PSObject.Properties.Name -contains 'last_lrn') {
  $lrn = [string]$state.last_lrn
}
Write-Host "LRN in state: $(if ($lrn) { $lrn } else { '(none — student login or set last_lrn for replies)' })"

if (Test-Path $pendingPath) {
  Write-Host "`nPending file ($pendingPath):" -ForegroundColor Yellow
  Get-Content -Raw -Path $pendingPath
} else {
  Write-Host "`nNo pending_messages.json yet." -ForegroundColor DarkGray
}

$headers = @{ 'X-Machine-Key' = $machineKey }
$messagesUrl = ($baseUrl.TrimEnd('/')) + '/api/pc/messages.php'

Write-Host "`nGET $messagesUrl" -ForegroundColor Yellow
try {
  $resp = Invoke-RestMethod -Uri $messagesUrl -Method GET -Headers $headers -TimeoutSec 15
  $resp | ConvertTo-Json -Depth 8
  $threadId = 0
  if ($resp.threads -and $resp.threads.Count -gt 0) {
    $threadId = [int]$resp.threads[0].id
    Write-Host "Latest thread id: $threadId" -ForegroundColor Green
  }
} catch {
  Write-Warning "Messages API failed: $($_.Exception.Message)"
  $threadId = 0
}

$widgetProc = Get-Process -Name 'XPLabs.Widget' -ErrorAction SilentlyContinue
Write-Host "`nWidget process: $(if ($widgetProc) { 'running (PID ' + $widgetProc.Id + ')' } else { 'NOT running — start widget or send a dashboard message' })"

if ($TestReply -and $threadId -gt 0) {
  if (-not $lrn) { throw "Cannot send test reply without last_lrn in state.json" }
  $body = @{
    thread_id = $threadId
    lrn       = $lrn
    body      = $TestReply
  } | ConvertTo-Json
  $replyUrl = ($baseUrl.TrimEnd('/')) + '/api/pc/message-reply.php'
  Write-Host "`nPOST $replyUrl" -ForegroundColor Yellow
  Invoke-RestMethod -Uri $replyUrl -Method POST -Headers $headers -ContentType 'application/json' -Body $body
  Write-Host "Reply sent." -ForegroundColor Green
}

Write-Host "`nManual test:" -ForegroundColor Cyan
Write-Host "  1. Server: Lab PCs -> Message -> type hello -> Send"
Write-Host "  2. Lab PC: widget should pop up with [NEW] instructor line"
Write-Host "  3. Type reply in widget -> Send Reply"
Write-Host "  4. Server: Lab PCs -> Chats -> open thread -> see student reply"
Write-Host "`nOptional: .\Test-WidgetChat.ps1 -TestReply 'Hello from test script'"
