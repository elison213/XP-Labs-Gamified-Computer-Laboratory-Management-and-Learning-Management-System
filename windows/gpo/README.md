# GPO Deployment Guide (XPLabs Lab PCs)

This guide assumes **Windows 10 Education/Enterprise** lab PCs joined to a domain.

## 1) Put the agent on a share
Copy the repo’s `windows/` folder to a domain share readable by **computer accounts** (e.g. SYSVOL):\n
Example:\n
- `\\\\DOMAIN\\SYSVOL\\DOMAIN\\scripts\\XPLabs\\windows\\`

## 2) Create the agent config file per PC (recommended)
Each PC needs:\n
`C:\\ProgramData\\XPLabsAgent\\agent.config.json`

You can seed it using:\n
`windows/agent/agent.config.json.example`

Minimum fields:\n
- `server_base_url`: where the PHP app is hosted (example `http://10.0.0.5/xplabs`)\n
- `floor_id` + `station_id`: so the server maps the PC to a lab station\n

### How to set `station_id` per PC
Options:\n
- **Manual**: copy a different config file per PC.\n
- **GPP Item-Level Targeting**: “Files” preference item per computer name.\n
- **Naming convention**: extend the installer to map hostname → station_id (not implemented yet).\n

## 3) GPO: install at computer startup
Go to:\n
**Computer Configuration → Policies → Windows Settings → Scripts (Startup/Shutdown) → Startup**\n

Add a PowerShell startup script that calls the installer from the share:\n
- Script: `PowerShell.exe`\n
- Parameters:\n
  `-NoProfile -ExecutionPolicy Bypass -File \"\\\\DOMAIN\\SYSVOL\\DOMAIN\\scripts\\XPLabs\\windows\\gpo\\Startup-Install.ps1\" -WindowsShareRoot \"\\\\DOMAIN\\SYSVOL\\DOMAIN\\scripts\\XPLabs\\windows\"`\n

## 4) Deploy the LockScreen EXE (build output)
Build the WPF project:\n
- `windows/lockscreen/XPLabs.LockScreen/XPLabs.LockScreen.csproj` (Release, .NET Framework 4.8)\n

Copy the EXE to each PC:\n
`C:\\Program Files\\XPLabsAgent\\LockScreen\\XPLabs.LockScreen.exe`\n

Then either:\n
- rerun the agent installer (it will create the logon scheduled task if the EXE exists), or\n
- create your own GPO logon task to start it.\n

## 5) Baseline lockdown policies (recommended)
These are standard GPO hardening items for lab/kiosk accounts:\n
- **Disable Task Manager**: User Config → Admin Templates → System → Ctrl+Alt+Del Options → Remove Task Manager\n
- **Remove Lock/Logoff** options: same area (optional)\n
- **Hide command prompt / PowerShell** for students/kiosk users\n
- **Disable access to Registry tools**\n
- **Remove Run / Windows+R** (Win key is blocked best-effort by app, but also set policies)\n
- **Disable “Switch user”** (optional)\n
\n
Note: **Ctrl+Alt+Del cannot be fully disabled**.\n

## 6) Uninstall / rollback
Run:\n
`C:\\Program Files\\XPLabsAgent\\Uninstall-Agent.ps1`\n

This removes scheduled tasks and the program folder (keeps ProgramData by default).\n

## 7) Hybrid scaling model (recommended)
- Use GPO startup install as the primary scalable path for domain-joined PCs.
- Use app-managed deployment queue as fallback for newly discovered/missed PCs.
- Exclusions are enforced by app policy (`config/app.php` `pc_auto_deploy`):
  - deny VLAN/subnet ranges for AP/infra segments,
  - deny tags such as `exclude_auto_deploy`.

## 8) Validation checklist for rollout
- In `dashboard_lab_pcs.php`, verify deployment badges (`pending`, `in_progress`, `installed`, `failed`, `excluded`).
- Run policy evaluation from dashboard and confirm excluded devices show a reason.
- Queue deployments in small batches first, then run queued jobs.
- Confirm client writes:
  - `C:\\ProgramData\\XPLabsAgent\\agent.config.json`
  - `C:\\ProgramData\\XPLabsAgent\\machine_key.txt`
  - `C:\\ProgramData\\XPLabsAgent\\logs\\agent.log`

