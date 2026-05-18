@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Sync-LockedStatusToServer.ps1" -Status locked
exit /b %ERRORLEVEL%
