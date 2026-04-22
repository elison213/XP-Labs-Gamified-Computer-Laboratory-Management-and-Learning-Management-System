# XPLabs Local Lab Server (Windows Server) — One‑click Config

This folder configures a **local-only** Windows Server instance to host XPLabs on your LAN with:
- **AD DS + DNS** (new forest)
- Internal DNS zone: `xplabs.com`
- Site DNS record: `lab.xplabs.com` → your server’s static IP
- Windows Firewall rules for **DNS** and **HTTP/HTTPS**
- XAMPP service checks (Apache/MySQL) so the webapp runs reliably

## What this does (high-level)
1. Sets the server’s **static IP** (prompted).
2. Installs **AD DS** and **DNS Server** roles.
3. Promotes the machine to a **Domain Controller** for `xplabs.com`.
4. Creates DNS record: `lab.xplabs.com` → `<server_ip>`.
5. Opens firewall for DNS + web ports.
6. Verifies XAMPP services are installed and set to auto-start (best effort).

## Requirements
- Windows Server 2019/2022
- Run PowerShell **as Administrator**
- Server NIC must be connected to your LAN (VMware Bridged recommended)
- You must have (or install) XAMPP on the server (script can validate; install itself is optional)

## Quick start (interactive)
Run:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force
.\Configure-LocalLabServer.ps1
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

