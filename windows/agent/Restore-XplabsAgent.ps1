# Replace broken agent module/loop under Program Files from this script's folder. Run as Administrator on LAB PC.
$ErrorActionPreference = 'Stop'
$src = $PSScriptRoot
$dst = Join-Path $env:ProgramFiles 'XPLabsAgent'

foreach ($name in @('XplabsAgent.psm1', 'Run-AgentLoop.ps1')) {
  $from = Join-Path $src $name
  if (-not (Test-Path $from)) { throw "Missing $from - copy full windows\agent folder from server first." }
  Copy-Item -LiteralPath $from -Destination (Join-Path $dst $name) -Force
  Write-Host "Copied $name" -ForegroundColor Green
}

$lines = (Get-Content (Join-Path $dst 'XplabsAgent.psm1')).Count
if ($lines -gt 900) {
  throw "XplabsAgent.psm1 still looks too large ($lines lines). Use a clean copy from the server."
}

Write-Host "Validating module syntax..." -ForegroundColor Cyan
Import-Module (Join-Path $dst 'XplabsAgent.psm1') -Force
Write-Host "Module OK ($lines lines)." -ForegroundColor Green

schtasks /End /TN XPLabsAgentLoop 2>$null | Out-Null
Start-Sleep -Seconds 2
schtasks /Run /TN XPLabsAgentLoop | Out-Null
Start-Sleep -Seconds 5
Get-Content (Join-Path $env:ProgramData 'XPLabsAgent\logs\agent.log') -Tail 8
