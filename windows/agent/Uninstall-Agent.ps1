Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$programDir = Join-Path $env:ProgramFiles 'XPLabsAgent'
$dataDir = Join-Path $env:ProgramData 'XPLabsAgent'
$taskName = 'XPLabsAgentLoop'
$lockTask = 'XPLabsLockScreen'

try { Stop-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue } catch {}
try { Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue | Out-Null } catch {}
try { Stop-ScheduledTask -TaskName $lockTask -ErrorAction SilentlyContinue } catch {}
try { Unregister-ScheduledTask -TaskName $lockTask -Confirm:$false -ErrorAction SilentlyContinue | Out-Null } catch {}

if (Test-Path $programDir) { Remove-Item -Path $programDir -Recurse -Force -ErrorAction SilentlyContinue }
# Keep $dataDir by default (contains machine_key & logs). Uncomment to remove:
# if (Test-Path $dataDir) { Remove-Item -Path $dataDir -Recurse -Force -ErrorAction SilentlyContinue }

