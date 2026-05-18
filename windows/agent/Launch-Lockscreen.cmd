@echo off
REM Wrapper so schtasks /TR does not split on PowerShell flags (-NoProfile, etc.)
powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "%~dp0Launch-Lockscreen.ps1"
