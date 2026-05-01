# XPLabs Windows Lab Agent (GPO Deploy)

This folder contains the **Windows-side** pieces that were referenced by comments in the PHP API (e.g., “Called by PowerShell …ps1”), but were not present in the repository.

## What it does
- **Registers** each lab PC with XPLabs (`POST /api/pc/register`) and stores the returned `machine_key` locally.
- Sends periodic **heartbeats** with status + basic system info (`POST /api/pc/heartbeat`).
- Polls and **executes remote commands** (`GET/POST /api/pc/commands`) like `lock`, `unlock`, `message`, `restart`, `shutdown`.
- Enforces “must scan QR at door kiosk” by **locking the screen until the server says access is allowed**.\n
## Files
- `windows/agent/Install-Agent.ps1`: install/copy agent locally + create scheduled tasks (for GPO Startup Script).
- `windows/agent/Run-AgentLoop.ps1`: long-running loop (heartbeat + command execution + validation).
- `windows/agent/XplabsAgent.psm1`: shared PowerShell functions.\n
- `windows/lockscreen/XPLabs.LockScreen/`: a simple full-screen lock UI (WPF) that blocks common shortcuts (Alt+F4, Alt+Tab, Win keys) as much as Windows allows. **Ctrl+Alt+Del cannot be fully disabled**.

## Required configuration
Create a JSON config on each lab PC at:\n
`C:\\ProgramData\\XPLabsAgent\\agent.config.json`\n
Use `windows/agent/agent.config.json.example` as a template.

At minimum you must set:
- `server_base_url` (example: `http://10.0.0.5/xplabs`)
- (recommended) `floor_id` and `station_id` so the server can map the PC to a station

## Server-side kiosk restriction (recommended)
Add the door tablet IP address to `config/app.php`:\n
`'kiosk' => ['allowed_ips' => ['<tablet_static_ip>'], ...]`

## GPO deployment overview
1. Put the `windows/` folder on a share accessible by computer accounts (e.g. `\\\\DOMAIN\\SYSVOL\\...\\XPLabs\\windows\\`).\n
2. GPO **Computer Configuration → Policies → Windows Settings → Scripts (Startup/Shutdown)**\n
   - Startup Script: run `Install-Agent.ps1` from the share.\n
3. After policy refresh/reboot, each PC will:\n
   - install to `C:\\Program Files\\XPLabsAgent\\`\n
   - create scheduled tasks\n
   - start heartbeating to the server\n

## Auto-deployment (server-orchestrated)
- The web app can now evaluate eligibility and queue deployments for discovered PCs.
- APIs:
  - `GET /api/pc/deploy-evaluate` (policy evaluation)
  - `POST /api/pc/deploy-queue` (queue jobs)
  - `POST /api/pc/deploy-run` (run queued jobs)
  - `POST /api/pc/deploy-retry` (retry one PC)
  - `POST /api/pc/deploy-policy` (exclude/include or tag a PC)
  - `GET /api/pc/deploy-status` (summary + recent jobs)
- PowerShell runner contract:
  - `windows/ops/Run-QueuedDeployment.ps1`
  - Inputs: target host, server base URL, windows share path, optional floor/station.
  - Output: JSON result (`success`, `message`, `data`) for API job result tracking.

## Auto-deploy policy
Configure in `config/app.php` under `pc_auto_deploy`:
- `allow_subnets`: optional CIDR allow list (if set, only these ranges are eligible).
- `deny_subnets`: CIDR blocks that must never receive deployment (AP/infra VLANs).
- `deny_tags`: explicit per-device tags that are excluded.
- `default_deny_unknown_networks`: fail-closed behavior for unknown/unclassified clients.
- `max_bulk_jobs_per_request`, `max_parallel_jobs`: rollout safety limits.

## Logging
- `C:\\ProgramData\\XPLabsAgent\\logs\\agent.log`\n
- `C:\\ProgramData\\XPLabsAgent\\logs\\agent-debug.log` (when `debug_enabled=true`)
- Windows Event Log (Application) entries are also written for key failures (optional).

## Validation checklist (sync + isolation + lockscreen)
- Monitoring sync:
  - `monitoring.php` and `dashboard_lab_pcs.php` should show aligned status for assigned PCs after heartbeat.
  - If heartbeat is stale (>5 minutes), both should show offline.
- Access isolation:
  - On unlock/checkin, agent applies drive mappings and folder rules from APIs.
  - On lock/checkout/forced lock, mapped drives are cleaned up.
  - Verify with `net use` and agent log entries.
- Lockscreen readiness:
  - `XPLabs.LockScreen.exe` present in Program Files lockscreen folder.
  - `XPLabsLockScreen` scheduled task exists.
  - `state.json` toggles `locked` and UI follows state.

## Kiosk shell deployment (agent/script-first)
- Build lockscreen EXE from:
  - `windows/lockscreen/XPLabs.LockScreen/XPLabs.LockScreen.csproj` (Release)
- Deploy client app and lockscreen in one step:
  - `windows/config/Deploy-ClientPowerShellApp.ps1 -ProjectPath "<repo>" -LockscreenExePath "<path_to_XPLabs.LockScreen.exe>" -StartAgentNow`
- Optional kiosk shell replacement:
  - Configure: `windows/config/Configure-LockscreenKiosk.ps1 -KioskUsername "<kiosk_user>"`
  - Revert: `windows/config/Revert-LockscreenKiosk.ps1`

## One-button client updates
- New APIs for update orchestration:
  - `POST /api/pc/update-queue`
  - `POST /api/pc/update-run`
  - `POST /api/pc/update-retry`
  - `GET /api/pc/update-status`
- Server runner:
  - `windows/ops/Run-QueuedUpdate.ps1`
- Behavior:
  - Copies latest agent scripts from server to target machine using PowerShell remoting.
  - Replaces lockscreen EXE when a built binary exists on server.
  - Restarts `XPLabsAgentLoop` and `XPLabsLockScreen` tasks after update.

## Build a standalone client installer (zip)
If you want a **single artifact** you can copy to a share, USB, or deployment system, build a zip that contains only the Windows deployment payload + a one-command entrypoint.

From the repo root:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force
.\windows\agent\Build-ClientInstaller.ps1 -Version "2026.04.27" -OutDir ".\dist\client-agent"
```

Optional: include a prebuilt lockscreen EXE in the artifact:

```powershell
.\windows\agent\Build-ClientInstaller.ps1 -Version "2026.04.27" -LockscreenExePath "C:\path\to\XPLabs.LockScreen.exe"
```

Optional: include the desktop widget EXE in the same artifact:

```powershell
.\windows\agent\Build-ClientInstaller.ps1 -Version "2026.04.27" -WidgetExePath "C:\path\to\XPLabs.Widget.exe"
```

On a client PC (run elevated), extract the zip and run:

```powershell
.\Install-XPLabsClient.ps1 -ServerBaseUrl "http://lab.xplabs.com/xplabs" -StartAgentNow
```

## Build a game-style EXE installer (wizard)
If you want a traditional setup wizard (`.exe`) similar to game installers, use:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force
.\windows\agent\Build-ClientInstallerExe.ps1 -Version "2026.04.28"
```

Optional payload flags:
- `-LockscreenExePath "C:\path\to\XPLabs.LockScreen.exe"`
- `-WidgetExePath "C:\path\to\XPLabs.Widget.exe"`

Outputs:
- `dist\client-agent\XPLabsClientAgentSetup-<version>.exe` (when Inno Setup is installed)
- `dist\client-agent\XPLabsClientInstaller-<version>.iss` (always generated)

Notes:
- Requires **Inno Setup 6** (`ISCC.exe`) to compile the EXE.
- If Inno Setup is missing, script still exports the `.iss` so you can compile later.
- Installer copies files to `C:\Program Files\XPLabsClientInstaller\` and launches a guided post-install setup script to collect server URL/floor/station.

## Desktop Agent Widget (tray + floating panel)
- Widget source project:
  - `windows/widget/XPLabs.Widget/`
- Build output expected by deploy scripts:
  - `XPLabs.Widget.exe`
- Install behavior:
  - If `XPLabs.Widget.exe` is provided to deploy/build scripts, installer copies it to:
    - `C:\Program Files\XPLabsAgent\Widget\XPLabs.Widget.exe`
  - Registers scheduled task:
    - `XPLabsWidget` (AtLogOn, interactive user)
- Widget capabilities:
  - tray icon + floating mini panel
  - shows local agent state (`state.json`) and server status (machine-key API calls)
  - open website shortcut
  - admin-only log viewer/export from `C:\ProgramData\XPLabsAgent\logs\agent.log`
  - close button (`X`) hides the widget to tray
  - tray icon `Exit` writes a local lock request so the lockscreen returns

### Expected runtime behavior
- `Run-AgentLoop.ps1` is headless by design (no window is shown).
- Visible UI is provided by optional WPF apps:
  - `XPLabsLockScreen` task (fullscreen login/lock screen at logon while locked)
  - `XPLabsWidget` task (floating/tray control panel at logon)
- If heartbeat is working but no window appears, verify lockscreen/widget tasks and EXE paths instead of troubleshooting the agent loop as a UI app.

### Quick UX verification checklist
- Confirm tasks exist:
  - `Get-ScheduledTask -TaskName XPLabsAgentLoop,XPLabsLockScreen,XPLabsWidget -ErrorAction SilentlyContinue`
- Confirm UI binaries exist:
  - `C:\Program Files\XPLabsAgent\LockScreen\XPLabs.LockScreen.exe`
  - `C:\Program Files\XPLabsAgent\Widget\XPLabs.Widget.exe`
- Confirm lockscreen flow:
  - set `state.json` `locked=true` and verify fullscreen login appears.
- Confirm widget flow:
  - click `X` and verify widget hides to tray.
  - right-click tray icon -> `Exit`; verify lockscreen returns shortly (agent consumes `lock_request.json`).

## Heartbeat reliability runbook (hard-cutover protocol)
- New heartbeat contract:
  - client sends `heartbeat_id` + `command_cursor` to `/api/pc/heartbeat`
  - server responds with `ack_id`, `command_cursor`, `commands[]`, `retry_after_sec`, `server_time`
- Reliability behaviors:
  - exponential backoff + jitter on transient network failures
  - unsent payloads queued in `C:\ProgramData\XPLabsAgent\heartbeat-spool\`
  - queued payloads drain before live heartbeat on recovery
  - duplicate heartbeat submissions are deduplicated server-side by `(pc_id, heartbeat_id)`
- protocol rollout flag:
  - `heartbeat_protocol_version: "v2"` (default, full idempotent contract)
  - `heartbeat_protocol_version: "v1"` (fallback compatibility mode; server auto-generates legacy heartbeat IDs)

### Debug layer
- Enable in `C:\ProgramData\XPLabsAgent\agent.config.json`:
  - `debug_enabled`: `true|false`
  - `debug_level`: `normal|verbose|trace`
  - `debug_retention_days`: integer days for local debug log trimming
- Agent debug event types include:
  - `checkin_attempt`, `checkin_backoff`, `spool_enqueue`, `spool_drain`, `command_poll`, `command_ack`, `ui_selfheal`
- Correlation fields:
  - `heartbeat_id`, `ack_id`, `command_cursor`, `attempt`, `delay_ms`, `error_class`
- Server-side protocol traces are queryable by admins at:
  - `GET /api/pc/debug-events`

### Failure test matrix
- DNS outage:
  - break DNS resolution for server host and wait 2-3 cycles
  - expected: `checkin_state=degraded/backoff`, spool files created
  - restore DNS
  - expected: `checkin_state=recovered`, spool drained, no manual restart needed
- HTTP 5xx / temporary server stop:
  - stop Apache or return 503 temporarily
  - expected: retries with bounded delay, no crash loop
  - restart server and confirm recovery
- Network disconnect:
  - disable NIC or unplug cable
  - expected: payload buffering and no process exit
  - reconnect NIC and confirm spool drain + online logs
- Startup lockscreen:
  - set `state.json` to `locked=true`, reboot/logon
  - expected: lockscreen task is present and starts automatically

### Operator commands
```powershell
Get-ScheduledTask -TaskName XPLabsAgentLoop,XPLabsLockScreen,XPLabsWidget -ErrorAction SilentlyContinue
Get-Content "$env:ProgramData\XPLabsAgent\logs\agent.log" -Tail 120
Get-Content "$env:ProgramData\XPLabsAgent\logs\agent-debug.log" -Tail 120
Get-ChildItem "$env:ProgramData\XPLabsAgent\heartbeat-spool" -ErrorAction SilentlyContinue
```

### Security note
- Log viewer is intentionally admin-gated on the client widget.
- Core agent behavior and machine-key API contract remain unchanged.

