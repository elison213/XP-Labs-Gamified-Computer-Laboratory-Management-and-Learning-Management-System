# Test lockscreen student login path (run on lab PC as Administrator).
param(
  [string] $Lrn = '20241001',
  [string] $Password = 'password123'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$dataDir = Join-Path $env:ProgramData 'XPLabsAgent'
$configPath = Join-Path $dataDir 'agent.config.json'
$keyPath = Join-Path $dataDir 'machine_key.txt'
$reqPath = Join-Path $dataDir 'student_login_request.json'
$logPath = Join-Path $dataDir 'logs\agent.log'

Write-Host "== XPLabs student login test ==" -ForegroundColor Cyan

if (-not (Test-Path $configPath)) { throw "Missing $configPath" }
if (-not (Test-Path $keyPath)) { throw "Missing $keyPath - run agent registration / deploy first." }

$cfg = Get-Content $configPath -Raw | ConvertFrom-Json
$base = [string]$cfg.server_base_url
$key = (Get-Content $keyPath -Raw).Trim()

Write-Host "Server: $base"
if ($base -match 'YOUR-SERVER') {
  Write-Host "ERROR: server_base_url is still a placeholder. Fix agent.config.json first." -ForegroundColor Red
  exit 2
}

$task = Get-ScheduledTask -TaskName 'XPLabsAgentLoop' -ErrorAction SilentlyContinue
if ($task) {
  Write-Host "Agent task state: $($task.State)"
} else {
  Write-Host "WARNING: XPLabsAgentLoop task not found" -ForegroundColor Yellow
}

Write-Host "`nCalling API directly..." -ForegroundColor Yellow
$body = @{ lrn = $Lrn; password = $Password } | ConvertTo-Json
$headers = @{ 'Content-Type' = 'application/json'; 'X-Machine-Key' = $key }
try {
  $res = Invoke-RestMethod -Uri ($base.TrimEnd('/') + '/api/session/pc-student-login.php') -Method POST -Headers $headers -Body $body -TimeoutSec 20
  Write-Host "API OK: $($res.message)" -ForegroundColor Green
} catch {
  Write-Host "API FAILED: $($_.Exception.Message)" -ForegroundColor Red
  try {
    $res2 = Invoke-RestMethod -Uri ($base.TrimEnd('/') + '/api/session/pc-student-login') -Method POST -Headers $headers -Body $body -TimeoutSec 20
    Write-Host "API OK (fallback route): $($res2.message)" -ForegroundColor Green
  } catch {
    Write-Host "Fallback FAILED: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "Deploy api/session/pc-student-login.php on the server and run database/seed_demo_population.php" -ForegroundColor Yellow
    exit 1
  }
}

Write-Host "`nWriting lockscreen request file (agent should process in ~5s)..." -ForegroundColor Yellow
if (Test-Path $reqPath) { Remove-Item $reqPath -Force }
$payload = (@{ lrn = $Lrn; password = $Password } | ConvertTo-Json -Compress)
Set-Content -Path $reqPath -Value $payload -Encoding UTF8

Start-Sleep -Seconds 8
if (Test-Path $reqPath) {
  Write-Host "Request file still present - agent may be stopped or stuck." -ForegroundColor Red
  Write-Host "Run: schtasks /Run /TN XPLabsAgentLoop" -ForegroundColor Yellow
} else {
  Write-Host "Request consumed by agent." -ForegroundColor Green
}

$statePath = Join-Path $dataDir 'state.json'
if (Test-Path $statePath) {
  $raw = Get-Content $statePath -Raw
  Write-Host "`nstate.json:" -ForegroundColor Cyan
  Write-Host $raw
}

if (Test-Path $logPath) {
  Write-Host "`nagent.log (last 15 lines):" -ForegroundColor Cyan
  Get-Content $logPath -Tail 15
}
