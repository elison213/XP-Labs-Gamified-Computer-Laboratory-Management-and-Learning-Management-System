@echo off
REM Backup: show lockscreen if still locked — must not force re-lock after unlock.
timeout /t 20 /nobreak >nul
call "%~dp0Show-Lockscreen.cmd"
