param(
  [string] $ProjectPath = "",
  [string] $OutDir = "",
  [string] $Version = "",
  [string] $LockscreenExePath = "",
  [string] $WidgetExePath = "",
  [switch] $SkipCompile
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Resolve-ProjectPath([string]$InputPath) {
  if ($InputPath -and $InputPath.Trim().Length -gt 0) { return $InputPath }
  $scriptRoot = if ($PSScriptRoot) { $PSScriptRoot } else { Split-Path -Parent $MyInvocation.MyCommand.Path }
  return Split-Path -Parent (Split-Path -Parent $scriptRoot)
}

function Ensure-Dir([string]$Path) {
  if (-not (Test-Path $Path)) { New-Item -ItemType Directory -Path $Path -Force | Out-Null }
}

function Safe-RemoveDir([string]$Path) {
  if ($Path -and (Test-Path $Path)) {
    try { Remove-Item -Path $Path -Recurse -Force -ErrorAction Stop } catch {}
  }
}

function Get-IsccPath {
  $candidates = @(
    "C:\Program Files (x86)\Inno Setup 6\ISCC.exe",
    "C:\Program Files\Inno Setup 6\ISCC.exe"
  )
  foreach ($p in $candidates) {
    if (Test-Path $p) { return $p }
  }
  return $null
}

$ProjectPath = Resolve-ProjectPath -InputPath $ProjectPath
if (-not (Test-Path $ProjectPath)) { throw "ProjectPath not found: $ProjectPath" }

if (-not $Version -or $Version.Trim().Length -eq 0) {
  $Version = (Get-Date).ToString('yyyyMMdd-HHmm')
}

if (-not $OutDir -or $OutDir.Trim().Length -eq 0) {
  $OutDir = Join-Path $ProjectPath 'dist\client-agent'
}
Ensure-Dir $OutDir

$zipBuilder = Join-Path $ProjectPath 'windows\agent\Build-ClientInstaller.ps1'
if (-not (Test-Path $zipBuilder)) { throw "Missing zip builder script: $zipBuilder" }

$tempRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("xplabs-client-agent-exe-" + $Version)
$tempOutDir = Join-Path $tempRoot 'out'
$tempStageDir = Join-Path $tempRoot 'stage'
Ensure-Dir $tempOutDir
Ensure-Dir $tempStageDir

try {
  $zipArgs = @{
    ProjectPath = $ProjectPath
    OutDir = $tempOutDir
    Version = $Version
  }
  if ($LockscreenExePath -and $LockscreenExePath.Trim().Length -gt 0) { $zipArgs.LockscreenExePath = $LockscreenExePath }
  if ($WidgetExePath -and $WidgetExePath.Trim().Length -gt 0) { $zipArgs.WidgetExePath = $WidgetExePath }

  & $zipBuilder @zipArgs

  $zipPath = Join-Path $tempOutDir ("XPLabsClientAgent-$Version.zip")
  if (-not (Test-Path $zipPath)) { throw "Expected zip artifact not found: $zipPath" }
  Expand-Archive -Path $zipPath -DestinationPath $tempStageDir -Force

  $interactiveInstall = @'
param(
  [string] $ServerBaseUrl = "",
  [int] $FloorId = 0,
  [int] $StationId = 0,
  [switch] $StartAgentNow = $true
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

if (-not $ServerBaseUrl -or $ServerBaseUrl.Trim().Length -eq 0) {
  $ServerBaseUrl = Read-Host "Enter server base URL (example: http://lab.xplabs.com/xplabs)"
}
if (-not $ServerBaseUrl -or $ServerBaseUrl.Trim().Length -eq 0) {
  throw "Server base URL is required."
}

$floorInput = Read-Host "Floor ID (optional, press Enter to skip)"
$stationInput = Read-Host "Station ID (optional, press Enter to skip)"
if ($floorInput -and $floorInput.Trim().Length -gt 0) {
  try { $FloorId = [int]$floorInput } catch {}
}
if ($stationInput -and $stationInput.Trim().Length -gt 0) {
  try { $StationId = [int]$stationInput } catch {}
}

$scriptRoot = if ($PSScriptRoot) { $PSScriptRoot } else { Split-Path -Parent $MyInvocation.MyCommand.Path }
$entry = Join-Path $scriptRoot "Install-XPLabsClient.ps1"
if (-not (Test-Path $entry)) {
  throw "Missing installer entry script: $entry"
}

$args = @{
  ServerBaseUrl = $ServerBaseUrl
}
if ($FloorId -gt 0) { $args.FloorId = $FloorId }
if ($StationId -gt 0) { $args.StationId = $StationId }
if ($StartAgentNow) { $args.StartAgentNow = $true }

& $entry @args
'@
  Set-Content -Path (Join-Path $tempStageDir 'Install-XPLabsClientInteractive.ps1') -Value $interactiveInstall -Encoding UTF8

  $launcherCmd = @'
@echo off
setlocal
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0Install-XPLabsClientInteractive.ps1"
if %ERRORLEVEL% NEQ 0 (
  echo.
  echo Installer failed. See messages above.
  pause
  exit /b %ERRORLEVEL%
)
echo.
echo XPLabs client installation completed.
pause
'@
  Set-Content -Path (Join-Path $tempStageDir 'Install-XPLabsClient.cmd') -Value $launcherCmd -Encoding ASCII

  $stagePathEsc = ($tempStageDir -replace '\\','\\')
  $outDirEsc = ($OutDir -replace '\\','\\')
  $iss = @"
[Setup]
AppName=XPLabs Client Agent
AppVersion=$Version
AppPublisher=XPLabs
DefaultDirName={autopf}\XPLabsClientInstaller
DefaultGroupName=XPLabs Client Agent
DisableProgramGroupPage=yes
OutputDir=$outDirEsc
OutputBaseFilename=XPLabsClientAgentSetup-$Version
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=admin

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Files]
Source: "$stagePathEsc\*"; DestDir: "{app}"; Flags: recursesubdirs createallsubdirs ignoreversion

[Icons]
Name: "{group}\Install XPLabs Client"; Filename: "{app}\Install-XPLabsClient.cmd"
Name: "{autodesktop}\Install XPLabs Client"; Filename: "{app}\Install-XPLabsClient.cmd"

[Run]
Filename: "{app}\Install-XPLabsClient.cmd"; Description: "Launch XPLabs client setup now"; Flags: postinstall nowait shellexec
"@

  $issPath = Join-Path $tempRoot 'XPLabsClientInstaller.iss'
  Set-Content -Path $issPath -Value $iss -Encoding ASCII

  Copy-Item -Path $issPath -Destination (Join-Path $OutDir ("XPLabsClientInstaller-$Version.iss")) -Force

  if ($SkipCompile) {
    Write-Host "Inno Setup compile skipped. ISS exported to $OutDir" -ForegroundColor Yellow
    return
  }

  $iscc = Get-IsccPath
  if (-not $iscc) {
    Write-Warning "Inno Setup compiler (ISCC.exe) not found. Install Inno Setup 6, then run this script again."
    Write-Host "ISS script generated at: $OutDir" -ForegroundColor Yellow
    return
  }

  & $iscc $issPath | Out-Null

  $exePath = Join-Path $OutDir ("XPLabsClientAgentSetup-$Version.exe")
  if (-not (Test-Path $exePath)) {
    throw "Expected installer EXE not found: $exePath"
  }
  Write-Host "Built EXE installer: $exePath" -ForegroundColor Green
}
finally {
  Safe-RemoveDir $tempRoot
}

