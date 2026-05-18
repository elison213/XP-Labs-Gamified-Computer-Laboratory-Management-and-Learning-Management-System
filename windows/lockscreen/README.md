# XPLabs LockScreen (Windows)

This is a **full-screen lock UI** that is meant to run on lab PCs to prevent students from using the machine until XPLabs grants access (via door kiosk QR scan or teacher unlock).

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

The agent installer (`windows/agent/Install-Agent.ps1`) will create a scheduled task named `XPLabsLockScreen` if the EXE exists.

## Shell Launcher / Assigned Access (Windows 10 Education/Enterprise)
If you want *maximum* lockdown, use **Shell Launcher** to force a dedicated kiosk Windows account to run only this app (or a wrapper). In that model:\n
- The desktop is replaced by the lock app (strong lockdown)\n
- Unlocking would need to transition into a separate allowed shell/app experience (requires additional work / policy decisions)\n

For most labs, a practical approach is:\n
- Keep Explorer as the shell\n
- Run LockScreen at logon and keep it always-on-top while locked\n
- Use GPO to harden the session (disable TaskMgr, block cmd/powershell for students, remove logoff/shutdown buttons, etc.)\n

