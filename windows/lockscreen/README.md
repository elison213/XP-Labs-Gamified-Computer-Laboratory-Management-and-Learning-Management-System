# XPLabs LockScreen (Windows)

This is a **full-screen lock UI** that runs on lab PCs until a student signs in (LRN + password), an instructor unlocks the PC from the dashboard, or staff uses admin override / Ctrl+Shift+X.

## What it blocks (best effort)
- Alt+F4, Alt+Tab, Win keys, Ctrl+Esc, Alt+Esc (via low-level keyboard hook)

## What it cannot block
- **Ctrl+Alt+Del** (Windows Security screen cannot be disabled from a normal user-mode app)

## How it decides lock/unlock
It watches:

`C:\\ProgramData\\XPLabsAgent\\state.json`

The PowerShell agent updates that file. When `locked: true`, the UI shows; when `locked: false`, it hides.

## Build
Open `windows/lockscreen/XPLabs.LockScreen/XPLabs.LockScreen.csproj` in Visual Studio and build **Release** for .NET Framework 4.8.

Copy the output EXE to:

`C:\\Program Files\\XPLabsAgent\\LockScreen\\XPLabs.LockScreen.exe`

The agent installer (`windows/agent/Install-Agent.ps1`) registers `XPLabsLockScreen` with:

- **At log on** (any user) — lock UI when someone signs in
- **At startup** (60s delay) — lock UI after reboot once a session is available
- **Agent loop** also starts the lockscreen when `state.json` has `"locked": true`

If the schtasks fallback is used, a companion task `XPLabsLockScreenBoot` runs on `ONSTART`.

## Shell Launcher / Assigned Access (Windows 10 Education/Enterprise)
If you want *maximum* lockdown, use **Shell Launcher** to force a dedicated kiosk Windows account to run only this app (or a wrapper). In that model:\n
- The desktop is replaced by the lock app (strong lockdown)\n
- Unlocking would need to transition into a separate allowed shell/app experience (requires additional work / policy decisions)\n

For most labs, a practical approach is:\n
- Keep Explorer as the shell\n
- Run LockScreen at logon and keep it always-on-top while locked\n
- Use GPO to harden the session (disable TaskMgr, block cmd/powershell for students, remove logoff/shutdown buttons, etc.)\n

## Runtime readiness checklist
- `C:\\Program Files\\XPLabsAgent\\LockScreen\\XPLabs.LockScreen.exe` exists
- Scheduled task `XPLabsLockScreen` exists (`Get-ScheduledTask -TaskName XPLabsLockScreen`)
- Agent loop task exists/running (`Get-ScheduledTask -TaskName XPLabsAgentLoop`)
- `C:\\ProgramData\\XPLabsAgent\\state.json` updates with `locked` changes
- Agent log contains lockscreen readiness message or warning:
  - `Lockscreen ready: exe+task detected`
  - or explicit missing-exe/task warning

## Kiosk shell mode (no GPO dependency)
Use the provided scripts to switch shell to lockscreen and roll back safely:

- Configure kiosk shell:
  - `windows/config/Configure-LockscreenKiosk.ps1 -KioskUsername "<kiosk_user>"`
- Revert kiosk shell:
  - `windows/config/Revert-LockscreenKiosk.ps1`

Notes:
- Scripts back up current shell to:
  - `C:\ProgramData\XPLabsAgent\kiosk-shell-backup.txt`
- Reboot is required after setting or reverting shell.
- Always test rollback path before production rollout.

