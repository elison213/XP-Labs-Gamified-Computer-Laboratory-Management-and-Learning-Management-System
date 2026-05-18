@echo off
REM Runs in logged-on user session. Starts widget via user-session bridge.
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Invoke-WidgetUserSession.ps1"
exit /b %ERRORLEVEL%
