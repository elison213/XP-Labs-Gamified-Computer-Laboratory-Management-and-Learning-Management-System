# XPLabs fast rebuild (MeshCentral-informed agent + full features)

This tree matches the **full build**: activity audit logs, lab desktop activity, PC messaging, command fixes, and `XplabsUserSession.psm1` (user-session UI bridge — same idea as MeshCentral running desktop tools in the logged-on session, not as SYSTEM).

**Agent version:** `windows/agent/AGENT_VERSION.txt` (currently **6**)

## Server (`192.168.100.22`)

```bat
cd C:\xampp\htdocs\xplabs
C:\xampp\php\php.exe database\migrate.php
```

If desktop activity tab says table missing:

```bat
C:\xampp\php\php.exe tools\ensure-pc-activity-table.php
```

Clear stuck remote commands (replace `8` with your PC id):

```bat
C:\xampp\php\php.exe tools\reset-pc-command-queue.php --pc_id=8
```

## Lab PC (`192.168.100.23`)

Copy `C:\xampp\htdocs\xplabs\windows` to `C:\xplabs\windows` (or map share), then **elevated PowerShell**:

```powershell
cd C:\xplabs
windows\Build-AllClient.bat

powershell -ExecutionPolicy Bypass -File ".\windows\agent\Rebuild-LabClientFromServer.ps1" `
  -ServerShare "C:\xplabs\windows" `
  -ResetState

powershell -ExecutionPolicy Bypass -File "C:\Program Files\XPLabsAgent\Repair-PcCommandCursor.ps1" -ResetToZero
```

`agent.config.json`:

```json
"server_base_url": "http://local.xplabs.com/xplabs"
```

Hosts on lab PC: `192.168.100.22    local.xplabs.com`

## Test commands

1. Dashboard → Lab PCs → **Lock** → lockscreen in ~5–30s  
2. **Unlock** → widget  
3. Activity Logs → **System audit** and **Lab desktop activity** (after student uses widget)

Log: `C:\ProgramData\XPLabsAgent\agent.log` → `Command processed id=… type=lock`
