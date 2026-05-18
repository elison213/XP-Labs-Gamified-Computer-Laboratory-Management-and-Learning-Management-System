@echo off
REM Build lockscreen + widget and copy to Program Files. Run as Administrator.
cd /d "%~dp0"

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Build-AllClient.ps1" -Configuration Release
if errorlevel 1 exit /b 1

set "LOCK=%~dp0lockscreen\XPLabs.LockScreen\bin\Release\net48\XPLabs.LockScreen.exe"
set "WIDGET=%~dp0widget\XPLabs.Widget\bin\Release\net48\XPLabs.Widget.exe"
set "DST=C:\Program Files\XPLabsAgent"

if not exist "%LOCK%" (
  echo Build output missing: %LOCK%
  exit /b 2
)

schtasks /End /TN XPLabsAgentLoop >nul 2>&1
taskkill /F /IM XPLabs.LockScreen.exe >nul 2>&1
taskkill /F /IM XPLabs.Widget.exe >nul 2>&1

if not exist "%DST%\LockScreen" mkdir "%DST%\LockScreen"
if not exist "%DST%\Widget" mkdir "%DST%\Widget"

copy /Y "%LOCK%" "%DST%\LockScreen\XPLabs.LockScreen.exe"
if exist "%WIDGET%" copy /Y "%WIDGET%" "%DST%\Widget\XPLabs.Widget.exe"

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%DST%\Fix-LockscreenTasks.ps1"
schtasks /Run /TN XPLabsAgentLoop >nul 2>&1

echo.
echo Deployed:
echo   %DST%\LockScreen\XPLabs.LockScreen.exe
echo   %DST%\Widget\XPLabs.Widget.exe
exit /b 0
