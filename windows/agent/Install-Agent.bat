@echo off
setlocal
cd /d "%~dp0.."
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Install-Agent.ps1" -SourceDir "%~dp0.." %*
if errorlevel 1 exit /b 1
exit /b 0
