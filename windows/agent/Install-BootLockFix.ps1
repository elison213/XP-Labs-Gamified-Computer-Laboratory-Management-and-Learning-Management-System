# Patches deployed agent so boot lock runs once per Windows boot (not every agent restart).
# Run as Administrator on the LAB PC. Copy this file via C:\xplabs\windows\agent\ or USB.
$ErrorActionPreference = 'Stop'

$agentDir = Join-Path $env:ProgramFiles 'XPLabsAgent'
$loopPath = Join-Path $agentDir 'Run-AgentLoop.ps1'
$modPath = Join-Path $agentDir 'XplabsAgent.psm1'

if (-not (Test-Path $loopPath)) { throw "Missing $loopPath" }
if (-not (Test-Path $modPath)) { throw "Missing $modPath" }

$bootLockFunctions = @'

function Get-WindowsLastBootTimeIso {
  try {
    return (Get-CimInstance Win32_OperatingSystem -ErrorAction Stop).LastBootUpTime.ToString('o')
  } catch {
    return ''
  }
}

function Test-ShouldApplyBootLock {
  $boot = Get-WindowsLastBootTimeIso
  if ([string]::IsNullOrWhiteSpace($boot)) { return $false }
  $p = Get-XplabsPaths
  $markerPath = Join-Path $p.DataDir 'last_boot_lock_applied.txt'
  if (Test-Path $markerPath) {
    $last = (Get-Content -LiteralPath $markerPath -Raw -ErrorAction SilentlyContinue).Trim()
    if ($last -eq $boot) { return $false }
  }
  try {
    Set-Content -LiteralPath $markerPath -Value $boot -Encoding ASCII -Force
  } catch {
    Write-XplabsLog -Level warn -Message "Could not write boot lock marker: $($_.Exception.Message)"
  }
  return $true
}

'@

$mod = Get-Content -LiteralPath $modPath -Raw
if ($mod -notmatch 'function Test-ShouldApplyBootLock') {
  $mod = $mod -replace '(function Initialize-BootLockState \{)', ($bootLockFunctions + '$1')
  if ($mod -notmatch 'Test-ShouldApplyBootLock') { throw 'Failed to patch XplabsAgent.psm1' }
}
if ($mod -notmatch 'Test-ShouldApplyBootLock,') {
  $mod = $mod -replace 'Initialize-BootLockState, Send-XplabsStatusHeartbeat', 'Initialize-BootLockState, Test-ShouldApplyBootLock, Get-WindowsLastBootTimeIso, Send-XplabsStatusHeartbeat'
}
Set-Content -LiteralPath $modPath -Value $mod -Encoding UTF8

$loop = Get-Content -LiteralPath $loopPath -Raw
$oldBlock = @'
# After reboot: lock in state only. UI must start at user logon (SYSTEM cannot show the desktop).
$state = Initialize-BootLockState
if ($state.locked) {
  Invoke-XplabsAccessCleanup
  Stop-XplabsSessionZeroLockscreen
  Send-XplabsStatusHeartbeat -Status 'locked' -MachineKey $machineKey | Out-Null
  Write-XplabsLog -Level info -Message 'Boot lock applied; lockscreen shows at user logon via scheduled task'
}
'@
$newBlock = @'
# After Windows boot only (not each agent loop restart).
if (Test-ShouldApplyBootLock) {
  $state = Initialize-BootLockState
  if ($state.locked) {
    Invoke-XplabsAccessCleanup
    Stop-XplabsSessionZeroLockscreen
    Send-XplabsStatusHeartbeat -Status 'locked' -MachineKey $machineKey | Out-Null
    Write-XplabsLog -Level info -Message 'Boot lock applied; lockscreen shows at user logon via scheduled task'
  }
} else {
  Write-XplabsLog -Level info -Message 'Agent loop started (skipping boot lock; not a fresh Windows boot)'
}
'@
if ($loop -match [regex]::Escape($oldBlock)) {
  $loop = $loop.Replace($oldBlock, $newBlock)
} elseif ($loop -match 'Test-ShouldApplyBootLock') {
  Write-Host 'Run-AgentLoop.ps1 already patched.' -ForegroundColor Yellow
} else {
  throw 'Run-AgentLoop.ps1 layout unexpected; copy full files from dev server instead.'
}
Set-Content -LiteralPath $loopPath -Value $loop -Encoding UTF8

Write-Host 'Boot lock fix installed.' -ForegroundColor Green
Write-Host 'Restarting agent once...' -ForegroundColor Cyan
schtasks /End /TN XPLabsAgentLoop 2>$null | Out-Null
Start-Sleep -Seconds 2
schtasks /Run /TN XPLabsAgentLoop | Out-Null
Start-Sleep -Seconds 4
Get-Content (Join-Path $env:ProgramData 'XPLabsAgent\logs\agent.log') -Tail 5
