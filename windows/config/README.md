# XPLabs Local Lab Server (Windows Server) — One‑click Config

This folder configures a **local-only** Windows Server instance to host XPLabs on your LAN with:
- **AD DS + DNS** (new forest)
- Internal DNS zone: `xplabs.com`
- Site DNS record: `lab.xplabs.com` → your server’s static IP
- Windows Firewall rules for **DNS** and **HTTP/HTTPS**
- XAMPP service checks (Apache/MySQL) so the webapp runs reliably

## Scripts in this folder
- `Configure-LocalLabServer.ps1` — one-click AD DS + DNS + server baseline setup.
- `Integrate-XplabsWebsite.ps1` — integrates the web app stack on server (DNS record, firewall, XAMPP services, optional IIS reverse-proxy checks).
- `Integrate-XplabsDatabase.ps1` — creates/imports the `xplabs` database (dump import, or migration + seed).
- `Configure-ClientMachine.ps1` — configures client DNS/network and validates reachability to `lab.local.xplabs.com`.
- `Apply-LabStabilityUpdate.ps1` — single-script updater for this stability/security release (runs migrations, verifies schema, refreshes services/task, prints smoke checks).
- `Deploy-ClientPowerShellApp.ps1` — one-script client deployment (optional network setup + agent install + agent config + task start).
- `Discover-LabPCs.ps1` — discovers hosts from DHCP/ARP on the server and syncs discovered PCs to XPLabs as unassigned.

## What this does (high-level)
1. Sets the server’s **static IP** (prompted).
2. Installs **AD DS** and **DNS Server** roles.
3. Promotes the machine to a **Domain Controller** for `xplabs.com`.
4. Creates DNS record: `lab.xplabs.com` → `<server_ip>`.
5. Opens firewall for DNS + web ports.
6. Verifies XAMPP services are installed and set to auto-start (best effort).

## Requirements
- Windows Server 2019/2022
- Windows Server 2025 is supported
- Run **Windows PowerShell 5.1** (`powershell.exe`) as Administrator
- Server NIC must be connected to your LAN (VMware Bridged recommended)
- You must have (or install) XAMPP on the server (script can validate; install itself is optional)

## Quick start (interactive)
Run:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force
.\Configure-LocalLabServer.ps1
```

## Website integration script
On the server (after local domain setup):

```powershell
.\Integrate-XplabsWebsite.ps1 -SiteHost "lab.local.xplabs.com" -DnsZone "local.xplabs.com" -XamppPath "C:\xampp" -ProjectPath "C:\xampp\htdocs\xplabs"
```

Optional flags:
- `-EnsureIisReverseProxy` (prepare IIS site guidance for ARR setup)
- `-RunMigrations` (best-effort call to `database\migrate.php` if `php` is available)

## Database integration script
Database package location:
- `windows/config/db/`
- put your full dump as: `windows/config/db/xplabs_dump.sql` (optional)

Run:

```powershell
.\Integrate-XplabsDatabase.ps1 -ProjectPath "C:\xampp\htdocs\xplabs" -XamppPath "C:\xampp" -DatabaseName "xplabs" -DbUser "root"
```

Config-file mode:

```powershell
Copy-Item .\db.config.example.json .\db.config.json
notepad .\db.config.json
.\Integrate-XplabsDatabase.ps1 -ConfigPath .\db.config.json
```

Behavior:
- If dump exists: imports `xplabs_dump.sql`.
- If no dump: runs `database\migrate.php` (if available), then applies `db/xplabs.post-import.seed.sql`.

## One-script release update
Use this when you only need to apply the latest app update on an existing server:

```powershell
.\Apply-LabStabilityUpdate.ps1 -ProjectPath "C:\xampp\htdocs\xplabs" -XamppPath "C:\xampp" -DatabaseName "xplabs" -DbUser "root"
```

## Client machine config script
On each client (run elevated):

```powershell
.\Configure-ClientMachine.ps1 -ServerDnsName "lab.local.xplabs.com" -ServerIp "192.168.1.10" -DnsServerIp "192.168.1.10"
```

If you need a static client IP:

```powershell
.\Configure-ClientMachine.ps1 -ServerDnsName "lab.local.xplabs.com" -ServerIp "192.168.1.10" -DnsServerIp "192.168.1.10" -SetStaticClientIp -ClientIp "192.168.1.20" -PrefixLength 24 -DefaultGateway "192.168.1.1"
```

## One-script client app deployment
Run on each client (elevated PowerShell):

```powershell
.\Deploy-ClientPowerShellApp.ps1 -ProjectPath "C:\xampp\htdocs\xplabs" -ServerBaseUrl "http://local.xplabs.com/xplabs" -FloorId 1 -StationId 1 -StartAgentNow
```

To include optional UI apps during deployment:
- `-LockscreenExePath "<path_to_XPLabs.LockScreen.exe>"`
- `-WidgetExePath "<path_to_XPLabs.Widget.exe>"`
- Deployment sequencing is hardened: UI EXEs are copied first, then `Install-Agent.ps1` runs once to register/update tasks.
- If `-SkipInstallAgent` is used, EXEs can be copied but task registration is not refreshed.

If you want discovery-first flow, omit floor/station (recommended):

```powershell
.\Deploy-ClientPowerShellApp.ps1 -ProjectPath "C:\xampp\htdocs\xplabs" -ServerBaseUrl "http://local.xplabs.com/xplabs" -StartAgentNow
```

If you also want it to configure DNS/network in the same run:

```powershell
.\Deploy-ClientPowerShellApp.ps1 -ProjectPath "C:\xampp\htdocs\xplabs" -ServerBaseUrl "http://local.xplabs.com/xplabs" -FloorId 1 -StationId 1 -ConfigureNetwork -ServerDnsName "local.xplabs.com" -ServerIp "192.168.100.22" -DnsServerIp "192.168.100.22" -StartAgentNow
```

## Config-file mode
1. Copy `config.example.json` to `config.json`
2. Edit values
3. Run:

```powershell
.\Configure-LocalLabServer.ps1 -ConfigPath .\config.json
```

### Generic static-IP template
If you want a minimal template for a static server IP, copy:
- `config.static-server.generic.json` → `config.json`
Then replace only:
- `CHANGE_ME_SERVER_STATIC_IP`
- `CHANGE_ME_GATEWAY_IP`

## After the server is configured
On a client PC:
- Ensure the client uses the server as DNS (DHCP option or manual DNS setting).
- Confirm:
  - `Resolve-DnsName lab.xplabs.com`
  - Browse `http://lab.xplabs.com/xplabs/`

## Step-by-step (VMware + clients) quick checklist
### VMware (Windows Server VM)
1. VM network adapter: **Bridged**
2. Boot the VM and run `Configure-LocalLabServer.ps1`
3. When DC promotion finishes, the VM will reboot automatically.

### Server static IP (what to enter)
- **IP**: a free LAN IP (example `192.168.1.50`)
- **PrefixLength**: typically `24`
- **Gateway**: your router (example `192.168.1.1`)
- **DNS**: the server IP itself (after DNS is installed)

### Client PCs (DHCP)
Option A (recommended): set DHCP DNS to the server IP on your router/DHCP server.\n
Option B: manually set a client’s DNS to the server IP for testing.

### Kiosk device (static)
- Assign a static IP on the LAN (so you can allowlist it in `config/app.php` under `kiosk.allowed_ips`).

## Validation runbook (recommended order)
### DNS
On the server:
- `Resolve-DnsName lab.xplabs.com`

On a client:
- `nslookup lab.xplabs.com`

### Web
On a client browser:
- `http://lab.xplabs.com/xplabs/`
- Login and open dashboards.

### XAMPP services
On the server:
- Ensure **Apache** and **MySQL** services are Running and set to Automatic.

### Kiosk (when ready)
1. Set the kiosk device IP in `config/app.php` → `kiosk.allowed_ips`.
2. From the kiosk device, open `http://lab.xplabs.com/xplabs/kiosk.php` and test unlock flow.

### PC agent (when ready)
- Configure lab PCs using `windows/agent/agent.config.json` with `server_base_url` set to `http://lab.xplabs.com/xplabs`.

## Discovery-first assignment workflow
1. Deploy clients with no floor/station in config.
2. On server, discover hosts:
   ```powershell
   .\Discover-LabPCs.ps1 -PreviewOnly
   ```
3. Sync discovered hosts with authenticated admin session (supply CSRF + session cookie):
   ```powershell
   .\Discover-LabPCs.ps1 -CsrfToken "<csrf_token>" -SessionCookie "XPLABS_SESSION=<session_cookie>"
   ```
4. In website, open `dashboard_lab_pcs.php` and assign unassigned PCs to a floor/station.
5. Assigned PCs appear in seatplan/monitoring context for that floor.

## Post-run validation commands
Run these after setup to verify critical dependencies:

```powershell
Get-Service Apache2.4,mysql | Format-Table Name,Status,StartType
Resolve-DnsName local.xplabs.com
Test-NetConnection local.xplabs.com -Port 80
curl http://local.xplabs.com/xplabs/api/auth/me -UseBasicParsing
```

For machine API validation from a registered PC:

```powershell
$k = Get-Content "$env:ProgramData\XPLabsAgent\machine_key.txt" -ErrorAction Stop
Invoke-RestMethod -Method GET -Uri "http://local.xplabs.com/xplabs/api/pc/config" -Headers @{ "X-Machine-Key" = $k }
Invoke-RestMethod -Method GET -Uri "http://local.xplabs.com/xplabs/api/pc/commands" -Headers @{ "X-Machine-Key" = $k }
```

## Client UI runtime checks
- Agent loop is background-only and does not create a desktop window.
- Lockscreen admin login screen is shown by `XPLabsLockScreen` when `state.json.locked=true`.
- Widget behavior:
  - clicking `X` hides to tray,
  - tray menu `Exit` triggers local lock request and returns lockscreen.
- Quick checks:
  - `Get-ScheduledTask -TaskName XPLabsLockScreen,XPLabsWidget -ErrorAction SilentlyContinue`
  - `Get-Content "$env:ProgramData\XPLabsAgent\logs\agent.log" -Tail 80`

## Heartbeat protocol validation (post-deploy)
- Confirm migration `049_add_heartbeat_delivery_protocol.sql` is applied.
- Confirm migration `050_add_pc_protocol_debug_events.sql` is applied.
- Validate heartbeat response includes:
  - `ack_id`
  - `command_cursor`
  - `commands`
  - `retry_after_sec`
- Validate command poll fallback endpoint:
  - `GET /api/pc/commands?after_cursor=<cursor>`
  - response `next_cursor` should monotonically increase
- Validate diagnostics endpoint (admin/teacher session required):
  - `GET /api/pc/debug-events?pc_id=<id>&limit=100`

## Protocol rollout flag (client)
- In `C:\ProgramData\XPLabsAgent\agent.config.json`:
  - `heartbeat_protocol_version: "v2"` (recommended)
  - `heartbeat_protocol_version: "v1"` (fallback compatibility mode)

## Agent debug mode
- Config keys:
  - `debug_enabled`
  - `debug_level` (`normal|verbose|trace`)
  - `debug_retention_days`
- Debug log path:
  - `C:\ProgramData\XPLabsAgent\logs\agent-debug.log`

### Recovery diagnostics checklist
- If check-ins are flaky:
  - inspect `agent.log` for `checkin_state=degraded|backoff|offline_buffering|recovered`
  - inspect `agent-debug.log` for correlated `heartbeat_id` and `ack_id`
  - query `/api/pc/debug-events` for server-side dedup/cursor traces
  - inspect spool directory: `C:\ProgramData\XPLabsAgent\heartbeat-spool`
  - verify machine API reachability using machine key
- If lockscreen does not appear after startup:
  - verify `XPLabsLockScreen` task exists and can start
  - verify lockscreen EXE path exists under Program Files
  - verify `state.json` still has `locked=true`

## Regression checklist before rollout
- API auth: verify session-only endpoints reject unauthenticated calls.
- Kiosk: verify unlock succeeds for enrolled student and fails for invalid LRN.
- Force logout: verify command is queued and session ends.
- Agent roundtrip: queue lock/unlock command and confirm command status changes to `executed`.

