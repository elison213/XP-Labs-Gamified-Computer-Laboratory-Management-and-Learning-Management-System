@echo off
REM Show lockscreen only when state.json says locked=true. Does NOT force re-lock.
cd /d "%~dp0"
set "EXE=%~dp0LockScreen\XPLabs.LockScreen.exe"
set "STATE=%ProgramData%\XPLabsAgent\state.json"
if not exist "%EXE%" exit /b 1

powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -Command "$j=''; if (Test-Path $env:STATE) { $j=Get-Content -Raw -LiteralPath $env:STATE -Encoding UTF8 }; if ($j -match '\"locked\"\s*:\s*false') { exit 10 } else { exit 0 }"
if %ERRORLEVEL%==10 exit /b 0

taskkill /F /IM XPLabs.LockScreen.exe >nul 2>&1
start "" /MAX "%EXE%"
exit /b 0
