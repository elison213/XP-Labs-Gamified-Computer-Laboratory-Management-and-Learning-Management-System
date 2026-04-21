# End-to-end validation checklist (Lab PCs + Door Kiosk)

## Server prerequisites
- XPLabs site is reachable from:\n
  - door tablet (kiosk)\n
  - all lab PCs\n
- In `config/app.php`, set:\n
  - `kiosk.allowed_ips` to the **static IP** of the door tablet\n
  - optionally `kiosk.issued_by_user_id` (must be an existing `users.id`)\n

## 1) Door kiosk flow (server-only)
1. Open `kiosk.php` on the door tablet.\n
2. Scan a student QR.\n
3. Expect:\n
   - green “Checked in successfully! Unlocking PC (HOSTNAME)…”\n
   - if no PC available: error “No available PC found”\n

## 2) Lab PC agent registration/heartbeat
On a lab PC (after GPO deploy):\n
1. Confirm files exist:\n
   - `C:\\Program Files\\XPLabsAgent\\Run-AgentLoop.ps1`\n
   - `C:\\ProgramData\\XPLabsAgent\\agent.config.json`\n
2. Confirm scheduled task exists:\n
   - `XPLabsAgentLoop`\n
3. Confirm `machine_key` generated:\n
   - `C:\\ProgramData\\XPLabsAgent\\machine_key.txt`\n
4. Confirm heartbeat:\n
   - `lab_pcs.last_heartbeat` updates\n
   - `dashboard_lab_pcs.php` shows PC online/locked\n

## 3) Unlock on QR scan
1. Ensure the PC is **locked** (agent state: `locked: true`).\n
2. Scan student QR at the door kiosk.\n
3. Expect:\n
   - server creates a `pc_sessions` row\n
   - server queues `remote_commands` type `unlock`\n
   - agent receives command and sets `locked: false` in `state.json`\n
   - LockScreen UI hides and the desktop becomes usable\n

## 4) Auto-lock when session invalid
1. End the session (teacher locks PC OR student checkout OR server removes active session).\n
2. Expect:\n
   - agent calls `/api/session/validate` periodically\n
   - when it returns `action=lock_screen`, agent sets `locked: true`\n
   - LockScreen UI reappears\n

## 5) Teacher lock/unlock controls
1. From `dashboard_lab_pcs.php` send Lock and Unlock.\n
2. Expect:\n
   - `remote_commands` queued\n
   - agent processes and acks command\n
   - PC state toggles\n

## 6) Logs
- Agent log:\n
  - `C:\\ProgramData\\XPLabsAgent\\logs\\agent.log`\n
- Server:\n
  - PHP error log / `storage/logs/error.log`\n

