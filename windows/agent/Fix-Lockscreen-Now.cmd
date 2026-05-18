@echo off
REM Run Register-XplabsUiTasks.ps1 + show lockscreen via Logon-Lockscreen.cmd (user session).
setlocal
set "AGENT=C:\Program Files\XPLabsAgent"
set "LOGON=%AGENT%\Logon-Lockscreen.cmd"

if not exist "%LOGON%" (
  echo ERROR: Missing %LOGON%
  exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%AGENT%\Register-XplabsUiTasks.ps1" -SkipAgentRestart
if errorlevel 1 exit /b 2

echo Starting lockscreen in user session...
call "%LOGON%"
timeout /t 3 /nobreak >nul
powershell -NoProfile -Command "Get-Process XPLabs.LockScreen -EA SilentlyContinue | Select-Object Id,SessionId,ProcessName"
exit /b 0
