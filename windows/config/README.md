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

## Regression checklist before rollout
- API auth: verify session-only endpoints reject unauthenticated calls.
- Kiosk: verify unlock succeeds for enrolled student and fails for invalid LRN.
- Force logout: verify command is queued and session ends.
- Agent roundtrip: queue lock/unlock command and confirm command status changes to `executed`.

