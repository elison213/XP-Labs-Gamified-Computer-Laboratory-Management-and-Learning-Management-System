# Windows Script Catalog (Deployment Hardening)

This catalog defines the canonical deployment path for production and classifies all scripts as `primary`, `support`, or `deprecated`.

## Primary Scripts (production path)

- `windows/config/Apply-LabStabilityUpdate.ps1`
  - Canonical server update path (code update + migrations + service refresh + smoke checks).
- `windows/config/Deploy-ClientPowerShellApp.ps1`
  - Canonical client deployment path (agent files, optional UI payloads, config update, task registration/start).
- `windows/agent/Install-Agent.ps1`
  - Canonical installer invoked by deployment/GPO startup.
- `windows/agent/Uninstall-Agent.ps1`
  - Canonical cleanup/uninstall for client runtime.

## Support Scripts (safe to keep)

- `windows/config/Configure-LocalLabServer.ps1`
- `windows/config/Integrate-XplabsWebsite.ps1`
- `windows/config/Integrate-XplabsDatabase.ps1`
- `windows/config/Configure-ClientMachine.ps1`
- `windows/config/Discover-LabPCs.ps1`
- `windows/config/Configure-LockscreenKiosk.ps1`
- `windows/config/Revert-LockscreenKiosk.ps1`
- `windows/ops/Run-QueuedDeployment.ps1`
- `windows/ops/Run-QueuedUpdate.ps1`
- `windows/gpo/Startup-Install.ps1`
- `windows/agent/Build-ClientInstaller.ps1`
- `windows/agent/Build-ClientInstallerExe.ps1`

## Deprecated Usage (do not remove yet)

The following are not removed yet for compatibility but should not be used as first-choice deployment entrypoints:

- Direct manual copy of `Run-AgentLoop.ps1` / `XplabsAgent.psm1` to `Program Files` (use `Deploy-ClientPowerShellApp.ps1` instead).
- Ad-hoc `schtasks` recreation as regular deployment method (use `Install-Agent.ps1` / deploy script task registration).

## Compatibility Mapping (old -> canonical)

- Old: manual client install/copy + task recreation  
  New: `windows/config/Deploy-ClientPowerShellApp.ps1`
- Old: piecemeal server script sequence  
  New: `windows/config/Apply-LabStabilityUpdate.ps1`

## Must-pass Validation Gates

## Client
- `Run-AgentLoop.ps1 -Once` runs without parser/runtime errors.
- `agent.log` shows stable `checkin_state=online`.
- Command lifecycle moves `pending -> executed` for lock/unlock/shutdown.
- LockScreen and Widget tasks exist and run in interactive session.

## Server
- Migrations complete with no pending migrations.
- `lab_pcs.last_heartbeat` freshness matches server timezone.
- Dashboards show locked/online/offline states correctly.
- Override unlock failures surface actionable messages on lockscreen.

## Quick Rollback Commands

## Client rollback (known-good files)
- Restore known-good:
  - `C:\Program Files\XPLabsAgent\Run-AgentLoop.ps1`
  - `C:\Program Files\XPLabsAgent\XplabsAgent.psm1`
- Restart:
  - `Stop-ScheduledTask -TaskName XPLabsAgentLoop -ErrorAction SilentlyContinue`
  - `Start-ScheduledTask -TaskName XPLabsAgentLoop`
- Clear bad queued payloads:
  - `Remove-Item "C:\ProgramData\XPLabsAgent\heartbeat-spool\*.json" -Force -ErrorAction SilentlyContinue`

## Server rollback (app/runtime)
- Restore previous PHP/script versions from your VCS tag/backup.
- Re-run migration status check:
  - `C:\xampp\php\php.exe C:\xampp\htdocs\xplabs\database\migrate.php`
- Restart Apache:
  - `Restart-Service Apache2.4`
