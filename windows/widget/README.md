# XPLabs Agent Widget

Desktop status widget for lab clients. It is separate from the core PowerShell agent loop.

## Features
- System tray icon with quick actions (show widget, open website, refresh, exit)
- Floating always-on-top panel for agent activity/status
- Displays:
  - agent lock/unlock state
  - machine/assignment hints
  - active user (best-effort)
  - last server time (best-effort)
- Admin-only log viewer + export from:
  - `C:\ProgramData\XPLabsAgent\logs\agent.log`

## Build
From this folder:

```powershell
msbuild .\XPLabs.Widget\XPLabs.Widget.csproj /p:Configuration=Release
```

Typical output:
- `XPLabs.Widget\bin\Release\XPLabs.Widget.exe`

## Deploy
Use one of the existing deployment entrypoints:
- `windows/config/Deploy-ClientPowerShellApp.ps1 -WidgetExePath "<path_to_XPLabs.Widget.exe>"`
- `windows/agent/Build-ClientInstaller.ps1 -WidgetExePath "<path_to_XPLabs.Widget.exe>"`

Installer places the EXE at:
- `C:\Program Files\XPLabsAgent\Widget\XPLabs.Widget.exe`

And creates scheduled task:
- `XPLabsWidget` (AtLogOn, interactive user)

