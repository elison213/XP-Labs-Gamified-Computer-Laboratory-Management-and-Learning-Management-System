@echo off
REM Run from the logged-on desktop (double-click or user cmd). Starts lockscreen in YOUR session.
taskkill /F /IM XPLabs.LockScreen.exe >nul 2>&1
start "" /MAX "C:\Program Files\XPLabsAgent\LockScreen\XPLabs.LockScreen.exe"
timeout /t 2 /nobreak >nul
powershell -NoProfile -ExecutionPolicy Bypass -Command "Get-Process XPLabs.LockScreen -EA SilentlyContinue | Select-Object Id,SessionId"
