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

## Logging
- `C:\\ProgramData\\XPLabsAgent\\logs\\agent.log`\n
- Windows Event Log (Application) entries are also written for key failures (optional).

