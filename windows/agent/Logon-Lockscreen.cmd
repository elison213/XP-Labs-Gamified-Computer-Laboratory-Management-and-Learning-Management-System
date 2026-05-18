@echo off
REM Force locked=true and show lockscreen on the logged-on user's desktop (not Admin/UAC session 0).
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Invoke-LockscreenUserSession.ps1"
exit /b %ERRORLEVEL%
