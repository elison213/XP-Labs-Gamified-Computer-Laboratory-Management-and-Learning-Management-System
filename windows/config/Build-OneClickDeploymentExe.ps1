param(
  [string] $ProjectPath = '',
  [string] $OutDir = '',
  [string] $Version = '',
  [switch] $SkipCompile
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Resolve-ProjectPath([string]$InputPath) {
  if ($InputPath -and $InputPath.Trim().Length -gt 0) { return $InputPath }
  $scriptRoot = if ($PSScriptRoot) { $PSScriptRoot } else { Split-Path -Parent $MyInvocation.MyCommand.Path }
  return Split-Path -Parent (Split-Path -Parent $scriptRoot)
}

function Ensure-Dir([string] $Path) {
  if (-not (Test-Path $Path)) { New-Item -ItemType Directory -Path $Path -Force | Out-Null }
}

function Get-IsccPath {
  $candidates = @(
    'C:\Program Files (x86)\Inno Setup 6\ISCC.exe',
    'C:\Program Files\Inno Setup 6\ISCC.exe'
  )
  foreach ($p in $candidates) {
    if (Test-Path $p) { return $p }
  }
  return $null
}

function Safe-RemoveDir([string]$Path) {
  if ($Path -and (Test-Path $Path)) {
    try { Remove-Item -Path $Path -Recurse -Force -ErrorAction Stop } catch {}
  }
}

$ProjectPath = Resolve-ProjectPath -InputPath $ProjectPath
if (-not (Test-Path $ProjectPath)) { throw "ProjectPath not found: $ProjectPath" }
if (-not $Version -or $Version.Trim().Length -eq 0) { $Version = (Get-Date).ToString('yyyyMMdd-HHmm') }
if (-not $OutDir -or $OutDir.Trim().Length -eq 0) { $OutDir = Join-Path $ProjectPath 'dist\deploy' }
Ensure-Dir $OutDir

$launcher = Join-Path $ProjectPath 'windows\config\Invoke-OneClickDeployment.ps1'
if (-not (Test-Path $launcher)) { throw "Launcher script missing: $launcher" }

$tempRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("xplabs-oneclick-exe-" + $Version)
$stageDir = Join-Path $tempRoot 'stage'
Ensure-Dir $stageDir

try {
  Copy-Item -Path $launcher -Destination (Join-Path $stageDir 'Invoke-OneClickDeployment.ps1') -Force

  $cmd = @'
@echo off
setlocal
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0Invoke-OneClickDeployment.ps1"
if %ERRORLEVEL% NEQ 0 (
  echo.
  echo One-click deployment failed. See output above.
  pause
  exit /b %ERRORLEVEL%
)
echo.
echo One-click deployment completed.
pause
'@
  Set-Content -Path (Join-Path $stageDir 'XPLabsOneClickDeploy.cmd') -Value $cmd -Encoding ASCII

  $stageEsc = ($stageDir -replace '\\', '\\')
  $outEsc = ($OutDir -replace '\\', '\\')

  $iss = @"
[Setup]
AppName=XPLabs One-Click Deployment
AppVersion=$Version
AppPublisher=XPLabs
DefaultDirName={autopf}\XPLabsOneClickDeploy
DefaultGroupName=XPLabs One-Click Deployment
DisableProgramGroupPage=yes
OutputDir=$outEsc
OutputBaseFilename=XPLabsOneClickDeploy-$Version
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=admin

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Files]
Source: "$stageEsc\*"; DestDir: "{app}"; Flags: recursesubdirs createallsubdirs ignoreversion

[Icons]
Name: "{group}\Run XPLabs One-Click Deployment"; Filename: "{app}\XPLabsOneClickDeploy.cmd"
Name: "{autodesktop}\XPLabs One-Click Deployment"; Filename: "{app}\XPLabsOneClickDeploy.cmd"

[Run]
Filename: "{app}\XPLabsOneClickDeploy.cmd"; Description: "Launch One-Click Deployment"; Flags: postinstall nowait shellexec
"@

  $issPath = Join-Path $tempRoot 'XPLabsOneClickDeploy.iss'
  Set-Content -Path $issPath -Value $iss -Encoding ASCII
  Copy-Item -Path $issPath -Destination (Join-Path $OutDir ("XPLabsOneClickDeploy-$Version.iss")) -Force

  if ($SkipCompile) {
    Write-Host "Compile skipped. Generated ISS at $OutDir" -ForegroundColor Yellow
    return
  }

  $iscc = Get-IsccPath
  if (-not $iscc) {
    Write-Warning 'Inno Setup compiler not found. Install Inno Setup 6 and re-run to build EXE.'
    Write-Host "ISS file available at: $OutDir" -ForegroundColor Yellow
    return
  }

  & $iscc $issPath | Out-Null
  $exe = Join-Path $OutDir ("XPLabsOneClickDeploy-$Version.exe")
  if (-not (Test-Path $exe)) { throw "Expected EXE not found: $exe" }
  Write-Host "Built one-click EXE: $exe" -ForegroundColor Green
}
finally {
  Safe-RemoveDir $tempRoot
}
