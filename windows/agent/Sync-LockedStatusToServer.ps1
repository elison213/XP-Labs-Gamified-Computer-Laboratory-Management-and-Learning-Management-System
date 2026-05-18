# Push locked/online status to XPLabs server (no module import required).
param(
  [ValidateSet('locked', 'online', 'idle')]
  [string] $Status = 'locked'
)

$ErrorActionPreference = 'Stop'
$keyPath = Join-Path $env:ProgramData 'XPLabsAgent\machine_key.txt'
$cfgPath = Join-Path $env:ProgramData 'XPLabsAgent\agent.config.json'

if (-not (Test-Path $keyPath)) { throw "Missing machine key: $keyPath" }
if (-not (Test-Path $cfgPath)) { throw "Missing agent config: $cfgPath" }

$key = (Get-Content -LiteralPath $keyPath -Raw -Encoding ASCII).Trim()
$cfg = Get-Content -LiteralPath $cfgPath -Raw -Encoding UTF8 | ConvertFrom-Json
$base = [string]$cfg.server_base_url
if ([string]::IsNullOrWhiteSpace($base)) { throw 'server_base_url missing in agent.config.json' }
$base = $base.TrimEnd('/')

$cursor = 0
$statePath = Join-Path $env:ProgramData 'XPLabsAgent\state.json'
if (Test-Path $statePath) {
  $raw = Get-Content -LiteralPath $statePath -Raw -Encoding UTF8
  if ($raw -match '"last_command_cursor"\s*:\s*(\d+)') {
    try { $cursor = [int64]$Matches[1] } catch {}
  }
}

$body = @{
  heartbeat_id     = [guid]::NewGuid().ToString('N')
  command_cursor   = $cursor
  status           = $Status
  active_users     = @()
  system_info      = @{ hostname = $env:COMPUTERNAME }
  protocol_version = 'v2'
}

$uri = "$base/api/pc/heartbeat.php"
$headers = @{
  'Content-Type'  = 'application/json'
  'X-Machine-Key' = $key
}

try {
  $res = Invoke-RestMethod -Uri $uri -Method POST -Headers $headers -Body ($body | ConvertTo-Json -Depth 6)
  Write-Host "OK: server heartbeat status=$Status pc_id=$($res.pc_id) ack_id=$($res.ack_id)"
} catch {
  $uri2 = "$base/api/pc/heartbeat"
  try {
    $res = Invoke-RestMethod -Uri $uri2 -Method POST -Headers $headers -Body ($body | ConvertTo-Json -Depth 6)
    Write-Host "OK: server heartbeat status=$Status (fallback route)"
  } catch {
    throw $_.Exception.Message
  }
}
