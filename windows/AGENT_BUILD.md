# XPLabs lab PC agent — build and deploy

Copy the whole `xplabs` folder to **server** (`C:\xampp\htdocs\xplabs`) and **each lab PC** (e.g. `C:\xplabs`). Run server DB migrations once; build UI on any machine with the .NET SDK; deploy clients with elevated PowerShell.

## Server (once)

1. Run migrations (includes PC messaging + activity):
   - `database/migrations/051_pc_messaging.sql` (if not applied)
   - `database/migrations/052_pc_activity_events.sql`
2. Ensure Apache serves `/api/pc/messages.php`, `/api/pc/message-reply.php`, `/api/pc/activity.php`.

## Build lockscreen + widget

From repo root (requires [.NET SDK](https://dotnet.microsoft.com/download) with **net48** targeting pack).

If PowerShell says *running scripts is disabled*, use **either** option below (no permanent policy change required).

**Option A — double-click or CMD (easiest):**

```bat
cd C:\xplabs
windows\Build-AllClient.bat
```

**Option B — one-liner bypass for this run only:**

```powershell
cd C:\xplabs
powershell -NoProfile -ExecutionPolicy Bypass -File .\windows\Build-AllClient.ps1 -Configuration Release
```

**Option C — allow scripts for your user only (optional, persistent):**

```powershell
Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned
cd C:\xplabs
.\windows\Build-AllClient.ps1 -Configuration Release
```

**Option D — build without any script:**

```powershell
cd C:\xplabs
dotnet build .\windows\lockscreen\XPLabs.LockScreen\XPLabs.LockScreen.csproj -c Release
dotnet build .\windows\widget\XPLabs.Widget\XPLabs.Widget.csproj -c Release
```

Outputs:

- `windows\lockscreen\XPLabs.LockScreen\bin\Release\net48\XPLabs.LockScreen.exe`
- `windows\widget\XPLabs.Widget\bin\Release\net48\XPLabs.Widget.exe`

## Deploy lab PC (elevated)

```powershell
cd C:\xplabs   # or your copy path
.\windows\config\Deploy-ClientPowerShellApp.ps1 `
  -ServerBaseUrl "http://local.xplabs.com/xplabs" `
  -FloorId 1 -StationId 3 `
  -LockscreenExePath ".\windows\lockscreen\XPLabs.LockScreen\bin\Release\net48" `
  -WidgetExePath ".\windows\widget\XPLabs.Widget\bin\Release\net48" `
  -StartAgentNow
```

Agent + scheduled tasks install to `C:\Program Files\XPLabsAgent\`. Runtime data: `C:\ProgramData\XPLabsAgent\` (`state.json`, `machine_key.txt`, `agent.config.json`, `pending_messages.json`).

## Features

| Feature | How it works |
|--------|----------------|
| **Admin hotkey** | On lock screen: **Ctrl+Shift+X** → writes `admin_hotkey_request.json` → agent unlocks 30 min, starts widget (no password). |
| **Instructor message** | Dashboard queues `message` command → agent adds `pending_messages.json` + starts widget → student sees thread and can reply. |
| **Desktop activity** | Widget samples foreground app / idle → `POST /api/pc/activity.php` → `pc_activity_events` table. |
| **Frameless widget** | Borderless window; drag title bar; tray icon to hide/show. |

## Remote commands (lock / unlock / restart)

Dashboard **Lock**, **Unlock**, and **Restart** queue rows in `remote_commands`. The lab agent (`Run-AgentLoop.ps1`) picks them up on **heartbeat** and **poll**, but runs each command **once per loop tick** (deduped batch).

| Step | What happens |
|------|----------------|
| 1 | Instructor queues command → `remote_commands` status `pending`. |
| 2 | Agent sends `command_cursor` from `C:\ProgramData\XPLabsAgent\state.json` (`last_command_cursor`). |
| 3 | Server returns commands with `id > cursor` (heartbeat + `GET /api/pc/commands.php`). |
| 4 | Agent runs command in the **logged-on user session** via `XplabsUserSession.psm1` (same idea as MeshCentral `sessionDispatch` in `MeshCentral-master/agents/modules_meshcore/win-deskutils.js`). |
| 5 | Agent `POST /api/pc/commands.php` with `command_id` + status → server marks executed and advances `lab_pcs.last_command_cursor`. |
| 6 | Agent updates local `last_command_cursor` only after a successful ack. |

**Reliability fixes (agent build 5+):**

- Heartbeat no longer advances the server cursor before ack (stale `restart` on reboot was a common symptom).
- Same command is not processed twice when heartbeat and poll return it in the same tick.
- Lock/unlock are idempotent if already in the desired UI state.
- `restart` / `shutdown` ack **before** `Restart-Computer` / `Stop-Computer`.

**If lock/unlock stops working after a server queue reset:** reset **both** sides (replace `8` with PC id):

```bat
C:\xampp\php\php.exe tools\reset-pc-command-queue.php --pc_id=8
```

On the lab PC (elevated):

```powershell
powershell -ExecutionPolicy Bypass -File "C:\Program Files\XPLabsAgent\Repair-PcCommandCursor.ps1" -ResetToZero
```

If the agent cursor on the PC is higher than pending command ids on the server, commands are never delivered until you run the repair script above.

## Lab desktop activity (what apps students use)

This is separate from system audit logs (`admin_logs`). It records **foreground window** while the widget is running (student unlocked).

```
XPLabs.Widget (user session)
  DesktopActivitySampler.cs  → foreground HWND title + process name, idle seconds
  MainWindow.xaml.cs         → buffer every ~15s, POST batch
       ↓
POST /api/pc/activity.php  (machine_key auth)
       ↓
PcActivityService → pc_activity_events
       ↓
Admin → Activity logs → tab “Lab desktop activity”
```

Event types:

- `app_focus` — student switched app/window (`payload.title`, `payload.process`, optional `payload.lrn` from `state.json`).
- `idle` — no input for 120+ seconds (`payload.idle_seconds`).

Server also attaches `user_id` from the active `pc_sessions` row when the widget does not send one.

**Requirements:** migration `052_pc_activity_events.sql` (or `php tools/ensure-pc-activity-table.php`), widget built and deployed, student signed in so the widget runs.

## Verify

1. Lock screen shows; student login unlocks as before.
2. From dashboard, send a PC message — widget should open with **[NEW]** line.
3. Student reply appears in instructor thread.
4. Use PC a minute with browser/editor — check `pc_activity_events` for `app_focus` / `idle` rows.
5. **Ctrl+Shift+X** on lock screen unlocks and opens widget without admin password.

### Remote lockscreen (from server dashboard)

Use **Lock** on the PC card in `dashboard_lab_pcs.php` (not Unlock). That queues a `lock` command; the agent sets `locked=true` and starts the lockscreen within a few seconds.

CLI on server:

```bat
C:\xampp\php\php.exe tools\queue-remote-lock.php --hostname=YOUR-PC-NAME
```

### Widget chat test

**Server:** run migration `database/migrations/051_create_pc_message_threads.sql` if not applied.

1. Lab PC online (`XPLabsAgentLoop` running), student logged in once (so `last_lrn` is set) or unlock with LRN.
2. Dashboard → **Lab PCs** → **Message** on that PC → send e.g. `Chat test at 3pm`.
3. Widget should open; **Messages** panel shows `[NEW] Instructor: ...`.
4. Student types in **Send Reply** → dashboard **Chats** on same PC shows the reply.

Lab PC diagnostic:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File C:\xplabs\windows\ops\Test-WidgetChat.ps1
```

Lockscreen runs **at boot** (90s after startup via `XPLabsLockScreenPrep`) and **at logon** (`XPLabsLockScreen`), and the agent re-launches it in the **logged-on user session** while the PC is locked.

### Lab PC repair (one command)

The lab client must be deployed from the **server repo** only (`C:\xampp\htdocs\xplabs\windows` on the XAMPP machine). `AGENT_VERSION.txt` = **6**, `XplabsUserSession.psm1` present, `XplabsAgent.psm1` **950+ lines**. Do not rely on a stale `C:\xplabs` copy unless synced from the server share.

### Full rebuild (recommended on lab PC)

Administrator PowerShell on the **lab PC**:

```powershell
# Replace YOUR-SERVER with the XAMPP machine hostname or IP
powershell -ExecutionPolicy Bypass -File "\\YOUR-SERVER\c$\xampp\htdocs\xplabs\windows\agent\Rebuild-LabClientFromServer.ps1" `
  -ServerShare '\\YOUR-SERVER\c$\xampp\htdocs\xplabs\windows' `
  -ResetState
```

Or use repair (calls rebuild when `-ServerShare` is set):

```powershell
powershell -ExecutionPolicy Bypass -File C:\xplabs\windows\agent\Repair-LabPc.ps1 -ServerShare '\\YOUR-SERVER\c$\xampp\htdocs\xplabs\windows\agent'
```

Validation:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File "C:\Program Files\XPLabsAgent\Get-AgentBuildInfo.ps1"
# build_ok=True from Get-AgentBuildInfo (agent >= 800 lines + user-session >= 250 + version 4)
```

UI launch path: **only** `Logon-Lockscreen.cmd` → `Invoke-LockscreenUserSession.ps1` → `XplabsUserSession.psm1` (SYSTEM agent never `Start-Process` lockscreen directly).

Do **not** run `php` tools from `tools\` on the lab PC — those run on the XAMPP server only.

### Lab PC validation checklist

```powershell
schtasks /Query /TN XPLabsLockScreen /FO LIST | findstr "Task To Run"
schtasks /Query /TN XPLabsWidget /FO LIST | findstr "Task To Run"
Get-Content C:\ProgramData\XPLabsAgent\state.json
```

| Test | Expected |
|------|----------|
| Sign out / sign in | Fullscreen lockscreen (`XPLabs.LockScreen`, SessionId > 0) |
| Student login on lockscreen | `locked: false`, widget in tray (SessionId > 0) |
| Dashboard **Lock** | Log: `Lockscreen visible in user session`; `Get-Process XPLabs.LockScreen` SessionId > 0 |
| Dashboard **Unlock** | Button only when server status is **Locked**; then `locked: false`, lockscreen process gone |
| `agent.log` after lock | If UI fails: `Lock command applied but lockscreen UI not visible` then self-heal retry |

Kill invisible session-0 copies:

```powershell
Get-Process XPLabs.Widget, XPLabs.LockScreen -EA SilentlyContinue | Where-Object SessionId -eq 0 | Stop-Process -Force
```

After updating agent scripts on the lab PC, restart the agent task:

```powershell
Restart-ScheduledTask -TaskName XPLabsAgentLoop
```

## Agent loop only (no full deploy)

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\windows\agent\Install-Agent.ps1 -SourceDir .\windows
```

If deploy failed with *Task XML contains a value which is incorrectly formatted* on lockscreen/widget tasks, update `windows\agent\Install-Agent.ps1` from the repo (fixes invalid `NT AUTHORITY\INTERACTIVE` principal) and re-run the command above.

Verify tasks:

```powershell
Get-ScheduledTask -TaskName 'XPLabs*' | Format-Table TaskName, State
```
